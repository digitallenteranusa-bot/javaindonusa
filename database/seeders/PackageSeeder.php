<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Paket Hemat 5 Mbps',
                'code' => 'PKT-05',
                'description' => 'Paket internet hemat untuk penggunaan ringan',
                'speed_download' => 5120, // 5 Mbps in Kbps
                'speed_upload' => 2048,   // 2 Mbps in Kbps
                'price' => 100000,
                'setup_fee' => 150000,
                'mikrotik_profile' => 'profile-5mbps',
                'priority' => 8,
                'sort_order' => 1,
            ],
            [
                'name' => 'Paket Standar 10 Mbps',
                'code' => 'PKT-10',
                'description' => 'Paket internet standar untuk keluarga kecil',
                'speed_download' => 10240, // 10 Mbps
                'speed_upload' => 5120,    // 5 Mbps
                'price' => 150000,
                'setup_fee' => 150000,
                'mikrotik_profile' => 'profile-10mbps',
                'priority' => 7,
                'sort_order' => 2,
            ],
            [
                'name' => 'Paket Plus 20 Mbps',
                'code' => 'PKT-20',
                'description' => 'Paket internet untuk keluarga dengan banyak device',
                'speed_download' => 20480, // 20 Mbps
                'speed_upload' => 10240,   // 10 Mbps
                'price' => 200000,
                'setup_fee' => 200000,
                'mikrotik_profile' => 'profile-20mbps',
                'priority' => 6,
                'sort_order' => 3,
            ],
            [
                'name' => 'Paket Super 30 Mbps',
                'code' => 'PKT-30',
                'description' => 'Paket internet super cepat untuk streaming & gaming',
                'speed_download' => 30720, // 30 Mbps
                'speed_upload' => 15360,   // 15 Mbps
                'price' => 275000,
                'setup_fee' => 200000,
                'mikrotik_profile' => 'profile-30mbps',
                'priority' => 5,
                'sort_order' => 4,
            ],
            [
                'name' => 'Paket Ultra 50 Mbps',
                'code' => 'PKT-50',
                'description' => 'Paket internet ultra untuk power user',
                'speed_download' => 51200, // 50 Mbps
                'speed_upload' => 25600,   // 25 Mbps
                'price' => 350000,
                'setup_fee' => 250000,
                'mikrotik_profile' => 'profile-50mbps',
                'priority' => 4,
                'sort_order' => 5,
            ],
            [
                'name' => 'Paket Bisnis 100 Mbps',
                'code' => 'PKT-100',
                'description' => 'Paket internet untuk kebutuhan bisnis',
                'speed_download' => 102400, // 100 Mbps
                'speed_upload' => 51200,    // 50 Mbps
                'price' => 500000,
                'setup_fee' => 500000,
                'mikrotik_profile' => 'profile-100mbps',
                'priority' => 3,
                'sort_order' => 6,
            ],
            [
                'name' => 'Paket Enterprise 200 Mbps',
                'code' => 'PKT-200',
                'description' => 'Paket internet enterprise dedicated',
                'speed_download' => 204800, // 200 Mbps
                'speed_upload' => 102400,   // 100 Mbps
                'price' => 1000000,
                'setup_fee' => 1000000,
                'mikrotik_profile' => 'profile-200mbps',
                'priority' => 2,
                'sort_order' => 7,
            ],
        ];

        foreach ($packages as $package) {
            Package::create(array_merge($package, ['is_active' => true]));
        }
    }
}
