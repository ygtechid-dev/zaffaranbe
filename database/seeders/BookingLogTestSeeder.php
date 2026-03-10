<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use App\Models\BookingAgendaLog;
use App\Models\ServicePriceLog;
use App\Models\Service;
use App\Models\User;
use App\Models\Branch;
use App\Models\Therapist;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingLogTestSeeder extends Seeder
{
    public function run()
    {
        // Cleanup existing test data
        Booking::whereIn('booking_ref', [
            'BK-20260301-TEST1',
            'BK-20260301-TEST2',
            'BK-20260301-TEST3',
            'BK-20260301-TEST4'
        ])->delete();

        $user = User::first();
        $admin = User::where('role', 'admin')->orWhere('role', 'superadmin')->first() ?: $user;
        $service1 = Service::first();
        
        $branch = Branch::where('name', 'like', '%Jakarta%')->first() ?: Branch::where('id', 2)->first() ?: Branch::first();
        $therapist = Therapist::where('branch_id', $branch->id)->first() ?: Therapist::first();
        $room = \App\Models\Room::where('branch_id', $branch->id)->first() ?: \App\Models\Room::first();

        if (!$user || !$service1 || !$branch || !$room || !$therapist) {
            $this->command->error("Data kurang untuk seeder.");
            return;
        }

        $this->command->info("Menggunakan Branch: {$branch->name} (ID: {$branch->id})");

        // --- Kasus 1: Overpayment ---
        $this->command->info('Membuat Kasus 1 (Overpayment)...');
        $b1 = Booking::create([
            'booking_ref' => 'BK-20260301-TEST1',
            'user_id' => $user->id, 'branch_id' => $branch->id, 'service_id' => $service1->id, 'room_id' => $room->id, 'therapist_id' => $therapist->id,
            'booking_date' => '2026-03-01', 'start_time' => '10:00', 'end_time' => '11:00', 'duration' => 60,
            'service_price' => 200000, 'total_price' => 200000, 'status' => 'confirmed', 'payment_status' => 'paid',
            'notes' => 'Awal bayar lunas 200rb'
        ]);
        BookingItem::create(['booking_id' => $b1->id, 'service_id' => $service1->id, 'room_id' => $room->id, 'price' => 200000, 'start_time' => '10:00', 'end_time' => '11:00', 'status' => 'active', 'therapist_id' => $therapist->id, 'duration' => 60]);
        Payment::create(['booking_id' => $b1->id, 'amount' => 200000, 'status' => 'success', 'payment_method' => 'cash', 'paid_at' => Carbon::now()]);
        
        $old1 = 200000; $new1 = 150000;
        $b1->update(['service_price' => $new1, 'total_price' => $new1, 'notes' => $b1->notes . "\n[SISTEM] Terdeteksi kelebihan bayar: Rp " . number_format($old1 - $new1) . " akibat ganti layanan ke lebih murah."]);
        $b1->items()->update(['price' => $new1]);
        BookingAgendaLog::create(['booking_id' => $b1->id, 'action' => 'update_agenda', 'old_data' => ['total' => $old1], 'new_data' => ['total' => $new1], 'price_difference' => $new1 - $old1, 'changed_by' => $admin->id, 'notes' => 'Ganti layanan ke lebih murah']);

        // --- Kasus 2: DP jadi Lunas ---
        $this->command->info('Membuat Kasus 2 (DP -> Lunas)...');
        $b2 = Booking::create([
            'booking_ref' => 'BK-20260301-TEST2',
            'user_id' => $user->id, 'branch_id' => $branch->id, 'service_id' => $service1->id, 'room_id' => $room->id, 'therapist_id' => $therapist->id,
            'booking_date' => '2026-03-02', 'start_time' => '12:00', 'end_time' => '13:00', 'duration' => 60,
            'service_price' => 300000, 'total_price' => 300000, 'status' => 'confirmed', 'payment_status' => 'partial',
            'notes' => 'Bayar DP 150rb'
        ]);
        BookingItem::create(['booking_id' => $b2->id, 'service_id' => $service1->id, 'room_id' => $room->id, 'price' => 300000, 'start_time' => '12:00', 'end_time' => '13:00', 'status' => 'active', 'therapist_id' => $therapist->id, 'duration' => 60]);
        Payment::create(['booking_id' => $b2->id, 'amount' => 150000, 'status' => 'success', 'payment_method' => 'cash', 'paid_at' => Carbon::now()]);
        
        $old2 = 300000; $new2 = 150000;
        $b2->update(['service_price' => $new2, 'total_price' => $new2, 'payment_status' => 'paid', 'notes' => $b2->notes . "\n[SISTEM] Harga turun jadi Rp 150.000, DP mencukupi pelunasan."]);
        $b2->items()->update(['price' => $new2]);
        BookingAgendaLog::create(['booking_id' => $b2->id, 'action' => 'update_agenda', 'old_data' => ['total' => $old2], 'new_data' => ['total' => $new2], 'price_difference' => $new2 - $old2, 'changed_by' => $admin->id, 'notes' => 'Penyesuaian harga (diskon) membuat DP jadi Lunas']);

        // --- Kasus 3: Lunas jadi Kurang Bayar ---
        $this->command->info('Membuat Kasus 3 (Lunas -> Kurang)...');
        $b3 = Booking::create([
            'booking_ref' => 'BK-20260301-TEST3',
            'user_id' => $user->id, 'branch_id' => $branch->id, 'service_id' => $service1->id, 'room_id' => $room->id, 'therapist_id' => $therapist->id,
            'booking_date' => '2026-03-03', 'start_time' => '14:00', 'end_time' => '15:00', 'duration' => 60,
            'service_price' => 150000, 'total_price' => 150000, 'status' => 'confirmed', 'payment_status' => 'paid',
            'notes' => 'Awal lunas 150rb'
        ]);
        BookingItem::create(['booking_id' => $b3->id, 'service_id' => $service1->id, 'room_id' => $room->id, 'price' => 150000, 'start_time' => '14:00', 'end_time' => '15:00', 'status' => 'active', 'therapist_id' => $therapist->id, 'duration' => 60]);
        Payment::create(['booking_id' => $b3->id, 'amount' => 150000, 'status' => 'success', 'payment_method' => 'cash', 'paid_at' => Carbon::now()]);
        
        $old3 = 150000; $new3 = 250000;
        $b3->update(['service_price' => $new3, 'total_price' => $new3, 'payment_status' => 'partial', 'notes' => $b3->notes . "\n[SISTEM] Terdeteksi kurang bayar: Rp " . number_format($new3 - $old3) . " akibat ganti layanan ke lebih mahal."]);
        $b3->items()->update(['price' => $new3]);
        BookingAgendaLog::create(['booking_id' => $b3->id, 'action' => 'update_agenda', 'old_data' => ['total' => $old3], 'new_data' => ['total' => $new3], 'price_difference' => $new3 - $old3, 'changed_by' => $admin->id, 'notes' => 'Ganti layanan ke lebih MAHAL (Kurang Bayar)']);

        // --- Kasus 4: DP jadi Tambah Kurang Bayar ---
        $this->command->info('Membuat Kasus 4 (DP + Tagihan)...');
        $b4 = Booking::create([
            'booking_ref' => 'BK-20260301-TEST4',
            'user_id' => $user->id, 'branch_id' => $branch->id, 'service_id' => $service1->id, 'room_id' => $room->id, 'therapist_id' => $therapist->id,
            'booking_date' => '2026-03-04', 'start_time' => '16:00', 'end_time' => '17:00', 'duration' => 60,
            'service_price' => 200000, 'total_price' => 200000, 'status' => 'confirmed', 'payment_status' => 'partial',
            'notes' => 'DP 50rb dari total 200rb'
        ]);
        BookingItem::create(['booking_id' => $b4->id, 'service_id' => $service1->id, 'room_id' => $room->id, 'price' => 200000, 'start_time' => '16:00', 'end_time' => '17:00', 'status' => 'active', 'therapist_id' => $therapist->id, 'duration' => 60]);
        Payment::create(['booking_id' => $b4->id, 'amount' => 50000, 'status' => 'success', 'payment_method' => 'cash', 'paid_at' => Carbon::now()]);
        
        $old4 = 200000; $new4 = 400000;
        $b4->update(['service_price' => $new4, 'total_price' => $new4, 'notes' => $b4->notes . "\n[SISTEM] Penambahan biaya: Rp " . number_format($new4 - $old4) . " (Upgrade Layanan). Sisa tagihan baru: Rp " . number_format($new4 - 50000)]);
        $b4->items()->update(['price' => $new4]);
        BookingAgendaLog::create(['booking_id' => $b4->id, 'action' => 'update_agenda', 'old_data' => ['total' => $old4], 'new_data' => ['total' => $new4], 'price_difference' => $new4 - $old4, 'changed_by' => $admin->id, 'notes' => 'Upgrade layanan (Penambahan Tagihan)']);

        $this->command->info('Seeder selesai!');
    }
}
