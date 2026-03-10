<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;

class FixBookings extends Command
{
    protected $signature = 'bookings:fix';
    protected $description = 'Fix existing corrupted booking totals';

    public function handle()
    {
        $bookings = Booking::with('items')->get();
        $updatedCount = 0;
        foreach ($bookings as $booking) {
            if ($booking->items->count() > 1) {
                $sum = 0;
                foreach ($booking->items as $item) {
                    $sum += $item->price + $item->room_charge;
                }
                if ($sum > $booking->total_price) {
                    $this->info("Updating Booking ID {$booking->id} from {$booking->total_price} to {$sum}");
                    $booking->total_price = $sum;
                    $booking->save();
                    $updatedCount++;
                }
            }
        }
        $this->info("Updated {$updatedCount} bookings.");
    }
}
