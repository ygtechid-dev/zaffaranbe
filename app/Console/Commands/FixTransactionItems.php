<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Booking;

class FixTransactionItems extends Command
{
    protected $signature = 'transactions:fix-items';
    protected $description = 'Fix missing transaction items';

    public function handle()
    {
        $transactions = Transaction::all();
        $handled = 0;

        foreach ($transactions as $t) {
            $booking = Booking::with(['service', 'items.service'])->find($t->booking_id);
            if ($booking) {
                // Remove existing items for this transaction
                TransactionItem::where('transaction_id', $t->id)->delete();

                if ($booking->items && count($booking->items) > 0) {
                    foreach ($booking->items as $bi) {
                        $price = $bi->price ?? 0;
                        $roomCharge = $bi->room_charge ?? 0;
                        TransactionItem::create([
                            'transaction_id' => $t->id,
                            'type' => 'service',
                            'service_id' => $bi->service_id,
                            'therapist_id' => $bi->therapist_id,
                            'start_time' => $bi->start_time,
                            'quantity' => 1,
                            'price' => $price,
                            'subtotal' => $price + $roomCharge,
                            'notes' => 'Generated from Booking Item: ' . ($bi->service->name ?? 'Unknown') . ($bi->guest_name ? ' for ' . $bi->guest_name : '')
                        ]);
                    }
                } else {
                    TransactionItem::create([
                        'transaction_id' => $t->id,
                        'type' => 'service',
                        'service_id' => $booking->service_id,
                        'therapist_id' => $booking->therapist_id,
                        'start_time' => $booking->start_time,
                        'quantity' => 1,
                        'price' => $t->subtotal,
                        'subtotal' => $t->total,
                        'notes' => 'Generated item for ' . ($booking->service->name ?? 'Unknown Service')
                    ]);
                }
                $handled++;
            }
        }
        $this->info("Handled {$handled} transactions.");
    }
}
