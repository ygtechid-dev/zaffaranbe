<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class ListUsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        foreach ($users as $user) {
            $this->command->info($user->email . ' - ' . $user->role);
        }
    }
}
