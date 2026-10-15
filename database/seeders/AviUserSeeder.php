<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AviUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['npk' => '0478', 'name' => 'Nanang Kriscandono', 'email' => 'nanang.kriscandono@astra-visteon.com'],
            ['npk' => '0011', 'name' => 'Sugeng Sunaryo', 'email' => 'sugeng.sunaryo@astra-visteon.com'],
            ['npk' => '0117', 'name' => 'Prasetio Adi Sasono', 'email' => 'prasetio.adi@astra-visteon.com'],
            ['npk' => '0525', 'name' => 'Jaka Maulana Purba', 'email' => 'jaka.purba@astra-visteon.com'],
            ['npk' => '0856', 'name' => 'Samantha Nalindra', 'email' => 'samantha.nalindra@astra-visteon.com'],
            ['npk' => '0145', 'name' => 'Arif Nopiyanto', 'email' => 'arif.nopiyanto@astra-visteon.com'],
            ['npk' => '0791', 'name' => 'Alisa Haqiqi Jusuf', 'email' => 'alisa.jusuf@astra-visteon.com'],
            ['npk' => '0568', 'name' => 'Destya Ardi Wijayanti', 'email' => 'destya.wijayanti@astra-visteon.com'],
        ];

        $password = Hash::make('Avi123!');

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['npk' => $userData['npk']],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => $password,
                    'role' => 'user',
                ]
            );
        }
    }
}
