<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@sicap.local'],
            [
                'name' => 'Admin SICAP',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        $admin->assignRole('admin');
    }
}

