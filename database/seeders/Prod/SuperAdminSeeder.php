<?php

namespace Database\Seeders\Prod;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userData = [
            'name' => 'Admin Zafaran',
            'email' => 'admin@zafaran.com',
            'phone' => '081234567890',
            'birth_date' => '1990-01-01',
            'address' => 'Solo, Jawa Tengah',
            'password' => Hash::make('admin123'),
            'role' => 'super_admin', // Changed to super_admin to match RoleSeeder
            'is_verified' => true,
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        DB::table('users')->updateOrInsert(
            ['email' => $userData['email']],
            $userData
        );
    }
}
