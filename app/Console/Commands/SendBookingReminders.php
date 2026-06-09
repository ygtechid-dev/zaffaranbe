<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendBookingReminders extends Command
{
    protected $signature = 'reminders:send {--type=all : h1 | 2h | all}';
    protected $description = 'Send WhatsApp reminders for bookings (H-1 jam 21:00, 2H setiap jam)';

    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        parent::__construct();
        $this->whatsappService = $whatsappService;
    }

    public function handle()
    {
        $type = $this->option('type');
        $this->info("Starting reminder checks... type={$type}");

        if ($type === 'h1' || $type === 'all') {
            $this->sendH1Reminders();
        }

        if ($type === '2h' || $type === 'all') {
            $this->send2HReminders();
        }

        $this->info('Done.');
    }

    private function sendH1Reminders()
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        $bookings = Booking::where('booking_date', $tomorrow)
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->where('is_reminded_h1', false)
            ->with(['user', 'branch', 'service', 'therapist'])
            ->get();

        $this->info("H-1: ditemukan {$bookings->count()} booking.");

        $count = 0;
        foreach ($bookings as $booking) {
            $phone = $booking->user?->phone ?? $booking->guest_phone ?? null;
               $this->info("Booking: {$booking->booking_ref}");
    $this->info("User: " . ($booking->user?->name ?? 'Guest'));
    $this->info("Phone: " . ($phone ?? 'NULL'));
            if ($phone) {
                 $this->info("Masuk if phone");
                $sent = $this->whatsappService->sendReminder($phone, $booking, 'H-1');
                 $this->info("Sent result: " . ($sent ? 'true' : 'false'));
                if ($sent) {
                    $booking->update(['is_reminded_h1' => true]);
                    $this->info("H-1 sent: {$booking->booking_ref} -> {$phone}");
                    $count++;
                }
            }
        }

        $this->info("H-1 reminders terkirim: {$count}");
    }

    private function send2HReminders()
    {
        $today = Carbon::today()->toDateString();

        $bookings = Booking::where('booking_date', $today)
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->where('is_reminded_h2', false)
            ->whereBetween('start_time', [
                Carbon::now()->addHours(2)->subMinutes(15)->format('H:i'),
                Carbon::now()->addHours(2)->addMinutes(15)->format('H:i'),
            ])
            ->with(['user', 'branch', 'service', 'therapist'])
            ->get();

        $this->info("2H: ditemukan {$bookings->count()} booking.");

        $count = 0;
        foreach ($bookings as $booking) {
            $phone = $booking->user?->phone ?? $booking->guest_phone ?? null;
            if ($phone) {
                $sent = $this->whatsappService->sendReminder($phone, $booking, '2H');
                if ($sent) {
                    $booking->update(['is_reminded_h2' => true]);
                    $this->info("2H sent: {$booking->booking_ref} -> {$phone}");
                    $count++;
                }
            }
        }

        $this->info("2H reminders terkirim: {$count}");
    }
}