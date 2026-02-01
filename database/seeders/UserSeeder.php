<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin Users
        User::firstOrCreate(
            ['email' => 'admin@javaindonusa.net'],
            [
                'name' => 'Super Admin',
                'phone' => '08111111111',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'finance@javaindonusa.net'],
            [
                'name' => 'Finance Admin',
                'phone' => '08111111112',
                'password' => Hash::make('password'),
                'role' => 'finance',
                'is_active' => true,
            ]
        );

        // Collectors
        $collectors = [
            [
                'name' => 'Budi Santoso',
                'email' => 'budi@javaindonusa.net',
                'phone' => '08122222221',
                'commission_rate' => 2.5,
            ],
            [
                'name' => 'Agus Setiawan',
                'email' => 'agus@javaindonusa.net',
                'phone' => '08122222222',
                'commission_rate' => 2.5,
            ],
            [
                'name' => 'Dedi Kurniawan',
                'email' => 'dedi@javaindonusa.net',
                'phone' => '08122222223',
                'commission_rate' => 3.0,
            ],
            [
                'name' => 'Eko Prasetyo',
                'email' => 'eko@javaindonusa.net',
                'phone' => '08122222224',
                'commission_rate' => 2.5,
            ],
            [
                'name' => 'Fajar Hidayat',
                'email' => 'fajar@javaindonusa.net',
                'phone' => '08122222225',
                'commission_rate' => 2.0,
            ],
        ];

        foreach ($collectors as $collector) {
            User::firstOrCreate(
                ['email' => $collector['email']],
                [
                    'name' => $collector['name'],
                    'phone' => $collector['phone'],
                    'password' => Hash::make('password'),
                    'role' => 'penagih',
                    'commission_rate' => $collector['commission_rate'],
                    'is_active' => true,
                ]
            );
        }

        // Technicians
        $technicians = [
            [
                'name' => 'Gunawan Wibowo',
                'email' => 'gunawan@javaindonusa.net',
                'phone' => '08133333331',
            ],
            [
                'name' => 'Hendra Wijaya',
                'email' => 'hendra@javaindonusa.net',
                'phone' => '08133333332',
            ],
        ];

        foreach ($technicians as $technician) {
            User::firstOrCreate(
                ['email' => $technician['email']],
                [
                    'name' => $technician['name'],
                    'phone' => $technician['phone'],
                    'password' => Hash::make('password'),
                    'role' => 'technician',
                    'is_active' => true,
                ]
            );
        }
    }
}
