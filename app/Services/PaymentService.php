<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\Room;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AuditLog;

// Fallback/Stub for Midtrans if package is not installed (for development environments)
if (!class_exists('Midtrans\CoreApi')) {
    class_alias('App\Services\MidtransStub', 'Midtrans\CoreApi');
    class_alias('App\Services\MidtransConfigStub', 'Midtrans\Config');
}


class PaymentService
{
    private $isMockMode;
    private $dokuConfig;
    private $whatsappService;
    private $emailService;

    public function __construct(WhatsAppService $whatsappService, EmailService $emailService)
    {
        $this->whatsappService = $whatsappService;
        $this->emailService = $emailService;
        // Check if using mock mode (Midtrans/Doku not configured yet)
        $this->isMockMode = empty(env('MIDTRANS_SERVER_KEY')) && empty(env('DOKU_CLIENT_ID'));

        if (!$this->isMockMode) {
            // Initialize Midtrans configuration
            if (env('MIDTRANS_SERVER_KEY')) {
                \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
                \Midtrans\Config::$clientKey = env('MIDTRANS_CLIENT_KEY');
                \Midtrans\Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
                \Midtrans\Config::$isSanitized = true;
                \Midtrans\Config::$is3ds = true;
            }

            // Initialize Doku configuration
            if (env('DOKU_CLIENT_ID')) {
                $this->dokuConfig = [
                    'client_id' => env('DOKU_CLIENT_ID'),
                    'secret_key' => env('DOKU_SECRET_KEY'),
                    'is_production' => env('DOKU_ENV') === 'production',
                    'base_url' => env('DOKU_ENV') === 'production'
                        ? 'https://api.doku.com'
                        : 'https://api-sandbox.doku.com',
                ];
            }
        }
    }

    /**
     * Create payment link for a booking or payment log
     * 
     * @param Booking|PaymentLog $model
     * @param float $amount
     * @param string $method qris|virtual_account|bank_transfer
     * @param string $paymentType down_payment|full_payment
     * @return array
     */
    public function createPaymentLink($model, $amount, $method, $paymentType = 'down_payment')
    {
        $modelId = $model->id;
        $isLog = $model instanceof PaymentLog;
        $paymentRef = 'PAY-' . date('Ymd') . '-' . str_pad($modelId, 6, '0', STR_PAD_LEFT) . '-' . time();

        // Map specific payment methods to database enum values
        $methodUpper = strtoupper($method);
        $dbPaymentMethod = $method; // Default to original

        if (str_starts_with($methodUpper, 'VIRTUAL_ACCOUNT_')) {
            $dbPaymentMethod = 'virtual_account';
        } elseif ($methodUpper === 'QRIS') {
            $dbPaymentMethod = 'qris';
        } elseif (str_starts_with($methodUpper, 'EMONEY_') || str_starts_with($methodUpper, 'PEER_TO_PEER_')) {
            $dbPaymentMethod = 'bank_transfer'; // Generic for e-wallets and p2p
        } elseif ($methodUpper === 'CREDIT_CARD') {
            $dbPaymentMethod = 'edc';
        } elseif (in_array($methodUpper, ['DOKU', 'DOKU_CHECKOUT'])) {
            $dbPaymentMethod = 'bank_transfer';
        }

        // Create payment record first
        $payment = Payment::create([
            'booking_id' => $isLog ? null : $modelId,
            'payment_log_id' => $isLog ? $modelId : null,
            'payment_ref' => $paymentRef,
            'amount' => $amount,
            'payment_method' => $dbPaymentMethod, // Use mapped value
            'payment_type' => $paymentType,
            'status' => 'pending',
            'payment_data' => json_encode([]),
        ]);

        if ($this->isMockMode) {
            $result = $this->createMockPayment($payment, $model, $amount, $method);

            // Auto-confirm properly to ensure Booking is created/confirmed
            $this->mockConfirmPayment($payment->id);

            return $result;
        }

        $isDokuGateway = env('PAYMENT_GATEWAY') === 'doku';
        $methodUpper = strtoupper($method);
        $isDokuMethod = str_starts_with(strtolower($method), 'doku') ||
            $methodUpper === 'QRIS' ||
            str_starts_with($methodUpper, 'VIRTUAL_ACCOUNT_') ||
            str_starts_with($methodUpper, 'EMONEY_');

        if ($isDokuGateway || $isDokuMethod) {
            return $this->createDokuPayment($payment, $model, $amount, $method);
        }

        return $this->createMidtransPayment($payment, $model, $amount, $method);
    }

    /**
     * Mock payment for development/testing
     */
    private function createMockPayment(Payment $payment, $model, $amount, $method)
    {
        $mockData = [
            'payment_id' => $payment->id,
            'payment_ref' => $payment->payment_ref,
            'mock_mode' => true,
        ];

        $methodUpper = strtoupper($method);
        if ($methodUpper === 'QRIS' || $methodUpper === 'qris') {
            $mockData['qr_code_url'] = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($payment->payment_ref);
            $mockData['expiry_time'] = Carbon::now()->addMinutes(15)->toIso8601String();
        } else {
            // Virtual Account
            $mockData['va_number'] = '8800' . str_pad($model->id, 12, '0', STR_PAD_LEFT);
            // Detect bank from method code (e.g., VIRTUAL_ACCOUNT_BCA -> BCA)
            $bank = 'BCA';
            if (str_starts_with($methodUpper, 'VIRTUAL_ACCOUNT_')) {
                $bank = str_replace('VIRTUAL_ACCOUNT_', '', $methodUpper);
            }
            $mockData['bank'] = $bank;
            $mockData['expiry_time'] = Carbon::now()->addHours(24)->toIso8601String();
        }

        $payment->update(['payment_data' => json_encode($mockData)]);

        return [
            'success' => true,
            'payment_id' => $payment->id,
            'payment_ref' => $payment->payment_ref,
            'method' => $method,
            'amount' => $amount,
            'data' => $mockData,
            'message' => 'Mock payment created. Auto-confirm available for testing.',
        ];
    }

    /**
     * Real Midtrans payment (ready for production)
     */
    private function createMidtransPayment(Payment $payment, $model, $amount, $method)
    {
        try {
            $user = null;
            if ($model instanceof Booking) {
                $user = $model->user;
            } else {
                // POS/Ad-Hoc: Safe user check
                $userId = $model->booking_data['user_id'] ?? null;
                $user = $userId ? \App\Models\User::find($userId) : null;
            }

            $params = [
                'transaction_details' => [
                    'order_id' => $payment->payment_ref,
                    'gross_amount' => (int) $amount,
                ],
                'customer_details' => [
                    'first_name' => $user->name ?? 'Customer',
                    'email' => $user->email ?? '',
                    'phone' => $user->phone ?? '',
                ],
                'item_details' => [
                    [
                        'id' => ($model instanceof Booking ? 'BOOKING-' : 'LOG-') . $model->id,
                        'price' => (int) $amount,
                        'quantity' => 1,
                        'name' => 'Payment for Booking ' . ($model instanceof Booking ? '#' . $model->booking_ref : ''),
                    ]
                ],
            ];

            // Add payment method specific params
            $methodLower = strtolower($method);
            if ($methodLower === 'qris') {
                $params['payment_type'] = 'qris';
            } else {
                $params['payment_type'] = 'bank_transfer';
                // Detect bank from method name if possible, default to bca
                $bank = 'bca';
                if (str_contains(strtoupper($method), 'MANDIRI'))
                    $bank = 'mandiri';
                if (str_contains(strtoupper($method), 'BNI'))
                    $bank = 'bni';
                if (str_contains(strtoupper($method), 'BRI'))
                    $bank = 'bri';
                if (str_contains(strtoupper($method), 'PERMATA'))
                    $bank = 'permata';

                $params['bank_transfer'] = ['bank' => $bank];
            }

            $response = \Midtrans\CoreApi::charge($params);

            $paymentData = [
                'transaction_id' => $response->transaction_id ?? null,
                'transaction_status' => $response->transaction_status ?? null,
            ];

            if ($method === 'qris' && isset($response->actions)) {
                foreach ($response->actions as $action) {
                    if ($action->name === 'generate-qr-code') {
                        $paymentData['qr_code_url'] = $action->url;
                    }
                }
            } else if (isset($response->va_numbers)) {
                $paymentData['va_number'] = $response->va_numbers[0]->va_number ?? null;
                $paymentData['bank'] = $response->va_numbers[0]->bank ?? null;
            }

            $paymentData['expiry_time'] = $response->expiry_time ?? null;

            $payment->update(['payment_data' => json_encode($paymentData)]);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'payment_ref' => $payment->payment_ref,
                'method' => $method,
                'amount' => $amount,
                'data' => $paymentData,
            ];

        } catch (\Exception $e) {
            Log::error('Midtrans payment failed: ' . $e->getMessage());
            $payment->update(['status' => 'failed']);
            throw $e;
        }
    }

    /**
     * DOKU Checkout Payment (Hosted Page)
     */
    private function createDokuPayment(Payment $payment, $model, $amount, $method)
    {
        Log::info('createDokuPayment: Start', ['model' => get_class($model), 'id' => $model->id]);
        try {
            $user = null;
            if ($model instanceof Booking) {
                $user = $model->user;
            } else {
                // For PaymentLog (POS/Ad-hoc), user might be null or guest.
                // Safely try to finding a user or default to null.
                Log::info('createDokuPayment: Handling PaymentLog', ['booking_data_keys' => array_keys($model->booking_data ?? [])]);
                $userId = $model->booking_data['user_id'] ?? null;
                $user = $userId ? \App\Models\User::find($userId) : null;
            }

            $requestId = Str::uuid()->toString();

            // DOKU Direct API Endpoints vs Checkout API
            $directEndpoints = [
                'VIRTUAL_ACCOUNT_BCA' => '/bca-virtual-account/v2/payment-code',
                'VIRTUAL_ACCOUNT_MANDIRI' => '/mandiri-virtual-account/v2/payment-code',
                'VIRTUAL_ACCOUNT_BNI' => '/bni-virtual-account/v2/payment-code',
                'VIRTUAL_ACCOUNT_BRI' => '/bri-virtual-account/v2/payment-code',
                'VIRTUAL_ACCOUNT_PERMATA' => '/permata-virtual-account/v2/payment-code',
                'QRIS' => '/qris-handler/v1/generate-qr'
            ];

            $targetPath = '/checkout/v1/payment'; // Force Hosted Checkout for Sandbox stability
            $isDirect = false;

            /* 
            // Disable Direct API for Sandbox due to inconsistent endpoint support (QRIS 404)
            if (isset($directEndpoints[$method])) {
                $targetPath = $directEndpoints[$method];
                $isDirect = true;
                Log::info('createDokuPayment: Using Direct API', ['endpoint' => $targetPath]);
            }
            */

            $lineItems = [];
            $itemsTotal = 0;

            if ($payment->payment_type === 'down_payment') {
                $lineItems[] = [
                    'name' => $this->sanitizeDokuString('Down Payment booking ' . ($model->booking_ref ?? $model->id)),
                    'price' => (int) $amount,
                    'quantity' => 1
                ];
                $itemsTotal = (int) $amount;
                Log::info('createDokuPayment: Use summary for Down Payment');
            } else {
                if ($model instanceof Booking) {
                    $bookingItems = $model->items()->with(['service', 'variant'])->get();
                    if ($bookingItems->isNotEmpty()) {
                        foreach ($bookingItems as $item) {
                            $itemName = $item->service->name ?? 'Layanan';
                            if ($item->variant) {
                                $itemName .= ' (' . $item->variant->name . ')';
                            }

                            $itemPrice = (int) ($item->price + ($item->room_charge ?? 0));
                            $lineItems[] = [
                                'name' => $this->sanitizeDokuString(substr($itemName, 0, 50)),
                                'price' => $itemPrice,
                                'quantity' => 1
                            ];
                            $itemsTotal += $itemPrice;
                        }
                    } else {
                        // Fallback to main booking service
                        $itemName = $model->service->name ?? 'Layanan';
                        // Check if booking has a variant relationship (Booking model has service, but might need variant lookup)
                        // Looking at Booking.php, it has service() but not variant().
                        // For now let's use service name.

                        $itemPrice = (int) $model->total_price;
                        $lineItems[] = [
                            'name' => $this->sanitizeDokuString(substr($itemName, 0, 50)),
                            'price' => $itemPrice,
                            'quantity' => 1
                        ];
                        $itemsTotal += $itemPrice;
                    }
                } elseif (isset($model->booking_data['items']) && is_array($model->booking_data['items'])) {
                    foreach ($model->booking_data['items'] as $item) {
                        $itemPrice = (int) ($item['price'] ?? 0);
                        $itemQuantity = (int) ($item['quantity'] ?? 1);

                        $lineItems[] = [
                            'name' => $this->sanitizeDokuString(substr($item['name'] ?? 'Item', 0, 50)),
                            'price' => $itemPrice,
                            'quantity' => $itemQuantity
                        ];

                        $itemsTotal += ($itemPrice * $itemQuantity);
                    }
                }
            }

            // DOKU strict rule: order.amount == sum(line_items[].price * line_items[].quantity)
            $finalAmount = (int) $amount;

            if ($itemsTotal === 0) {
                // Fallback to summary if no items populated
                $lineItems[] = [
                    'name' => $this->sanitizeDokuString('Pembayaran Booking ' . ($model->booking_ref ?? $model->id)),
                    'price' => $finalAmount,
                    'quantity' => 1
                ];
                $itemsTotal = $finalAmount;
            } elseif ($finalAmount > $itemsTotal) {
                $diff = $finalAmount - $itemsTotal;
                $lineItems[] = [
                    'name' => $this->sanitizeDokuString('Biaya Layanan / Penyesuaian'),
                    'price' => $diff,
                    'quantity' => 1
                ];
                $itemsTotal += $diff;
                Log::info('createDokuPayment: Added adjustment item', ['amount' => $diff]);
            } elseif ($finalAmount < $itemsTotal) {
                // If amount is less than items (and not DP), it might be a discount or partial payment.
                // Use a flat summary to match the amount exactly.
                Log::warning('createDokuPayment: Amount mismatch, falling back to summary', [
                    'provided' => $finalAmount,
                    'items_total' => $itemsTotal
                ]);

                $lineItems = [
                    [
                        'name' => $this->sanitizeDokuString('Total Pembayaran Booking ' . ($model->booking_ref ?? $model->id)),
                        'price' => $finalAmount,
                        'quantity' => 1
                    ]
                ];
                $itemsTotal = $finalAmount;
            }

            $customerName = $user->name ?? $model->booking_data['customer_name'] ?? 'Customer';
            $customerEmail = $user->email ?? $model->booking_data['customer_email'] ?? 'customer@naqupos.com';
            $customerPhone = $user->phone ?? $model->booking_data['customer_phone'] ?? '08123456789';
            // Clean phone number (only digits and plus)
            $customerPhone = preg_replace('/[^0-9\+]/', '', $customerPhone);

            // Ensure valid email (DOKU can be picky)
            if ($customerEmail === 'no-email@naqupos.com') {
                $customerEmail = 'customer@naqupos.com';
            }

            $frontendUrl = env('FRONTEND_URL');
            if (!$frontendUrl) {
                if (str_contains(env('APP_URL'), 'localhost')) {
                    $frontendUrl = 'http://localhost:3090';
                } else {
                    $frontendUrl = 'https://zafaranuserdev.vercel.app';
                }
            }
            $callbackUrl = $frontendUrl . '/payment/success/' . $payment->payment_ref; // Use path instead of query param to avoid '?'

            if ($isDirect) {
                if ($method === 'QRIS') {
                    $body = [
                        'order' => [
                            'amount' => (int) $finalAmount,
                            'invoice_number' => $payment->payment_ref,
                        ],
                        'qris_info' => [
                            'expired_time' => 60,
                            'reusable_status' => false,
                        ],
                        'customer' => [
                            'name' => $this->sanitizeDokuString(substr($customerName, 0, 50)),
                            'email' => $customerEmail,
                        ]
                    ];
                } else {
                    $body = [
                        'order' => [
                            'amount' => (int) $finalAmount,
                            'invoice_number' => $payment->payment_ref,
                        ],
                        'virtual_account_info' => [
                            'expired_time' => 60,
                            'reusable_status' => false,
                        ],
                        'customer' => [
                            'name' => $this->sanitizeDokuString(substr($customerName, 0, 50)),
                            'email' => $customerEmail,
                        ]
                    ];
                }
            } else {
                $body = [
                    'order' => [
                        'amount' => $finalAmount,
                        'invoice_number' => $payment->payment_ref,
                        'currency' => 'IDR',
                        'session_id' => $requestId,
                        'callback_url' => $callbackUrl,
                        'line_items' => $lineItems
                    ],
                    'payment' => [
                        'payment_due_date' => 60, // 60 minutes
                    ],
                    'customer' => [
                        'name' => $this->sanitizeDokuString(substr($customerName, 0, 50)), // DOKU limit
                        'email' => $customerEmail,
                        'phone' => substr($customerPhone, 0, 20),
                    ]
                ];
            }

            // Filter Payment Methods based on Configuration
            $branchId = null;
            if ($model instanceof Booking) {
                $branchId = $model->branch_id;
            } elseif (isset($model->booking_data['branch_id'])) {
                $branchId = $model->booking_data['branch_id'];
            }

            // DOKU Payment Channel Codes (Based on DOKU API Documentation)
            // These are the actual API codes that DOKU accepts
            $dokuChannelMap = [
                // Virtual Accounts
                'VIRTUAL_ACCOUNT_BCA' => 'VIRTUAL_ACCOUNT_BCA',
                'VIRTUAL_ACCOUNT_MANDIRI' => 'VIRTUAL_ACCOUNT_BANK_MANDIRI',
                'VIRTUAL_ACCOUNT_BANK_MANDIRI' => 'VIRTUAL_ACCOUNT_BANK_MANDIRI',
                'VIRTUAL_ACCOUNT_BANK_SYARIAH_MANDIRI' => 'VIRTUAL_ACCOUNT_BANK_SYARIAH_MANDIRI',
                'VIRTUAL_ACCOUNT_DOKU' => 'VIRTUAL_ACCOUNT_DOKU',
                'VIRTUAL_ACCOUNT_BRI' => 'VIRTUAL_ACCOUNT_BRI',
                'VIRTUAL_ACCOUNT_BNI' => 'VIRTUAL_ACCOUNT_BNI',
                'VIRTUAL_ACCOUNT_PERMATA' => 'VIRTUAL_ACCOUNT_BANK_PERMATA',
                'VIRTUAL_ACCOUNT_BANK_PERMATA' => 'VIRTUAL_ACCOUNT_BANK_PERMATA',
                'VIRTUAL_ACCOUNT_CIMB' => 'VIRTUAL_ACCOUNT_BANK_CIMB',
                'VIRTUAL_ACCOUNT_BANK_CIMB' => 'VIRTUAL_ACCOUNT_BANK_CIMB',
                'VIRTUAL_ACCOUNT_DANAMON' => 'VIRTUAL_ACCOUNT_BANK_DANAMON',
                'VIRTUAL_ACCOUNT_BANK_DANAMON' => 'VIRTUAL_ACCOUNT_BANK_DANAMON',

                // E-Wallets (Only if activated in your DOKU account)
                'EMONEY_SHOPEEPAY' => 'EMONEY_SHOPEEPAY',
                'EMONEY_OVO' => 'EMONEY_OVO',
                'EMONEY_DANA' => 'EMONEY_DANA',

                // QRIS
                'QRIS' => 'QRIS',

                // Peer to Peer / Paylater
                'PEER_TO_PEER_AKULAKU' => 'PEER_TO_PEER_AKULAKU',
                'PEER_TO_PEER_KREDIVO' => 'PEER_TO_PEER_KREDIVO',
                'PEER_TO_PEER_INDODANA' => 'PEER_TO_PEER_INDODANA',

                // Credit Card (if activated)
                'CREDIT_CARD' => 'CREDIT_CARD',
            ];

            // Default active channels for sandbox (commonly available)
            $defaultActiveChannels = [
                'VIRTUAL_ACCOUNT_BCA',
                'VIRTUAL_ACCOUNT_BANK_MANDIRI',
                'VIRTUAL_ACCOUNT_BNI',
                'VIRTUAL_ACCOUNT_BRI',
                'VIRTUAL_ACCOUNT_BANK_PERMATA',
                'QRIS',
            ];

            // If method is specific (not generic 'doku'), use it. 
            // Otherwise/And also ensure we only allow configured methods.
            $query = \App\Models\PaymentMethod::where('is_active', true);

            if ($branchId) {
                $query->where(function ($q) use ($branchId) {
                    $q->where('is_global', true)
                        ->orWhereHas('branches', function ($bq) use ($branchId) {
                            $bq->where('branches.id', $branchId);
                        });
                });
            } else {
                $query->where('is_global', true);
            }

            $allowedCodes = $query->pluck('code')->toArray();
            Log::info('createDokuPayment: Allowed Payment Codes from DB', ['codes' => $allowedCodes]);

            // Remove internal codes that are not valid Doku channels
            $excludeCodes = ['CASH', 'EDC', 'DOKU', 'TITIP'];

            // Determine which payment channels to send to DOKU
            $selectedChannels = [];
            $methodUpper = strtoupper($method);

            if (isset($dokuChannelMap[$methodUpper])) {
                // Specific DOKU channel requested (e.g., VIRTUAL_ACCOUNT_BCA)
                $selectedChannels = [$dokuChannelMap[$methodUpper]];
                Log::info('createDokuPayment: Using specific channel', ['method' => $methodUpper, 'channel' => $dokuChannelMap[$methodUpper]]);
            } else if ($methodUpper === 'DOKU' || $methodUpper === 'DOKU_CHECKOUT' || $methodUpper === 'ALL' || str_starts_with($methodUpper, 'DOKU')) {
                // Generic DOKU request - use default active channels
                // Try to use channels from database if available
                $validDokuCodes = array_diff($allowedCodes, $excludeCodes);

                // Map DB codes to DOKU API codes
                $mappedCodes = [];
                foreach ($validDokuCodes as $code) {
                    $codeUpper = strtoupper($code);
                    if (isset($dokuChannelMap[$codeUpper])) {
                        $mappedCodes[] = $dokuChannelMap[$codeUpper];
                    }
                }

                if (!empty($mappedCodes)) {
                    $selectedChannels = array_unique($mappedCodes);
                    Log::info('createDokuPayment: Using mapped channels from DB', ['channels' => $selectedChannels]);
                } else {
                    // Fallback to default active channels
                    $selectedChannels = $defaultActiveChannels;
                    Log::info('createDokuPayment: Using default active channels', ['channels' => $selectedChannels]);
                }
            } else {
                // Unknown method - use default channels
                $selectedChannels = $defaultActiveChannels;
                Log::warning('createDokuPayment: Unknown method, using defaults', ['method' => $method, 'channels' => $selectedChannels]);
            }

            if (!$isDirect) {
                if (!empty($selectedChannels)) {
                    $body['payment']['payment_method_types'] = array_values($selectedChannels);
                    Log::info('createDokuPayment: Final payment_method_types', ['types' => $body['payment']['payment_method_types']]);
                }
            }

            $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            Log::info('createDokuPayment: Final JSON Body to Doku', ['json' => $jsonBody, 'targetPath' => $targetPath]);
            Log::info('createDokuPayment: Request Body Array', ['body' => $body]);

            $timestamp = Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z');
            $signature = $this->generateDokuSignature('POST', $targetPath, $jsonBody, $timestamp, $requestId);
            $digest = base64_encode(hash('sha256', $jsonBody, true));

            $client = new Client();
            $response = $client->post($this->dokuConfig['base_url'] . $targetPath, [
                'headers' => [
                    'Client-Id' => $this->dokuConfig['client_id'],
                    'Request-Id' => $requestId,
                    'Request-Timestamp' => $timestamp,
                    'Signature' => $signature,
                    'Digest' => 'SHA-256=' . $digest,
                    'Content-Type' => 'application/json',
                ],
                'body' => $jsonBody,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            Log::info('createDokuPayment: ResponseData', ['data' => $responseData]);

            // For Checkout API, payment URL is inside response.payment.url
            // For Checkout API, payment URL is inside response.payment.url
            $paymentUrl = $responseData['response']['payment']['url'] ?? $responseData['payment']['url'] ?? null;
            $invoiceNumber = $responseData['response']['order']['invoice_number'] ?? $responseData['order']['invoice_number'] ?? $payment->payment_ref;

            $vaNumber = null;
            $qrCodeUrl = null;
            $qrString = null;

            if ($isDirect) {
                if ($methodUpper === 'QRIS') {
                    $qrCodeUrl = $responseData['qris_info']['qr_image'] ?? null;
                    $qrString = $responseData['qris_info']['qr_string'] ?? null;
                } else {
                    $vaNumber = $responseData['virtual_account_info']['virtual_account_number'] ?? null;
                }
            }

            $paymentData = [
                'transaction_id' => $invoiceNumber,
                'transaction_status' => 'pending',
                'payment_url' => $paymentUrl,
                'va_number' => $vaNumber,
                'qr_code_url' => $qrCodeUrl,
                'qr_string' => $qrString,
                'bank' => ($isDirect || true) && $methodUpper !== 'QRIS' ? str_replace(['VIRTUAL_ACCOUNT_', 'VIRTUAL_ACCOUNT_BANK_'], '', $methodUpper) : null,
                'raw_response' => $responseData
            ];

            // Update payment with correct amount and payment data
            $payment->update([
                'amount' => $finalAmount, // Update with calculated amount
                'payment_data' => json_encode($paymentData)
            ]);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'payment_ref' => $payment->payment_ref,
                'method' => 'doku_checkout',
                'amount' => $finalAmount, // Return actual amount charged
                'data' => $paymentData,
            ];

        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            $response = $e->getResponse();
            $responseBody = $response ? (string) $response->getBody() : 'No response body';
            Log::error('DOKU payment failed (BadResponse): ' . $e->getMessage(), [
                'response_body' => json_decode($responseBody, true) ?: $responseBody
            ]);
            $payment->update(['status' => 'failed']);
            throw new \Exception("DOKU payment failed: " . $responseBody);
        } catch (\Exception $e) {
            Log::error('DOKU payment failed: ' . $e->getMessage());
            $payment->update(['status' => 'failed']);
            throw $e;
        }
    }

    private function generateDokuSignature($httpMethod, $targetPath, $bodyInfo, $timestamp, $requestId)
    {
        $clientId = $this->dokuConfig['client_id'];
        $secretKey = $this->dokuConfig['secret_key'];

        $digest = base64_encode(hash('sha256', $bodyInfo, true));

        // Request-Target for Doku usually only requires the path
        $requestTarget = $targetPath;

        $rawSignature = "Client-Id:" . $clientId . "\n" .
            "Request-Id:" . $requestId . "\n" .
            "Request-Timestamp:" . $timestamp . "\n" .
            "Request-Target:" . $requestTarget;

        // Digest is only included if httpMethod is not GET
        if (strtoupper($httpMethod) !== 'GET') {
            $rawSignature .= "\nDigest:" . $digest;
        }

        Log::info('Doku Raw Signature String:', ['raw' => $rawSignature]);

        return "HMACSHA256=" . base64_encode(hash_hmac('sha256', $rawSignature, $secretKey, true));
    }

    /**
     * Handle notification (Midtrans or DOKU)
     */
    public function handleNotification(Request $request)
    {
        $notification = $request->all();

        if (isset($notification['order']['invoice_number'])) {
            // It looks like DOKU
            return $this->handleDokuNotification($request);
        }

        // Midtrans Logic
        $orderId = $notification['order_id'] ?? null;
        $transactionStatus = $notification['transaction_status'] ?? null;
        $fraudStatus = $notification['fraud_status'] ?? null;

        if (!$orderId) {
            throw new \Exception('Invalid notification: missing order_id');
        }

        $payment = Payment::where('payment_ref', $orderId)->first();

        if (!$payment) {
            throw new \Exception('Payment not found: ' . $orderId);
        }

        $newStatus = 'pending';

        if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
            if ($fraudStatus == 'accept' || !$fraudStatus) {
                $newStatus = 'success';
            }
        } else if ($transactionStatus == 'pending') {
            $newStatus = 'pending';
        } else if (in_array($transactionStatus, ['deny', 'cancel'])) {
            $newStatus = 'failed';
        } else if ($transactionStatus == 'expire') {
            $newStatus = 'expired';
        }

        $updateData = ['status' => $newStatus];
        if ($newStatus === 'success') {
            $updateData['paid_at'] = Carbon::now();
        }
        $payment->update($updateData);

        if ($newStatus === 'success') {
            $this->processSuccessfulPayment($payment);
        }

        return $payment;
    }

    /**
     * Process a confirmed successful payment (Create booking, record transaction, notify)
     */
    public function processSuccessfulPayment(Payment $payment)
    {
        Log::info("processSuccessfulPayment starting for Payment ID: {$payment->id}, Status: {$payment->status}");

        if ($payment->status !== 'success') {
            Log::warning("processSuccessfulPayment early return: status is not success", ['status' => $payment->status]);
            return;
        }

        DB::beginTransaction();
        try {
            if ($payment->payment_log_id) {
                $log = $payment->paymentLog;
                Log::info("Found PaymentLog ID: " . ($log ? $log->id : 'NULL') . " with status: " . ($log ? $log->status : 'N/A'));

                if ($log && $log->status === 'pending') {
                    $log->update(['status' => 'settlement']);
                    Log::info("PaymentLog {$log->id} updated to settlement");
                    $data = $log->booking_data;

                    Log::info("Processing booking data from log", ['data_keys' => array_keys($data)]);

                    if (isset($data['is_pos']) && $data['is_pos']) {
                        Log::info("Payment is for POS, skipping booking creation");
                        DB::commit();
                        return;
                    }

                    $amountPaid = (float) $payment->amount;
                    $totalPrice = (float) $data['total_price'];
                    $paymentStatus = ($data['payment_type'] === 'full_payment' || $amountPaid >= $totalPrice) ? 'paid' : 'partial';

                    $items = isset($data['items']) ? $data['items'] : (isset($data['service_id']) ? [$data] : []);
                    $firstBooking = null;

                    // Consolidated notes logic
                    $allNotes = [];
                    $hasConflict = false;
                    foreach ($items as $item) {
                        if (!empty($item['notes'])) {
                            $allNotes[] = $item['notes'];
                        }

                        // Check for conflicts for this specific item
                        $conflict = Booking::where('booking_date', $item['booking_date'])
                            ->where('therapist_id', $item['therapist_id'])
                            ->where('start_time', '<', $item['end_time'])
                            ->where('end_time', '>', $item['start_time'])
                            ->whereIn('status', ['confirmed', 'in_progress'])
                            ->exists();

                        if (!$conflict && isset($item['room_id'])) {
                            $room = Room::find($item['room_id']);
                            if ($room) {
                                $existingCount = Booking::where('booking_date', $item['booking_date'])
                                    ->where('room_id', $item['room_id'])
                                    ->where('start_time', '<', $item['end_time'])
                                    ->where('end_time', '>', $item['start_time'])
                                    ->whereIn('status', ['confirmed', 'in_progress'])
                                    ->count();
                                if ($existingCount >= ($room->capacity * max(1, $room->quantity ?? 1))) {
                                    $conflict = true;
                                }
                            }
                        }

                        if ($conflict) {
                            $hasConflict = true;
                        }
                    }

                    $consolidatedNotes = implode(" | ", array_unique($allNotes));
                    if ($hasConflict) {
                        $consolidatedNotes .= ($consolidatedNotes ? " " : "") . "[Overbooked/Conflict]";
                    }

                    $firstItem = !empty($items) ? $items[0] : null;
                    $booking = Booking::create([
                        'user_id' => $data['user_id'] ?? null,
                        'branch_id' => $data['branch_id'] ?? null,
                        'service_id' => $firstItem ? ($firstItem['service_id'] ?? null) : null,
                        'therapist_id' => $firstItem ? ($firstItem['therapist_id'] ?? null) : null,
                        'room_id' => $firstItem ? ($firstItem['room_id'] ?? null) : null,
                        'booking_date' => $firstItem ? $firstItem['booking_date'] : ($data['booking_date'] ?? Carbon::now()->toDateString()),
                        'start_time' => $firstItem ? $firstItem['start_time'] : null,
                        'end_time' => $firstItem ? $firstItem['end_time'] : null,
                        'duration' => $data['duration'] ?? ($firstItem['duration'] ?? 0),
                        'service_price' => $data['service_price'] ?? ($firstItem['service_price'] ?? 0),
                        'room_charge' => $data['room_charge'] ?? ($firstItem['room_charge'] ?? 0),
                        'product_total' => $data['product_total'] ?? 0,
                        'total_price' => $data['total_price'] ?? ($firstItem['total_price'] ?? 0),
                        'promo_code' => $data['promo_code'] ?? null,
                        'discount_amount' => $data['discount_amount'] ?? 0,
                        'service_charge_amount' => $data['service_charge_amount'] ?? 0,
                        'tax_amount' => $data['tax_amount'] ?? 0,
                        'status' => $hasConflict ? 'pending' : 'confirmed',
                        'payment_status' => $paymentStatus,
                        'confirmed_at' => $hasConflict ? null : Carbon::now(),
                        'guest_name' => $firstItem ? ($firstItem['guest_name'] ?? ($data['customer_name'] ?? null)) : ($data['customer_name'] ?? null),
                        'guest_phone' => $firstItem ? ($firstItem['guest_phone'] ?? ($data['customer_phone'] ?? null)) : ($data['customer_phone'] ?? null),
                        'guest_type' => $firstItem ? ($firstItem['guest_type'] ?? 'dewasa') : 'dewasa',
                        'guest_age' => $firstItem ? ($firstItem['guest_age'] ?? null) : null,
                        'notes' => $consolidatedNotes,
                    ]);

                    // Increment Promo Usage
                    if (!empty($data['promo_code'])) {
                        $promo = \App\Models\Promo::where('code', $data['promo_code'])->first();
                        if ($promo) {
                            $promo->incrementUsage();
                        }
                    }

                    $firstBooking = $booking;

                    // Create Booking Items for all entries
                    foreach ($items as $index => $item) {
                        \App\Models\BookingItem::create([
                            'booking_id' => $booking->id,
                            'service_id' => $item['service_id'] ?? null,
                            'therapist_id' => $item['therapist_id'] ?? null,
                            'room_id' => $item['room_id'] ?? null,
                            'price' => $item['service_price'],
                            'room_charge' => $item['room_charge'] ?? 0,
                            'duration' => $item['duration'],
                            'start_time' => $item['start_time'],
                            'end_time' => $item['end_time'],
                            'guest_name' => $item['guest_name'] ?? null,
                            'guest_phone' => $item['guest_phone'] ?? null,
                            'guest_type' => $item['guest_type'] ?? 'dewasa',
                            'guest_age' => $item['guest_age'] ?? null,
                        ]);

                        // Notify Staff for each specific item as they might have different therapists
                        $therapist = \App\Models\Therapist::find($item['therapist_id'] ?? 0);
                        if ($therapist && $therapist->phone) {
                            try {
                                // Create a mock object for the notification service
                                $mockBooking = (object) [
                                    'booking_date' => $booking->booking_date,
                                    'start_time' => $item['start_time'],
                                    'user' => $booking->user,
                                    'therapist' => $therapist,
                                    'service' => (object) ['name' => $item['service_name'] ?? ($booking->service->name ?? 'Treatment')]
                                ];
                                $this->whatsappService->sendStaffBookingNotification($therapist->phone, $mockBooking);
                            } catch (\Exception $e) {
                                Log::error("Failed to notify staff via WhatsApp for item $index: " . $e->getMessage());
                            }

                            if ($therapist->email) {
                                try {
                                    $this->emailService->sendStaffBookingNotification($therapist->email, $mockBooking);
                                } catch (\Exception $e) {
                                    Log::error("Failed to notify staff via Email for item $index: " . $e->getMessage());
                                }
                            }
                        }
                    }

                    AuditLog::log('payment', 'Booking', "Online payment confirmed via DOKU/Midtrans for REF: {$booking->booking_ref}");

                    // Notify Customer (once for the whole booking)
                    $phone = $booking->user ? $booking->user->phone : ($data['customer_phone'] ?? null);
                    if ($phone) {
                        try {
                            $this->whatsappService->sendBookingSuccess($phone, [
                                'customer_name' => $booking->user->name ?? ($data['customer_name'] ?? 'Pelanggan'),
                                'branch_name' => $booking->branch->name ?? 'Naqupos Spa',
                                'date' => \Carbon\Carbon::parse($booking->booking_date)->format('d F Y'),
                                'time' => substr($booking->start_time, 0, 5) . ' WIB',
                                'service' => ($items[0]['service_name'] ?? $booking->service->name ?? 'Treatment') . (count($items) > 1 ? ' (' . count($items) . ' Guests)' : ''),
                                'location' => $booking->branch->address ?? ($booking->branch->name ?? '')
                            ]);
                        } catch (\Exception $e) {
                            Log::error("Failed to notify customer: " . $e->getMessage());
                        }
                    }

                    // Link payment to the first booking created
                    if ($firstBooking) {
                        $payment->update(['booking_id' => $firstBooking->id]);
                        $this->recordTransaction($payment, $firstBooking, $data);
                    }

                    DB::commit();
                    return;
                }
            } else if ($payment->booking_id) {
                $booking = $payment->booking;
                if ($booking) {
                    $totalPaid = Payment::where('booking_id', $booking->id)
                        ->where('status', 'success')
                        ->sum('amount');

                    $updateData = [];

                    // Update payment status
                    if ($totalPaid >= $booking->total_price) {
                        $updateData['payment_status'] = 'paid';
                    } else {
                        $updateData['payment_status'] = 'partial';
                    }

                    // Update booking status to confirmed if currently pending_payment
                    if ($booking->status === 'pending_payment') {
                        $updateData['status'] = 'confirmed';
                        $updateData['confirmed_at'] = Carbon::now();
                    }

                    $booking->update($updateData);

                    $this->recordTransaction($payment, $booking, [
                        'items' => [
                            [
                                'name' => 'Payment for Booking #' . $booking->booking_ref,
                                'price' => $payment->amount,
                                'quantity' => 1,
                                'type' => 'service',
                                'service_id' => $booking->service_id
                            ]
                        ]
                    ]);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('processSuccessfulPayment Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function handleDokuNotification(Request $request)
    {
        $notification = $request->all();
        $invoiceNumber = $notification['order']['invoice_number'];
        $transactionStatus = $notification['transaction']['status']; // SUCCESS or FAILED

        // 1. Verify Signature
        if (!$this->verifyDokuNotificationSignature($request)) {
            Log::warning('DOKU Signature Mismatch for invoice: ' . $invoiceNumber);
            throw new \Exception('Invalid Signature');
        }

        $payment = Payment::where('payment_ref', $invoiceNumber)->first();

        if (!$payment) {
            throw new \Exception('Payment not found: ' . $invoiceNumber);
        }

        // 2. Idempotency Check: Don't process if already in final state
        if (in_array($payment->status, ['success', 'failed', 'expired'])) {
            return $payment;
        }

        $newStatus = 'pending';
        if ($transactionStatus === 'SUCCESS') {
            // 3. Amount Validation
            $notifAmount = (int) ($notification['order']['amount'] ?? 0);
            // Allow small float tolerance if needed, but simple int check for now
            if ($notifAmount != (int) $payment->amount) {
                Log::warning("DOKU Amount Mismatch for {$invoiceNumber}: " . $notifAmount . " vs " . $payment->amount);
                // Do not convert to paid
                return $payment;
            }
            $newStatus = 'success';
        } elseif ($transactionStatus === 'FAILED') {
            $newStatus = 'failed';
        } elseif ($transactionStatus === 'EXPIRED') {
            $newStatus = 'expired';
        }

        Log::info("DOKU Callback received for invoice {$invoiceNumber} with status: {$transactionStatus} -> Mapped to: {$newStatus}");

        DB::beginTransaction();
        try {
            $updateData = ['status' => $newStatus];
            if ($newStatus === 'success') {
                $updateData['paid_at'] = Carbon::now();
            }
            $payment->update($updateData);

            if ($newStatus === 'success') {
                $this->processSuccessfulPayment($payment);
            } elseif (in_array($newStatus, ['failed', 'expired'])) {
                $this->handleFailedPayment($payment, $newStatus);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $payment;
    }

    private function verifyDokuNotificationSignature(Request $request)
    {
        $clientId = $request->header('Client-Id');
        $requestId = $request->header('Request-Id');
        $timestamp = $request->header('Request-Timestamp');
        $signature = $request->header('Signature');
        $body = $request->getContent(); // Raw body

        if (!$clientId || !$requestId || !$timestamp || !$signature) {
            return false;
        }

        // 0. Timestamp Freshness Check (prevent replay attacks)
        try {
            // Support ISO 8601
            $reqTime = Carbon::parse($timestamp);
            if (Carbon::now()->diffInMinutes($reqTime) > 5) { // 5 mins tolerance
                Log::warning("DOKU Notification Timestamp stale: $timestamp");
                return false;
            }
        } catch (\Exception $e) {
            Log::warning("DOKU Invalid Timestamp: $timestamp");
            return false;
        }

        // DOKU Signature Pattern: HMACSHA256(Client-Id + Request-Id + Request-Timestamp + Request-Target + Digest, SecretKey)
        // Note: For Notification, Request-Target is the path e.g. /api/v1/payments/callback
        // BUT DOKU documentation usually specifies format: "Client-Id:" . $clientId . "\n" ...

        // Let's use the standard format for Response/Notification Signature
        $digest = base64_encode(hash('sha256', $body, true));

        // Path logic: DOKU might send full URL or path. Usually path.
        // Important: Ensure the path matches exactly what DOKU hits. 
        // We can get it from request
        $path = $request->getRequestUri();

        $rawSignature = "Client-Id:" . $clientId . "\n" .
            "Request-Id:" . $requestId . "\n" .
            "Request-Timestamp:" . $timestamp . "\n" .
            "Request-Target:" . $path . "\n" .
            "Digest:" . $digest;

        $calculatedSignature = "HMACSHA256=" . base64_encode(hash_hmac('sha256', $rawSignature, $this->dokuConfig['secret_key'], true));

        // Log for debugging (remove in prod if secure)
        // Log::info("DOKU Sig Check: " . $calculatedSignature . " vs " . $signature);

        return hash_equals($calculatedSignature, $signature);
    }

    /**
     * Mock: Simulate payment success (for testing)
     */
    public function mockConfirmPayment($paymentId)
    {
        $payment = Payment::findOrFail($paymentId);

        // Mock request object for Midtrans format (internal mock)
        $request = new Request([], [], [
            'order_id' => $payment->payment_ref,
            'transaction_status' => 'settlement',
        ]);

        // Populate request data so $request->all() works
        $request->merge([
            'order_id' => $payment->payment_ref,
            'transaction_status' => 'settlement',
        ]);

        return $this->handleNotification($request);
    }
    /**
     * Check DOKU Payment Status (Proactive)
     */
    public function checkDokuStatus($payment)
    {
        Log::info('checkDokuStatus called', [
            'payment_id' => $payment->id,
            'payment_ref' => $payment->payment_ref,
            'isMockMode' => $this->isMockMode,
            'hasDokuConfig' => !empty($this->dokuConfig)
        ]);

        if ($this->isMockMode || !$this->dokuConfig) {
            Log::warning('checkDokuStatus skipped', [
                'reason' => $this->isMockMode ? 'Mock mode' : 'No Doku config'
            ]);
            return $payment;
        }

        try {
            $invoiceNumber = $payment->payment_ref;
            $requestId = Str::uuid()->toString();
            $targetPath = '/orders/v1/status/' . $invoiceNumber;
            $timestamp = Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z');

            $signature = $this->generateDokuSignature('GET', $targetPath, "", $timestamp, $requestId);

            $url = $this->dokuConfig['base_url'] . $targetPath;
            Log::info('Calling Doku Status API', [
                'url' => $url,
                'invoice' => $invoiceNumber
            ]);

            $client = new Client();
            $response = $client->get($url, [
                'headers' => [
                    'Client-Id' => $this->dokuConfig['client_id'],
                    'Request-Id' => $requestId,
                    'Request-Timestamp' => $timestamp,
                    'Signature' => $signature,
                ],
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            Log::info('--- DOKU STATUS RESPONSE START ---', [
                'payment_ref' => $invoiceNumber,
                'response' => $responseData
            ]);

            // Persist the status check response to payment_data for debugging history
            $currentData = json_decode($payment->payment_data, true) ?: [];
            $currentData['last_status_check'] = [
                'at' => Carbon::now()->toDateTimeString(),
                'response' => $responseData
            ];
            $payment->update(['payment_data' => json_encode($currentData)]);

            if (isset($responseData['transaction']['status'])) {
                $status = $responseData['transaction']['status'];
                $newStatus = $payment->status;

                if ($status === 'SUCCESS') {
                    $newStatus = 'success';
                } elseif ($status === 'FAILED') {
                    $newStatus = 'failed';
                } elseif ($status === 'EXPIRED') {
                    $newStatus = 'expired';
                }

                if ($newStatus !== $payment->status) {
                    Log::info('Updating payment status', [
                        'payment_id' => $payment->id,
                        'old_status' => $payment->status,
                        'new_status' => $newStatus
                    ]);

                    $updateData = ['status' => $newStatus];
                    if ($newStatus === 'success') {
                        $updateData['paid_at'] = Carbon::now();
                    }
                    $payment->update($updateData);

                    if ($newStatus === 'success') {
                        Log::info('Calling processSuccessfulPayment (status changed)', ['payment_id' => $payment->id]);
                        $this->processSuccessfulPayment($payment);
                    } elseif (in_array($newStatus, ['failed', 'expired'])) {
                        $this->handleFailedPayment($payment, $newStatus);
                    }
                } elseif ($newStatus === 'success' && !$payment->booking_id && $payment->payment_log_id) {
                    // Reconciliation: status is already success but booking not created
                    Log::info('Calling processSuccessfulPayment (reconciliation)', ['payment_id' => $payment->id]);
                    $this->processSuccessfulPayment($payment);
                }
            }

            return $payment->fresh();

        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            $response = $e->getResponse();
            $body = $response ? (string) $response->getBody() : 'No body';
            Log::error('DOKU Check Status failed (BadResponse): ' . $e->getMessage(), [
                'response_body' => json_decode($body, true) ?: $body
            ]);
            return $payment;
        } catch (\Exception $e) {
            Log::error('DOKU Check Status failed: ' . $e->getMessage());
            return $payment;
        }
    }


    /**
     * Record Transaction for Reporting
     */
    private function recordTransaction($payment, $booking, $data = [])
    {
        try {
            // Avoid duplicate transaction for same Booking if full payment?
            // But partial payments are valid transactions too.
            // Check if transaction exists for this specific payment reference?
            // Actually transactions table usually links to booking, not payment.
            // But here we want to record the MONEY received.

            $cashierId = null;
            // Online payments don't have a cashier, but the DB may require it. 
            // Try to find the first admin/owner as a default.
            $defaultCashier = \App\Models\User::whereIn('role', ['admin', 'owner', 'super_admin'])->first();
            if ($defaultCashier) {
                $cashierId = $defaultCashier->id;
            }

            $subtotal = $payment->amount;
            $discount = 0;
            $serviceCharge = 0;
            $tax = 0;

            if ($payment->payment_type === 'full_payment') {
                $subtotal = $data['service_price'] ?? ($data['price'] ?? $payment->amount);
                $discount = $data['discount_amount'] ?? 0;
                $serviceCharge = $data['service_charge_amount'] ?? 0;
                $tax = $data['tax_amount'] ?? 0;
            }

            $transaction = Transaction::create([
                'branch_id' => $booking->branch_id,
                'booking_id' => $booking->id,
                'cashier_id' => $cashierId,
                'type' => 'booking',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'service_charge' => $serviceCharge,
                'tax' => $tax,
                'total' => $payment->amount,
                'payment_method' => $payment->payment_method,
                'cash_received' => $payment->amount,
                'change_amount' => 0,
                'notes' => 'Online Payment (' . $payment->payment_method . ') - Ref: ' . $payment->payment_ref,
                'transaction_date' => Carbon::now(),
                'status' => 'completed' // Assuming paid = completed transaction
            ]);

            // Create Transaction Items
            $items = $data['items'] ?? [];
            if (empty($items) && $booking) {
                // If no items provided, default to the service in booking
                $items = [
                    [
                        'type' => 'service',
                        'service_id' => $booking->service_id,
                        'therapist_id' => $booking->therapist_id,
                        'product_id' => null,
                        'quantity' => 1,
                        'price' => $payment->amount,
                        'name' => 'Service Payment'
                    ]
                ];
            }

            foreach ($items as $item) {
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'type' => $item['type'] ?? ($item['product_id'] ?? false ? 'product' : 'service'),
                    'service_id' => $item['service_id'] ?? ($item['type'] === 'service' ? ($booking->service_id ?? null) : null),
                    'product_id' => $item['product_id'] ?? null,
                    'variant_id' => $item['variant_id'] ?? null,
                    'therapist_id' => $item['therapist_id'] ?? ($booking->therapist_id ?? null),
                    'quantity' => $item['quantity'] ?? 1,
                    'price' => $item['price'] ?? 0,
                    'subtotal' => ($item['price'] ?? 0) * ($item['quantity'] ?? 1),
                    'notes' => $item['name'] ?? null,
                ]);
            }

            Log::info('Transaction recorded for Payment: ' . $payment->id);

        } catch (\Exception $e) {
            Log::error('Failed to record transaction for payment ' . $payment->id . ': ' . $e->getMessage());
            // Do not fail the whole payment process just because reporting failed
        }
    }

    /**
     * Handle failed or expired payment (Release slots)
     */
    private function handleFailedPayment(Payment $payment, $status)
    {
        Log::info("handleFailedPayment called for payment {$payment->id} with status {$status}");

        DB::beginTransaction();
        try {
            // 1. Release PaymentLog if pending
            if ($payment->payment_log_id) {
                $log = $payment->paymentLog;
                if ($log && $log->status === 'pending') {
                    $log->update(['status' => $status === 'expired' ? 'expire' : 'cancel']);
                    Log::info("PaymentLog {$log->id} updated to {$log->status}");
                }
            }

            // 2. Cancel Booking if exists and in a cancellable state
            if ($payment->booking_id) {
                $booking = $payment->booking;
                if ($booking && in_array($booking->status, ['pending_payment', 'awaiting_payment'])) {
                    $booking->update([
                        'status' => 'cancelled',
                        'cancelled_at' => Carbon::now(),
                        'cancellation_reason' => $status === 'expired' ? 'Pembayaran kadaluarsa' : 'Pembayaran gagal'
                    ]);
                    Log::info("Booking {$booking->id} cancelled due to payment {$status}");
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to handle failed payment: " . $e->getMessage());
        }
    }

    /**
     * Sanitize string for DOKU (allowed only a-z A-Z 0-9 . - / + , = _ : ' @ % and space)
     */
    private function sanitizeDokuString($string)
    {
        if (!$string)
            return '';
        return preg_replace('/[^a-zA-Z0-9\.\-\/\+\,\=\_\:\' \@\%]/', '', $string);
    }
}
