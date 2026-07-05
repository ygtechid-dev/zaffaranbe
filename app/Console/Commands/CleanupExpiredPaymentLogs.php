<?php

namespace App\Console\Commands;

use App\Models\PaymentLog;
use App\Models\Booking;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CleanupExpiredPaymentLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup expired payment logs and unpaid bookings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Cleanup expired payment logs
        $expiredLogsCount = PaymentLog::where('status', 'pending')
            ->where('expired_at', '<', Carbon::now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$expiredLogsCount} payment logs.");

        // 2. Cleanup expired unpaid bookings (delete/cancel them)
        $expiredBookingsCount = Booking::where('payment_status', 'unpaid')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->whereIn('status', ['pending_payment', 'awaiting_payment'])
            ->whereDoesntHave('payments', function ($query) {
                $query->whereIn('status', ['success', 'paid', 'settlement']);
            })
            ->update([
                'status' => 'cancelled',
                'cancellation_reason' => 'Auto-cancelled: DP/Lunas belum dibayar dalam 30 menit',
                'cancelled_at' => Carbon::now(),
            ]);

        $this->info("Cancelled {$expiredBookingsCount} expired unpaid bookings.");

        $this->info("Cleanup completed successfully.");
    }
}
