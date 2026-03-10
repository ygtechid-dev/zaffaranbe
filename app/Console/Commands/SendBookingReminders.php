<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendBookingReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send WhatsApp reminders for bookings (H-1 and H-2 hours)';

    protected $whatsappService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(WhatsAppService $whatsappService)
    {
        parent::__construct();
        $this->whatsappService = $whatsappService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info("Starting reminder checks...");

        // 1. H-1 Reminders (Tomorrow)
        $tomorrow = Carbon::tomorrow()->toDateString();
        
        $h1Bookings = Booking::where('booking_date', $tomorrow)
            ->where('status', 'confirmed')
            ->where('is_reminded_h1', false)
            ->whereNotNull('user_id') // Ensure valid user
            ->with(['user', 'branch', 'service'])
            ->get();

        $this->info("Found " . $h1Bookings->count() . " bookings for H-1 reminders.");

        foreach ($h1Bookings as $booking) {
            if ($booking->user && $booking->user->phone) {
                $sent = $this->whatsappService->sendReminder($booking->user->phone, $booking, 'H-1');
                
                if ($sent) {
                    $booking->update(['is_reminded_h1' => true]);
                    $this->info("H-1 Sent to: " . $booking->user->name);
                }
            }
        }

        // 2. H-2 Hours Reminders
        // Check bookings for TODAY where start_time is between now and now+2.5 hours (to be safe)
        // Or strictly within 2 hours. Let's say check if start_time is LESS than NOW + 2 hours + buffer
        // Ideally run this command every 15-30 mins.
        
        $today = Carbon::today()->toDateString();
        $now = Carbon::now();
        $twoHoursFromNow = $now->copy()->addHours(2);
        
        // We look for bookings today where start_time <= 2 hours from now
        // But also start_time >= now (hasn't started yet)
        
        $h2Bookings = Booking::where('booking_date', $today)
            ->where('status', 'confirmed')
            ->where('is_reminded_h2', false)
            ->whereNotNull('user_id')
            ->with(['user', 'branch', 'service'])
            ->get();

        $countH2 = 0;
        foreach ($h2Bookings as $booking) {
            $bookingTime = Carbon::parse($booking->booking_date->toDateString() . ' ' . $booking->start_time);
            
            // If booking time is in future AND within 2 hours and 15 mins (buffer)
            // Example: Now 12:00. Booking 14:00. Diff = 120 mins.
            // Example: Now 12:00. Booking 13:00. Diff = 60 mins.
            // We want to send if it is APPROACHING.
            
            if ($bookingTime->isFuture() && $bookingTime->diffInMinutes($now) <= 135) { // 2 hours 15 mins
                if ($booking->user && $booking->user->phone) {
                    $sent = $this->whatsappService->sendReminder($booking->user->phone, $booking, 'H-2');
                    
                    if ($sent) {
                        $booking->update(['is_reminded_h2' => true]);
                        $this->info("H-2 Sent to: " . $booking->user->name);
                        $countH2++;
                    }
                }
            }
        }
        
        $this->info("Sent $countH2 H-2 reminders.");
        $this->info("Done.");
    }
}
