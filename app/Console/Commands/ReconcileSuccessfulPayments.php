<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileSuccessfulPayments extends Command
{
    protected $signature = 'payment:reconcile-successful {--dry-run : Only count unresolved successful payments}';
    protected $description = 'Create missing bookings for successful draft payments';

    public function handle(PaymentService $paymentService)
    {
        if ($this->option('dry-run')) {
            $count = Payment::where('status', 'success')
                ->whereNotNull('payment_log_id')
                ->whereNull('booking_id')
                ->count();

            $this->info("Found {$count} successful payment(s) without a booking.");
            return self::SUCCESS;
        }

        $reconciled = 0;
        $failed = 0;

        Payment::where('status', 'success')
            ->whereNotNull('payment_log_id')
            ->whereNull('booking_id')
            ->orderBy('id')
            ->chunkById(50, function ($payments) use ($paymentService, &$reconciled, &$failed) {
                foreach ($payments as $payment) {
                    try {
                        $paymentService->processSuccessfulPayment($payment);

                        if ($payment->fresh()->booking_id) {
                            $reconciled++;
                        } else {
                            $failed++;
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error('Successful payment reconciliation failed', [
                            'payment_id' => $payment->id,
                            'payment_log_id' => $payment->payment_log_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Reconciled {$reconciled} payment(s); {$failed} still unresolved.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
