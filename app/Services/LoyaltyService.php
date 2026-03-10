<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\LoyaltyPoint;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LoyaltyService
{
    /**
     * Process points for a completed transaction
     */
    public function processTransaction(Transaction $transaction)
    {
        try {
            // Only process completed transactions
            if ($transaction->status !== 'completed') {
                return;
            }

            // Check if already processed
            $existing = LoyaltyPoint::where('transaction_id', $transaction->id)->first();
            if ($existing) {
                return;
            }

            // Identify user from Booking
            $booking = $transaction->booking;
            if (!$booking || !$booking->user_id) {
                return;
            }

            // Identify Branch
            $branchId = $transaction->branch_id ?? ($booking ? $booking->branch_id : null);

            // Fetch settings
            $settings = DB::table('loyalty_program_settings')
                ->where(function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId)->orWhereNull('branch_id');
                })
                ->orderBy('branch_id', 'desc') // Prioritize branch-specific settings over global
                ->first();

            if (!$settings || !$settings->enabled) {
                return;
            }

            // Check earning type
            if ($settings->earning_type !== 'amount_spent') {
                return;
            }

            $amount = $transaction->total;

            if ($amount < $settings->min_order_amount) {
                return; // Doesn't meet minimum
            }

            $points = 0;
            if ($settings->apply_multiples) {
                $points = floor($amount / $settings->min_order_amount) * $settings->points_per_amount;
            } else {
                $points = $settings->points_per_amount;
            }

            if ($points > 0) {
                // Expiry calculation
                $expiresAt = null;
                $expirationSetting = strtolower($settings->expiration);

                if (str_contains($expirationSetting, '1 year')) {
                    $expiresAt = Carbon::now()->addYear();
                } elseif (str_contains($expirationSetting, '6 month')) {
                    $expiresAt = Carbon::now()->addMonths(6);
                } elseif (str_contains($expirationSetting, '3 month')) {
                    $expiresAt = Carbon::now()->addMonths(3);
                } elseif (str_contains($expirationSetting, '1 month')) {
                    $expiresAt = Carbon::now()->addMonth();
                } // default is null (never/no expiration)

                LoyaltyPoint::create([
                    'user_id' => $booking->user_id,
                    'branch_id' => $branchId,
                    'transaction_id' => $transaction->id,
                    'points' => $points,
                    'remaining_points' => $points,
                    'expires_at' => $expiresAt
                ]);
            }
        } catch (\Exception $e) {
            Log::error('LoyaltyService error: ' . $e->getMessage());
        }
    }
}
