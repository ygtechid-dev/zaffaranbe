<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\CleanupExpiredPaymentLogs::class,
        Commands\ReconcileSuccessfulPayments::class,
        Commands\SendBookingReminders::class,
        Commands\SendReviewRequests::class,
        Commands\ProcessAutomations::class,
        Commands\FixBookings::class,
        Commands\DumpBooking::class,
        Commands\FixTransactionItems::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
   protected function schedule(Schedule $schedule)
{
    $schedule->command('payment:cleanup')->everyMinute();
    $schedule->command('payment:reconcile-successful')->everyMinute()->withoutOverlapping();
    $schedule->command('reminders:send --type=h1')->dailyAt('21:00');
    $schedule->command('reminders:send --type=2h')->hourly();
$schedule->command('reviews:request')->dailyAt('20:00');
    $schedule->command('automations:process')->dailyAt('08:00');
}
}
