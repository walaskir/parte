<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('ADMIN_DEFAULT_PASSWORD');

        if (empty($password)) {
            $password = Str::random(16);
            $this->command->warn("Generated random password for admin user: {$password}");
            $this->command->warn('Set ADMIN_DEFAULT_PASSWORD in .env to use a specific password.');
        }

        User::firstOrCreate(
            ['email' => 'robert@walaski.cz'],
            [
                'name' => 'Robert',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );
    }
}
