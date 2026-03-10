<?php

namespace Database\Seeders\Prod;

use Illuminate\Database\Seeder;
use App\Models\Therapist;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = Branch::all();

        if ($branches->isEmpty()) {
            $this->command->warn('No branches found. Please seed branches first.');
            return;
        }

        $staffData = [
            [
                'name' => 'Siti Aminah',
                'email' => 'siti@zafaran.com',
                'phone' => '082111110001',
                'gender' => 'female',
                'specialization' => 'Traditional Massage, Facial',
                'shift' => 'morning',
                'color' => '#f87171',
                'branch_name' => 'Zafaran Spa Solo'
            ],
            [
                'name' => 'Budi Santoso',
                'email' => 'budi@zafaran.com',
                'phone' => '082111110002',
                'gender' => 'male',
                'specialization' => 'Reflexology, Sport Massage',
                'shift' => 'afternoon',
                'color' => '#60a5fa',
                'branch_name' => 'Zafaran Spa Solo'
            ],
            [
                'name' => 'Dewi Lestari',
                'email' => 'dewi@zafaran.com',
                'phone' => '082111110003',
                'gender' => 'female',
                'specialization' => 'Hair Treatment, Mani-Pedi',
                'shift' => 'morning',
                'color' => '#fbbf24',
                'branch_name' => 'Zafaran Spa Jakarta'
            ],
            [
                'name' => 'Rina Wijaya',
                'email' => 'rina@zafaran.com',
                'phone' => '082111110004',
                'gender' => 'female',
                'specialization' => 'Aromatherapy, Body Scrub',
                'shift' => 'afternoon',
                'color' => '#34d399',
                'branch_name' => 'Zafaran Spa Jakarta'
            ],
            [
                'name' => 'Andi Pratama',
                'email' => 'andi@zafaran.com',
                'phone' => '082111110005',
                'gender' => 'male',
                'specialization' => 'Deep Tissue Massage',
                'shift' => 'morning',
                'color' => '#a78bfa',
                'branch_name' => 'Zafaran Spa Surabaya'
            ]
        ];

        foreach ($staffData as $data) {
            $branch = Branch::where('name', $data['branch_name'])->first();

            if (!$branch) {
                // Fallback to first branch if specific one not found
                $branch = $branches->first();
            }

            unset($data['branch_name']);
            $data['branch_id'] = $branch->id;
            $data['is_active'] = true;
            $data['is_booking_online_enabled'] = true;
            $data['default_service_commission'] = 10.00;
            $data['service_commission_type'] = 'percent';
            $data['start_work_date'] = Carbon::now()->subMonths(6);

            $therapist = Therapist::updateOrCreate(
                ['email' => $data['email']],
                $data
            );

            // Also create a User account for the staff to login to POS/Admin
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => Hash::make('staff123'),
                    'role' => 'cashier',
                    'staff_id' => $therapist->id,
                    'is_verified' => true,
                    'is_active' => true,
                    'branch_id' => $branch->id
                ]
            );
        }
    }
}
