<?php

namespace Database\Seeders\Prod;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class BranchAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = DB::table('branches')->get();

        foreach ($branches as $branch) {
            $email = strtolower('admin.' . str_replace(' ', '', $branch->code) . '@zafaran.com');
            
            $userData = [
                'name' => 'Admin ' . $branch->name,
                'email' => $email,
                'phone' => $branch->phone ?? '081234567890',
                'birth_date' => '1990-01-01',
                'address' => $branch->address,
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'branch_id' => $branch->id,
                'is_verified' => true,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];

            DB::table('users')->updateOrInsert(
                ['email' => $email],
                $userData
            );
        }
    }
}
