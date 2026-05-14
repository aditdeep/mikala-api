<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@mikala.com'],
            ['name' => 'Admin Mikala', 'phone' => '081234567890', 'password' => Hash::make('password'), 'role' => 'manajemen', 'status' => 'active']
        );
        User::firstOrCreate(
            ['email' => 'siti@example.com'],
            ['name' => 'Siti Nurhaliza', 'phone' => '081298765431', 'password' => Hash::make('password'), 'role' => 'mitra', 'status' => 'active']
        );
        User::firstOrCreate(
            ['email' => 'klien@example.com'],
            ['name' => 'Klien Test', 'phone' => '081298765432', 'password' => Hash::make('password'), 'role' => 'klien', 'status' => 'active']
        );
    }
}
