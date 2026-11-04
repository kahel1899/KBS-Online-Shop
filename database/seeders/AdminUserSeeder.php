<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    // Set ADMIN_EMAIL and ADMIN_PASSWORD in .env (or in Render's environment).
    public function run()
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set, skipping admin user.');
            return;
        }

        // Only creates the admin once, never resets the password afterwards
        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
                'role' => User::ROLE_ADMIN,
            ]
        );
    }
}
