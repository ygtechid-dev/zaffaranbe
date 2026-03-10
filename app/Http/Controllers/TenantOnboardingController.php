<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Branch;
use App\Models\Subscription;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class TenantOnboardingController extends Controller
{
    /**
     * PUBLIC: Self-service onboarding for new tenants (salon owners).
     * Creates Branch, User (owner), and Starter Subscription.
     */
    public function register(Request $request)
    {
        $this->validate($request, [
            'salon_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'phone'      => 'required|string|max:20|unique:users',
            'email'      => 'required|email|max:255|unique:users',
            'password'   => 'required|string|min:6',
            'city'       => 'nullable|string|max:100',
        ]);

        try {
            DB::beginTransaction();

            // 1. Create the Branch (Tenant)
            $branchCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $request->salon_name), 0, 3)) . date('y');
            $branch = Branch::create([
                'name' => $request->salon_name,
                'code' => $branchCode . rand(100, 999), // guarantee unique
                'phone' => $request->phone,
                'city' => $request->city,
                'is_active' => true,
            ]);

            // 2. Create the Owner User
            $owner = User::create([
                'name' => $request->owner_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => 'owner',
                'branch_id' => $branch->id,
                'is_verified' => true, // Assuming auto-verified for frictionless onboarding, or handle OTP separately
            ]);

            // 3. Create Starter Subscription (Freemium)
            Subscription::create([
                'branch_id' => $branch->id,
                'plan_key'  => 'starter', // Ensure this matches your SubscriptionPlan code
                'status'    => 'active',
                'starts_at' => now(),
            ]);

            // 4. Log the onboarding
            AuditLog::log('onboarding', 'Tenant', "New tenant registered: {$request->salon_name} by {$request->owner_name}");

            DB::commit();

            // 5. Generate Login Token for auto-redirect
            $token = JWTAuth::fromUser($owner);

            return response()->json([
                'status' => 'success',
                'message' => 'Pendaftaran berhasil. Selamat datang di NaquPOS!',
                'data' => [
                    'user' => $owner,
                    'branch' => $branch,
                    'token' => $token,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mendaftar. Silakan coba lagi. ' . $e->getMessage()
            ], 500);
        }
    }
}
