<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Developer
        User::create([
            'id' => Str::uuid(),
            'name' => 'Developer User',
            'email' => 'developer@sample.com',
            'phone' => '081234567890',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'gender' => 'male',
            'role' => 'developer',
            'status' => 'active',
        ]);

        // Admin
        User::create([
            'id' => Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@sample.com',
            'phone' => '081298765432',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'gender' => 'female',
            'role' => 'admin',
            'status' => 'active',
        ]);

        // Customer
        User::create([
            'id' => Str::uuid(),
            'name' => 'Customer User',
            'email' => 'customer@sample.com',
            'email_verified_at' => now(),
            'phone' => null,
            'password' => Hash::make('password123'),
            'gender' => null,
            'role' => 'customer',
            'status' => 'active',
        ]);
    }
}
