<?php

namespace Database\Seeders\Prod;

use App\Models\User;
use App\Models\Branch;
use App\Models\LoyaltyPoint;
use App\Models\Membership;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class CustomerSeeder extends Seeder
{
    public function run()
    {
        $branches = Branch::all();
        if ($branches->isEmpty()) {
            return;
        }

        $soloBranch = $branches->where('code', 'ZFR-SOLO')->first() ?: $branches->first();
        $jktBranch = $branches->where('code', 'ZFR-JKT')->first() ?: ($branches->count() > 1 ? $branches->get(1) : $branches->first());

        $customers = [
            [
                'name' => 'Budi Sudarsono',
                'email' => 'budi.sudarsono@example.com',
                'phone' => '081234567001',
                'gender' => 'M',
                'birth_date' => '1990-05-15',
                'address' => 'Jl. Slamet Riyadi No. 10, Solo',
                'branch_id' => $soloBranch->id,
                'membership_status' => 'gold',
                'registration_source' => 'app',
            ],
            [
                'name' => 'Siti Aminah',
                'email' => 'siti.aminah@example.com',
                'phone' => '081234567002',
                'gender' => 'F',
                'birth_date' => '1992-08-20',
                'address' => 'Perum Solo Baru Blok C/5, Sukoharjo',
                'branch_id' => $soloBranch->id,
                'membership_status' => 'silver',
                'registration_source' => 'walk-in',
            ],
            [
                'name' => 'Andi Wijaya',
                'email' => 'andi.wijaya@example.com',
                'phone' => '081234567003',
                'gender' => 'M',
                'birth_date' => '1985-12-10',
                'address' => 'Jl. Sudirman No. 100, Jakarta Pusat',
                'branch_id' => $jktBranch->id,
                'membership_status' => 'platinum',
                'registration_source' => 'app',
            ],
            [
                'name' => 'Dewi Lestari',
                'email' => 'dewi.lestari@example.com',
                'phone' => '081234567004',
                'gender' => 'F',
                'birth_date' => '1995-03-25',
                'address' => 'Apartemen Gading Nias, Jakarta Utara',
                'branch_id' => $jktBranch->id,
                'membership_status' => 'new',
                'registration_source' => 'walk-in',
            ],
            [
                'name' => 'Eko Prasetyo',
                'email' => 'eko.prasetyo@example.com',
                'phone' => '081234567005',
                'gender' => 'M',
                'birth_date' => '1988-11-05',
                'address' => 'Jl. Gajah Mada No. 45, Solo',
                'branch_id' => $soloBranch->id,
                'membership_status' => 'gold',
                'registration_source' => 'app',
            ],
        ];

        foreach ($customers as $data) {
            $data['password'] = Hash::make('password123');
            $data['role'] = 'customer';
            $data['is_verified'] = true;
            $data['has_app_account'] = ($data['registration_source'] === 'app');

            $user = User::updateOrCreate(
                ['email' => $data['email']],
                $data
            );

            // Seed Loyalty Points (Random)
            LoyaltyPoint::updateOrCreate(
                ['user_id' => $user->id, 'branch_id' => $user->branch_id],
                [
                    'points' => rand(100, 1000),
                    'remaining_points' => rand(50, 500),
                    'expires_at' => Carbon::now()->addYear(),
                ]
            );

            // Seed Membership if applicable
            if ($user->membership_status !== 'new') {
                Membership::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'branch_id' => $user->branch_id,
                        'membership_type' => $user->membership_status,
                        'invoice_no' => 'MEM-' . strtoupper(substr($user->membership_status, 0, 3)) . '-' . $user->id,
                        'invoice_date' => Carbon::now()->subMonths(rand(1, 6)),
                        'start_date' => Carbon::now()->subMonths(rand(1, 6)),
                        'expiry_date' => Carbon::now()->addMonths(rand(6, 12)),
                        'status' => 'Active',
                        'price' => $user->membership_status === 'platinum' ? 1000000 : ($user->membership_status === 'gold' ? 500000 : 250000),
                    ]
                );
            }
        }
    }
}
