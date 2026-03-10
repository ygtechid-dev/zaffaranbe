<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendReviewRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reviews:request';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send WhatsApp review requests for completed bookings';

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
        $this->info("Starting review request checks...");

        // Check for completed bookings that haven't received a request yet
        // We might want to wait a bit after completion (e.g. 1 hour)
        // For simplicity, we check if it was completed at least 30 mins ago to allow user to leave the premises.
        
        $oneHourAgo = Carbon::now()->subMinutes(30);

        $bookings = Booking::where('status', 'completed')
            ->where('is_review_requested', false)
            ->whereNotNull('user_id')
            ->whereNotNull('completed_at') // Ensure it was marked completed
            ->where('completed_at', '<=', $oneHourAgo)
            ->with(['user', 'branch', 'service'])
            ->get();

        $this->info("Found " . $bookings->count() . " bookings for review requests.");

        foreach ($bookings as $booking) {
            if ($booking->user && $booking->user->phone) {
                // Check if feedback already exists to avoid spamming if they already reviewed manually
                if ($booking->feedback()->exists()) {
                    $booking->update(['is_review_requested' => true]);
                    continue;
                }

                $sent = $this->whatsappService->sendReviewRequest($booking->user->phone, $booking);
                
                if ($sent) {
                    $booking->update(['is_review_requested' => true]);
                    $this->info("Review Request Sent to: " . $booking->user->name);
                }
            }
        }
        
        $this->info("Done.");
    }
}
