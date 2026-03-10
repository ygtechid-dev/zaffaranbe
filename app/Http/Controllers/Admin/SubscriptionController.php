<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use GuzzleHttp\Client;

class SubscriptionController extends Controller
{
    /**
     * ADMIN: Get aggregated stats for CRM dashboard.
     */
    public function overview()
    {
        $totalActiveTenants = Subscription::active()->count();
        $totalProTenants = Subscription::pro()->active()->count();
        
        // Calculate MRR (Monthly Recurring Revenue)
        $monthlyRevenue = Subscription::active()
            ->where('interval', 'monthly')
            ->sum('amount');
            
        $yearlyRevenue = Subscription::active()
            ->where('interval', 'yearly')
            ->sum('amount');
            
        $mrr = $monthlyRevenue + ($yearlyRevenue / 12);

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_tenants' => Subscription::count(),
                'active_tenants' => $totalActiveTenants,
                'pro_tenants' => $totalProTenants,
                'mrr' => (int) $mrr,
            ]
        ]);
    }

    /**
     * Get all subscription plans (config).
     * Accessible by all authenticated users.
     */
    public function getPlans()
    {
        $plans = SubscriptionPlan::where('is_active', true)->orderBy('price_monthly')->get();

        // If no plans in DB, return defaults
        if ($plans->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'data' => SubscriptionPlan::getDefaultPlans(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $plans,
        ]);
    }

    /**
     * Update subscription plans (super_admin only).
     */
    public function updatePlans(Request $request)
    {
        $user = $request->user();
        $role = strtolower($user->role);

        if (!in_array($role, ['super_admin', 'owner'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $plans = $request->input('plans', []);

        foreach ($plans as $planData) {
            SubscriptionPlan::updateOrCreate(
                ['plan_key' => $planData['id'] ?? $planData['plan_key'] ?? ''],
                [
                    'name' => $planData['name'],
                    'description' => $planData['description'] ?? null,
                    'price_monthly' => $planData['priceMonthly'] ?? $planData['price_monthly'] ?? 0,
                    'price_yearly' => $planData['priceYearly'] ?? $planData['price_yearly'] ?? 0,
                    'features' => $planData['features'] ?? [],
                    'menu_permissions' => $planData['menu_permissions'] ?? null,
                    'is_popular' => $planData['isPopular'] ?? $planData['is_popular'] ?? false,
                ]
            );
        }

        AuditLog::log('update', 'Subscription', 'Updated subscription plans configuration');

        return response()->json([
            'status' => 'success',
            'message' => 'Plans updated successfully',
        ]);
    }

    /**
     * Get subscription status for current user's branch.
     */
    public function getStatus(Request $request)
    {
        $user = $request->user();
        $role = strtolower($user->role);

        // Super admin: return all subscriptions
        if (in_array($role, ['super_admin', 'owner'])) {
            $subscriptions = Subscription::with('branch')->get()->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'branch_id' => $sub->branch_id,
                    'branch_name' => $sub->branch?->name ?? '-',
                    'plan_key' => $sub->plan_key,
                    'status' => $sub->status,
                    'interval' => $sub->interval,
                    'amount' => $sub->amount,
                    'payment_method' => $sub->payment_method,
                    'starts_at' => $sub->starts_at,
                    'expires_at' => $sub->expires_at,
                    'is_active' => $sub->isActive(),
                    'is_pro' => $sub->isPro(),
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $subscriptions,
            ]);
        }

        // Branch admin / cashier: find their branch subscription
        $branchId = $user->branch_id ?? null;

        // Try to find branch by name if branch_id not set
        if (!$branchId && $user->branch) {
            $branch = Branch::where('name', $user->branch)->first();
            $branchId = $branch?->id;
        }

        if (!$branchId) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'plan_key' => 'starter',
                    'status' => 'active',
                    'is_active' => true,
                    'is_pro' => false,
                ],
            ]);
        }

        $subscription = Subscription::where('branch_id', $branchId)
            ->where('status', 'active')
            ->orderByDesc('expires_at')
            ->first();

        if (!$subscription) {
            // Auto-create starter subscription
            $subscription = Subscription::firstOrCreate(
                ['branch_id' => $branchId, 'plan_key' => 'starter'],
                [
                    'interval' => 'lifetime',
                    'status' => 'active',
                    'amount' => 0,
                    'starts_at' => Carbon::now(),
                    'expires_at' => null,
                ]
            );
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $subscription->id,
                'branch_id' => $subscription->branch_id,
                'plan_key' => $subscription->plan_key,
                'status' => $subscription->status,
                'interval' => $subscription->interval,
                'amount' => $subscription->amount,
                'starts_at' => $subscription->starts_at,
                'expires_at' => $subscription->expires_at,
                'is_active' => $subscription->isActive(),
                'is_pro' => $subscription->isPro(),
            ],
        ]);
    }

    /**
     * Get subscription history for a branch (or all branches for super admin).
     */
    public function getHistory(Request $request)
    {
        $user = $request->user();
        $role = strtolower($user->role);

        $query = Subscription::with('branch')->orderByDesc('created_at');

        if (!in_array($role, ['super_admin', 'owner'])) {
            $branchId = $user->branch_id ?? null;
            if (!$branchId && $user->branch) {
                $branch = Branch::where('name', $user->branch)->first();
                $branchId = $branch?->id;
            }
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $subscriptions = $query->get()->map(function ($sub) {
            return [
                'id' => 'INV-' . str_pad($sub->id, 6, '0', STR_PAD_LEFT),
                'raw_id' => $sub->id,
                'payment_ref' => $sub->payment_ref,
                'branch_id' => $sub->branch_id,
                'branch_name' => $sub->branch?->name ?? '-',
                'plan_name' => ucfirst($sub->plan_key === 'pro' ? 'Pro Business' : 'Starter'),
                'plan_key' => $sub->plan_key,
                'interval' => $sub->interval,
                'status' => $sub->status,
                'amount' => $sub->amount,
                'payment_method' => $sub->payment_method,
                'date' => $sub->created_at?->toDateString(),
                'valid_until' => $sub->expires_at ? $sub->expires_at->toDateString() : 'Selamanya',
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $subscriptions,
        ]);
    }

    /**
     * Create / activate a subscription (initiate payment).
     */
    public function subscribe(Request $request)
    {
        $user = $request->user();
        $role = strtolower($user->role);

        $this->validate($request, [
            'plan_key'       => 'required|string|in:starter,pro',
            'interval'       => 'required|string|in:month,year,lifetime',
            'payment_method' => 'nullable|string',
            'branch_id'      => 'nullable|integer',
        ]);

        // Determine branch
        $branchId = $request->branch_id ?? $user->branch_id ?? null;
        if (!$branchId && $user->branch) {
            $branch = Branch::where('name', $user->branch)->first();
            $branchId = $branch?->id;
        }

        if (!$branchId) {
            return response()->json(['message' => 'Branch not found'], 422);
        }

        // Determine plan price
        $plan = SubscriptionPlan::where('plan_key', $request->plan_key)->first();
        $priceMonthly = $plan ? $plan->price_monthly : ($request->plan_key === 'pro' ? 215000 : 0);
        $priceYearly = $plan ? $plan->price_yearly : ($request->plan_key === 'pro' ? 2580000 : 0);

        $amount = 0;
        $expiresAt = null;

        if ($request->plan_key === 'pro') {
            if ($request->interval === 'year') {
                $amount = $priceYearly;
                $expiresAt = Carbon::now()->addYear();
            } else {
                $amount = $priceMonthly;
                $expiresAt = Carbon::now()->addMonth();
            }
        }

        // Create subscription record
        $subscription = Subscription::create([
            'branch_id' => $branchId,
            'plan_key' => $request->plan_key,
            'interval' => $request->interval,
            'status' => ($amount === 0) ? 'active' : 'pending', // starter is immediately active
            'payment_method' => $request->payment_method,
            'payment_ref' => 'SUB-' . strtoupper(uniqid()),
            'amount' => $amount,
            'starts_at' => Carbon::now(),
            'expires_at' => $expiresAt,
        ]);

        AuditLog::log('create', 'Subscription', "Created {$request->plan_key} subscription for branch {$branchId}");

        // For paid plans, call DOKU or fallback mock
        if ($amount > 0) {
            try {
                $paymentData = $this->createDokuCheckout(
                    $subscription,
                    $user,
                    $amount,
                    $request->payment_method ?? 'doku'
                );
            } catch (\Exception $e) {
                Log::error('Subscription DOKU payment failed, falling back to mock: ' . $e->getMessage());
                $paymentData = $this->generateMockPayment($request->payment_method, $amount, $subscription->payment_ref);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Subscription created, please complete payment',
                'data'    => [
                    'subscription_id' => $subscription->id,
                    'payment_ref'     => $subscription->payment_ref,
                    'amount'          => $amount,
                    'payment_data'    => $paymentData,
                    'expires_payment_at' => Carbon::now()->addHours(24)->toIso8601String(),
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Subscription activated successfully',
            'data' => [
                'subscription_id' => $subscription->id,
                'plan_key' => $subscription->plan_key,
                'status' => $subscription->status,
            ],
        ]);
    }

    /**
     * Confirm payment and activate subscription.
     */
    public function confirmPayment(Request $request, $id)
    {
        $subscription = Subscription::findOrFail($id);

        // Mark as active
        $subscription->update(['status' => 'active']);

        // Deactivate previous subscriptions for same branch
        Subscription::where('branch_id', $subscription->branch_id)
            ->where('id', '!=', $subscription->id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        AuditLog::log('update', 'Subscription', "Activated subscription #{$subscription->id}");

        return response()->json([
            'status' => 'success',
            'message' => 'Payment confirmed and subscription activated',
            'data' => [
                'subscription_id' => $subscription->id,
                'plan_key' => $subscription->plan_key,
                'status' => $subscription->status,
                'expires_at' => $subscription->expires_at,
            ],
        ]);
    }

    /**
     * Unsubscribe from current active paid plan, reverting to starter.
     */
    public function unsubscribe(Request $request)
    {
        $user = $request->user();
        
        $branchId = $request->branch_id ?? $user->branch_id ?? null;
        if (!$branchId && $user->branch) {
            $branch = Branch::where('name', $user->branch)->first();
            $branchId = $branch?->id;
        }

        if (!$branchId) {
            return response()->json(['message' => 'Branch not found'], 422);
        }

        // Find active subscription 
        $activeSub = Subscription::where('branch_id', $branchId)
            ->where('status', 'active')
            ->where('plan_key', '!=', 'starter')
            ->first();

        if ($activeSub) {
            $activeSub->update(['status' => 'expired']);
        }

        // Create starter subscription
        $starterSub = Subscription::create([
            'branch_id' => $branchId,
            'plan_key' => 'starter',
            'interval' => 'lifetime',
            'status' => 'active',
            'amount' => 0,
            'starts_at' => Carbon::now(),
            'expires_at' => null,
        ]);

        AuditLog::log('update', 'Subscription', "Unsubscribed to starter for branch {$branchId}");

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil berhenti berlangganan. Paket kembali ke Starter.',
            'data' => [
                'subscription_id' => $starterSub->id,
                'plan_key' => $starterSub->plan_key,
                'status' => $starterSub->status,
            ]
        ]);
    }

    /**
     * Seed default plans.
     */
    public function seedPlans(Request $request)
    {
        $user = $request->user();
        $role = strtolower($user->role);

        if (!in_array($role, ['super_admin', 'owner'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $defaults = SubscriptionPlan::getDefaultPlans();
        $created = [];

        foreach ($defaults as $planData) {
            $plan = SubscriptionPlan::updateOrCreate(
                ['plan_key' => $planData['plan_key']],
                $planData
            );
            $created[] = $plan->plan_key;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Default plans seeded',
            'data' => $created,
        ]);
    }

    // ─── DOKU Checkout Integration ─────────────────────────────────────────────

    /**
     * Create DOKU Checkout session for subscription payment.
     * Returns payment_url (hosted checkout) or falls back to mock if DOKU not configured.
     */
    private function createDokuCheckout(Subscription $subscription, $user, float $amount, string $method): array
    {
        $dokuClientId  = env('DOKU_CLIENT_ID');
        $dokuSecretKey = env('DOKU_SECRET_KEY');
        $dokuEnv       = env('DOKU_ENV', 'sandbox');

        // No DOKU credentials → use mock
        if (empty($dokuClientId) || empty($dokuSecretKey)) {
            Log::info('SubscriptionController: DOKU not configured, using mock payment');
            return $this->generateMockPayment($method, $amount, $subscription->payment_ref);
        }

        $baseUrl    = $dokuEnv === 'production' ? 'https://api.doku.com' : 'https://api-sandbox.doku.com';
        $targetPath = '/checkout/v1/payment';
        $requestId  = Str::uuid()->toString();
        $timestamp  = Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z');

        $adminUrl = env('ADMIN_URL', env('APP_URL', 'http://localhost:3091'));
        $callbackUrl = $adminUrl . '/subscriptions?payment_ref=' . $subscription->payment_ref;

        // Plan name as line item
        $planLabel = $subscription->plan_key === 'pro' ? 'Pro Business' : 'Starter';
        $intervalLabel = $subscription->interval === 'year' ? 'Tahunan' : 'Bulanan';

        $body = [
            'order' => [
                'amount'         => (int) $amount,
                'invoice_number' => $subscription->payment_ref,
                'currency'       => 'IDR',
                'session_id'     => $requestId,
                'callback_url'   => $callbackUrl,
                'line_items'     => [
                    [
                        'name'     => "Langganan {$planLabel} - {$intervalLabel}",
                        'price'    => (int) $amount,
                        'quantity' => 1,
                    ]
                ],
            ],
            'payment' => [
                'payment_due_date' => 60,
            ],
            'customer' => [
                'name'  => substr($user->name ?? 'Admin', 0, 50),
                'email' => $user->email ?? 'admin@naqupos.com',
                'phone' => preg_replace('/[^0-9+]/', '', $user->phone ?? '08123456789'),
            ],
        ];

        // Apply specific payment method to skip DOKU choice page
        $dokuChannelMap = [
            'BCA' => 'VIRTUAL_ACCOUNT_BCA',
            'VA_BCA' => 'VIRTUAL_ACCOUNT_BCA',
            'MANDIRI' => 'VIRTUAL_ACCOUNT_BANK_MANDIRI',
            'VA_MANDIRI' => 'VIRTUAL_ACCOUNT_BANK_MANDIRI',
            'BNI' => 'VIRTUAL_ACCOUNT_BNI',
            'VA_BNI' => 'VIRTUAL_ACCOUNT_BNI',
            'BRI' => 'VIRTUAL_ACCOUNT_BRI',
            'VA_BRI' => 'VIRTUAL_ACCOUNT_BRI',
            'PERMATA' => 'VIRTUAL_ACCOUNT_BANK_PERMATA',
            'VA_PERMATA' => 'VIRTUAL_ACCOUNT_BANK_PERMATA',
            'CIMB' => 'VIRTUAL_ACCOUNT_BANK_CIMB',
            'VA_CIMB' => 'VIRTUAL_ACCOUNT_BANK_CIMB',
            'DANAMON' => 'VIRTUAL_ACCOUNT_BANK_DANAMON',
            'VA_DANAMON' => 'VIRTUAL_ACCOUNT_BANK_DANAMON',
            'DANA' => 'EMONEY_DANA',
            'OVO' => 'EMONEY_OVO',
            'SHOPEEPAY' => 'EMONEY_SHOPEEPAY',
            'QRIS' => 'QRIS',
        ];

        $methodUpper = strtoupper($method);
        if (isset($dokuChannelMap[$methodUpper])) {
            $body['payment']['payment_method_types'] = [$dokuChannelMap[$methodUpper]];
        }

        $jsonBody  = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = $this->generateDokuSignature('POST', $targetPath, $jsonBody, $timestamp, $requestId, $dokuClientId, $dokuSecretKey);
        $digest    = base64_encode(hash('sha256', $jsonBody, true));

        Log::info('SubscriptionController: DOKU Checkout request', ['ref' => $subscription->payment_ref, 'amount' => $amount]);

        $client = new Client(['timeout' => 15]);
        $response = $client->post($baseUrl . $targetPath, [
            'headers' => [
                'Client-Id'         => $dokuClientId,
                'Request-Id'        => $requestId,
                'Request-Timestamp' => $timestamp,
                'Signature'         => $signature,
                'Digest'            => 'SHA-256=' . $digest,
                'Content-Type'      => 'application/json',
            ],
            'body' => $jsonBody,
        ]);

        $data       = json_decode($response->getBody()->getContents(), true);
        $paymentUrl = $data['response']['payment']['url'] ?? $data['payment']['url'] ?? null;

        Log::info('SubscriptionController: DOKU Checkout response', ['url' => $paymentUrl]);

        return [
            'type'        => 'doku_checkout',
            'payment_url' => $paymentUrl,
            'amount'      => (int) $amount,
            'expiry_time' => Carbon::now()->addHours(1)->toIso8601String(),
        ];
    }

    /**
     * Generate DOKU HMAC-SHA256 signature.
     */
    private function generateDokuSignature(string $method, string $path, string $jsonBody, string $timestamp, string $requestId, string $clientId, string $secretKey): string
    {
        $digest = base64_encode(hash('sha256', $jsonBody, true));
        $raw    = "Client-Id:{$clientId}\nRequest-Id:{$requestId}\nRequest-Timestamp:{$timestamp}\nRequest-Target:{$path}";
        if (strtoupper($method) !== 'GET') {
            $raw .= "\nDigest:{$digest}";
        }
        return 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $raw, $secretKey, true));
    }

    /**
     * Mock payment for development (DOKU not configured).
     */
    private function generateMockPayment(string $method, float $amount, string $ref): array
    {
        if ($method === 'qris') {
            return [
                'type'        => 'qris',
                'mock_mode'   => true,
                'qr_code_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode("payment:{$ref}:amount:{$amount}"),
                'amount'      => (int) $amount,
                'expiry_time' => Carbon::now()->addHours(24)->toIso8601String(),
            ];
        }

        $bankMap = ['bca' => 'BCA', 'bni' => 'BNI', 'bri' => 'BRI', 'mandiri' => 'Mandiri'];
        $bankName = $bankMap[$method] ?? 'BCA';

        return [
            'type'        => 'bank_transfer',
            'mock_mode'   => true,
            'bank'        => $bankName,
            'va_number'   => '8095' . str_pad(rand(0, 9999999), 7, '0', STR_PAD_LEFT),
            'amount'      => (int) $amount,
            'expiry_time' => Carbon::now()->addHours(24)->toIso8601String(),
        ];
    }
}
