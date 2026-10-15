<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@copa.local'],
            [
                'npk' => '0000',
                'name' => 'Administrator',
                'password' => Hash::make('Avi123!'),
                'role' => 'admin',
            ]
        );
    }
}
