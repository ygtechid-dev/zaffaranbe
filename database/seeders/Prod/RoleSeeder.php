<?php

namespace Database\Seeders\Prod;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $allPermissions = [
            'dashboard',
            'pos',
            'calendar',
            'bookings',
            'services',
            'staff',
            'inventory',
            'customers',
            'marketing',
            'reports',
            'payment',
            'subscription',
            'settings',
            'users',
            'audit'
        ];

        $roles = [
            [
                'name' => 'super_admin',
                'description' => 'Super Administrator with full access to all branch and system settings',
                'permissions' => json_encode($allPermissions),
                'is_global' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'owner',
                'description' => 'Business Owner with full access to features',
                'permissions' => json_encode($allPermissions),
                'is_global' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'admin',
                'description' => 'Branch Administrator',
                'permissions' => json_encode(['dashboard', 'pos', 'calendar', 'bookings', 'services', 'staff', 'inventory', 'customers', 'marketing', 'reports']),
                'is_global' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'cashier',
                'description' => 'Cashier Staff',
                'permissions' => json_encode(['dashboard', 'pos', 'calendar', 'bookings', 'customers']),
                'is_global' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'therapist',
                'description' => 'Therapist/Staff view',
                'permissions' => json_encode(['dashboard', 'calendar']),
                'is_global' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'marketing',
                'description' => 'Marketing and Content staff',
                'permissions' => json_encode(['dashboard', 'marketing', 'customers']),
                'is_global' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role['name']],
                $role
            );
        }
    }
}
