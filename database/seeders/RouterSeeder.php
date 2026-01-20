<?php

namespace Database\Seeders;

use App\Models\Router;
use Illuminate\Database\Seeder;

class RouterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $routers = [
            [
                'name' => 'Router Pusat',
                'description' => 'Router utama di kantor pusat',
                'ip_address' => '192.168.1.1',
                'api_port' => 8728,
                'username' => 'admin',
                'password' => 'admin123',
                'identity' => 'MikroTik-Pusat',
                'version' => '7.12',
                'model' => 'CCR1036-12G-4S',
                'is_active' => true,
            ],
            [
                'name' => 'Router Area Timur',
                'description' => 'Router distribusi area timur',
                'ip_address' => '192.168.2.1',
                'api_port' => 8728,
                'username' => 'admin',
                'password' => 'admin123',
                'identity' => 'MikroTik-Timur',
                'version' => '7.12',
                'model' => 'RB1100AHx4',
                'is_active' => true,
            ],
            [
                'name' => 'Router Area Barat',
                'description' => 'Router distribusi area barat',
                'ip_address' => '192.168.3.1',
                'api_port' => 8728,
                'username' => 'admin',
                'password' => 'admin123',
                'identity' => 'MikroTik-Barat',
                'version' => '7.12',
                'model' => 'RB1100AHx4',
                'is_active' => true,
            ],
            [
                'name' => 'Router Area Utara',
                'description' => 'Router distribusi area utara',
                'ip_address' => '192.168.4.1',
                'api_port' => 8728,
                'username' => 'admin',
                'password' => 'admin123',
                'identity' => 'MikroTik-Utara',
                'version' => '7.11',
                'model' => 'RB3011UiAS-RM',
                'is_active' => true,
            ],
            [
                'name' => 'Router Area Selatan',
                'description' => 'Router distribusi area selatan',
                'ip_address' => '192.168.5.1',
                'api_port' => 8728,
                'username' => 'admin',
                'password' => 'admin123',
                'identity' => 'MikroTik-Selatan',
                'version' => '7.11',
                'model' => 'RB3011UiAS-RM',
                'is_active' => true,
            ],
        ];

        foreach ($routers as $router) {
            Router::create($router);
        }
    }
}
