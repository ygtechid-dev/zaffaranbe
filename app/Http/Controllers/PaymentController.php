<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /** @var PaymentService */
    protected $paymentService;

    /** @var WhatsAppService */
    protected $whatsappService;

    public function __construct(PaymentService $paymentService, WhatsAppService $whatsappService)
    {
        $this->paymentService = $paymentService;
        $this->whatsappService = $whatsappService;
    }

    /**
     * Get payment configuration (DP amount, etc.)
     */
    public function getConfig(Request $request)
    {
        $query = \App\Models\PaymentMethod::where('is_active', true)
            ->where('is_online', true);

        if ($request->has('branch_id')) {
            $branchId = $request->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->where('is_global', true)
                    ->orWhereHas('branches', function ($bq) use ($branchId) {
                        $bq->where('branches.id', $branchId);
                    });
            });
        } else {
            $query->where('is_global', true);
        }

        $methods = $query->orderBy('sort_order')->get()->map(function ($m) {
            return [
                'id' => $m->code,
                'name' => $m->name,
                'description' => $m->description,
                'icon' => $m->icon ?? 'credit-card',
                'type' => $m->type,
                'fee' => $m->fee,
                'enabled' => true,
            ];
        });

        $dpAmount = 50000;
        $dpPercentage = null;

        // Try to fetch settings from CompanySettings (Global/Branch Specific)
        $settings = null;
        if ($request->has('branch_id')) {
            $settings = \App\Models\CompanySettings::where('branch_id', $request->branch_id)->first();
        }

        // Fallback to global settings if branch specific not found
        if (!$settings) {
            $settings = \App\Models\CompanySettings::whereNull('branch_id')->first();
        }

        if ($settings && isset($settings->min_dp)) {
            $dpAmount = (int) $settings->min_dp;

            // Check if DP is per guest
            if (isset($settings->min_dp_type) && $settings->min_dp_type === 'per_guest') {
                $guestCount = 1;

                // If booking_id is provided, get guest count from booking
                if ($request->has('booking_id')) {
                    $booking = \App\Models\Booking::find($request->booking_id);
                    if ($booking) {
                        $guestCount = max(1, (int) ($booking->guest_count ?: 1));
                    }
                } else {
                    // Otherwise check for guest_count in request
                    $guestCount = max(1, (int) $request->input('guest_count', 1));
                }

                $dpAmount = $dpAmount * $guestCount;
            }
        } elseif ($request->has('branch_id')) {
            // Fallback to legacy BranchPaymentConfig
            $config = \App\Models\BranchPaymentConfig::where('branch_id', $request->branch_id)->first();
            if ($config) {
                if ($config->down_payment_amount > 0) {
                    $dpAmount = (int) $config->down_payment_amount;
                }
                $dpPercentage = (float) $config->down_payment_percentage;
            }
        }

        $totalAmount = $request->has('amount') ? (float) $request->amount : null;

        $paymentTypes = [];

        // Always include Full Payment
        $paymentTypes[] = [
            'id' => 'full_payment',
            'name' => 'Pembayaran Penuh',
            'description' => 'Bayar lunas seluruh total biaya sekarang.',
            'amount' => $totalAmount ?: 0
        ];

        // Include DP only if total amount is not less than DP amount (or if amount not provided)
        if ($totalAmount === null || $totalAmount >= $dpAmount) {
            $paymentTypes[] = [
                'id' => 'down_payment',
                'name' => 'Down Payment (DP)',
                'description' => 'Bayar sebagian untuk konfirmasi reservasi.',
                'amount' => $dpAmount
            ];
        }

        // Respect toggle flags: return 0 if the feature is disabled
        $taxPercentage = ($settings && ($settings->is_tax_enabled ?? false))
            ? ($settings->tax_percentage ?? 0)
            : 0;

        $serviceChargePercentage = ($settings && ($settings->is_service_charge_enabled ?? false))
            ? ($settings->service_charge_percentage ?? 0)
            : 0;

        return response()->json([
            'dp_amount' => $dpAmount,
            'dp_percentage' => $dpPercentage,
            'min_dp' => $dpAmount,
            'payment_types' => $paymentTypes,
            'payment_methods' => $methods,
            'tax_percentage' => $taxPercentage,
            'service_charge_percentage' => $serviceChargePercentage,
        ]);
    }

    /**
     * Initiate payment for a booking
     */
    public function initiate(Request $request)
    {
        $this->validate($request, [
            'booking_id' => 'required_without_all:payment_log_id,items',
            'payment_log_id' => 'required_without_all:booking_id,items',
            'items' => 'required_without_all:booking_id,payment_log_id|array',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required',
            'payment_type' => 'required',
        ]);

        $booking = null;
        $paymentLog = null;

        if ($request->booking_id) {
            if ($request->booking_id >= 99900000) {
                $logId = $request->booking_id - 99900000;
                $paymentLog = \App\Models\PaymentLog::findOrFail($logId);
            } else {
                $booking = Booking::findOrFail($request->booking_id);
            }
        } elseif ($request->payment_log_id) {
            $paymentLog = \App\Models\PaymentLog::findOrFail($request->payment_log_id);
        } elseif ($request->items) {
            $paymentLog = \App\Models\PaymentLog::create([
                'booking_data' => array_merge($request->all(), ['is_pos' => true]),
                'status' => 'pending',
                'expired_at' => \Carbon\Carbon::now()->addHours(1)
            ]);
        }

        try {
            $model = $booking ?: $paymentLog;
            $amount = (float) $request->amount;
            $paymentType = $request->payment_type;

            // Validation: check if service price < DP amount for down_payment type
            if ($paymentType === 'down_payment') {
                $totalPrice = 0;
                $branchId = null;
                if ($booking) {
                    $totalPrice = (float) $booking->total_price;
                    $branchId = $booking->branch_id;
                } elseif ($paymentLog) {
                    $totalPrice = (float) ($paymentLog->booking_data['total_price'] ?? 0);
                    $branchId = $paymentLog->booking_data['branch_id'] ?? null;
                }

                // Get DP amount from settings
                $dpAmount = 50000; // Default
                $settings = \App\Models\CompanySettings::where('branch_id', $branchId)->first();
                if (!$settings) {
                    $settings = \App\Models\CompanySettings::whereNull('branch_id')->first();
                }

                if ($settings && isset($settings->min_dp)) {
                    $dpAmount = (int) $settings->min_dp;

                    if (isset($settings->min_dp_type) && $settings->min_dp_type === 'per_guest') {
                        $guestCount = 1;
                        if ($booking) {
                            $guestCount = max(1, (int) ($booking->guest_count ?: 1));
                        } elseif ($paymentLog) {
                            $guestCount = max(1, (int) ($paymentLog->booking_data['guest_count'] ?? 1));
                        }
                        $dpAmount = $dpAmount * $guestCount;
                    }
                }

                if ($totalPrice < $dpAmount) {
                    // Force full payment if total price is less than DP amount
                    $paymentType = 'full_payment';
                    $amount = $totalPrice;
                    Log::info('Forcing full payment because total price is less than DP amount', [
                        'total_price' => $totalPrice,
                        'dp_amount' => $dpAmount
                    ]);
                }
            }

            $result = $this->paymentService->createPaymentLink(
                $model,
                $amount,
                $request->payment_method,
                $paymentType
            );

            // Send Notification (Confirmation)
            if ($result['success'] && isset($result['data']['payment_url'])) {
                $branchId = $request->branch_id ?? ($booking ? $booking->branch_id : ($paymentLog->booking_data['branch_id'] ?? null));
                $branch = $branchId ? \App\Models\Branch::find($branchId) : null;
                $user = auth()->user();
                $phone = $user->phone ?? ($paymentLog->booking_data['customer_phone'] ?? null);

                if ($phone) {
                    $finalPaymentAmount = (int) $amount;

                    // Fallback in case property is uninitialized for any reason
                    if (!isset($this->whatsappService)) {
                        $this->whatsappService = app(WhatsAppService::class);
                    }

                    $this->whatsappService->sendCustomerNotification($phone, 'confirmation', [
                        'customer' => $user,
                        'customer_name' => $user->name ?? ($paymentLog->booking_data['customer_name'] ?? 'Pelanggan'),
                        'booking' => $booking,
                        'branch' => $branch,
                        'payment_link' => $result['data']['payment_url'],
                        'dp_amount' => $finalPaymentAmount
                    ], $branchId);
                }
            }

            if ($paymentLog) {
                $result['payment_log_id'] = $paymentLog->id;
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Payment initiation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Payment initiation failed', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get payment status
     */
    public function status($id)
    {
        $payment = Payment::where('id', $id)->first();

        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        $user = auth()->user();
        $isAuthorized = false;

        if (in_array($user->role, ['admin', 'cashier', 'staff', 'owner', 'super_admin'])) {
            $isAuthorized = true;
        } elseif ($payment->booking_id) {
            $booking = Booking::find($payment->booking_id);
            if ($booking && $booking->user_id === $user->id) {
                $isAuthorized = true;
            }
        } elseif ($payment->payment_log_id) {
            $log = \App\Models\PaymentLog::find($payment->payment_log_id);
            if ($log && isset($log->booking_data['user_id']) && $log->booking_data['user_id'] == $user->id) {
                $isAuthorized = true;
            }
        }

        if (!$isAuthorized) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        Log::info('Payment Status Check', [
            'payment_id' => $id,
            'current_status' => $payment->status,
            'payment_method' => $payment->payment_method,
            'gateway' => env('PAYMENT_GATEWAY')
        ]);

        // Check if payment might be Doku related or using common methods handled by Doku
        $isDokuPayment = str_contains($payment->payment_method, 'doku') ||
            env('PAYMENT_GATEWAY') === 'doku' ||
            in_array($payment->payment_method, ['virtual_account', 'qris', 'bank_transfer']);

        if ($payment->status === 'pending' && $isDokuPayment) {
            Log::info('Calling checkDokuStatus for payment', ['payment_id' => $id]);
            $payment = $this->paymentService->checkDokuStatus($payment);
            // Refresh payment from database to get latest status
            $payment = $payment->fresh();
            Log::info('After checkDokuStatus', [
                'payment_id' => $id,
                'new_status' => $payment->status
            ]);
        }

        $paymentData = $payment->payment_data ?? [];

        // Get booking payment status if booking exists
        $bookingPaymentStatus = null;
        if ($payment->booking_id) {
            $booking = Booking::find($payment->booking_id);
            if ($booking) {
                $bookingPaymentStatus = $booking->payment_status;
                Log::info('Booking payment status', [
                    'booking_id' => $booking->id,
                    'payment_status' => $bookingPaymentStatus
                ]);
            }
        }

        $response = [
            'payment_id' => $payment->id,
            'payment_ref' => $payment->payment_ref,
            'status' => $payment->status,
            'payment_status' => $bookingPaymentStatus, // Add booking payment status
            'amount' => $payment->amount,
            'method' => $payment->payment_method,
            'type' => $payment->payment_type,
            'data' => $paymentData,
            'created_at' => $payment->created_at,
        ];

        Log::info('Payment Status Response', $response);

        return response()->json($response);
    }

    /**
     * Mock: Confirm payment (for testing without Midtrans)
     */
    public function mockConfirm(Request $request)
    {
        $this->validate($request, [
            'payment_id' => 'required|exists:payments,id',
        ]);

        try {
            $payment = $this->paymentService->mockConfirmPayment($request->payment_id);

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed successfully',
                'payment' => [
                    'id' => $payment->id,
                    'status' => $payment->status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Payment gateway callback (Midtrans webhook)
     */
    public function callback(Request $request)
    {
        try {
            $this->paymentService->handleNotification($request);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Payment callback failed: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }
}
