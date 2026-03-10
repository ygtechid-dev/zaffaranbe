<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;

class DumpBooking extends Command
{
    protected $signature = 'booking:dump';
    protected $description = 'Dump booking info';

    public function handle()
    {
        $b = Booking::find(1);
        if ($b) {
            print_r($b->toArray());
        }
    }
}
