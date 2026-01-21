<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Area;
use App\Models\Router;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = Package::all();
        $areas = Area::whereNotNull('parent_id')->get(); // Sub-areas only
        $routers = Router::all();
        $collectors = User::where('role', 'penagih')->get();

        // Indonesian names for realistic data
        $firstNames = ['Andi', 'Budi', 'Citra', 'Dewi', 'Eka', 'Fajar', 'Gita', 'Hadi', 'Indra', 'Joko',
            'Kartini', 'Lina', 'Made', 'Nina', 'Oki', 'Putri', 'Ratna', 'Sari', 'Tono', 'Udin',
            'Vina', 'Wati', 'Yani', 'Zaki', 'Ahmad', 'Bambang', 'Cahyo', 'Dian', 'Endang', 'Faisal',
            'Galih', 'Hendra', 'Irwan', 'Joni', 'Kiki', 'Laras', 'Mulyadi', 'Nanda', 'Oka', 'Pandu',
            'Qori', 'Rina', 'Surya', 'Tuti', 'Ujang', 'Vera', 'Wahyu', 'Xena', 'Yudi', 'Zainal'];

        $lastNames = ['Santoso', 'Wijaya', 'Kusuma', 'Pratama', 'Setiawan', 'Hidayat', 'Rahman', 'Putra',
            'Wibowo', 'Saputra', 'Nugroho', 'Susanto', 'Hartono', 'Suryadi', 'Gunawan', 'Permana',
            'Firmansyah', 'Budiman', 'Sutrisno', 'Prabowo', 'Halim', 'Sukma', 'Lestari', 'Anggraini',
            'Rahayu', 'Suharto', 'Supriadi', 'Kurniawan', 'Ramadhan', 'Hakim'];

        $streets = ['Jl. Mawar', 'Jl. Melati', 'Jl. Anggrek', 'Jl. Dahlia', 'Jl. Kenanga', 'Jl. Flamboyan',
            'Jl. Cemara', 'Jl. Akasia', 'Jl. Mangga', 'Jl. Rambutan', 'Jl. Durian', 'Jl. Salak',
            'Jl. Merdeka', 'Jl. Sudirman', 'Jl. Thamrin', 'Jl. Gatot Subroto', 'Jl. Ahmad Yani',
            'Jl. Diponegoro', 'Jl. Imam Bonjol', 'Jl. Hayam Wuruk'];

        // Generate 10 customers (sample data - mudah dihapus)
        $customerCount = 10;

        for ($i = 1; $i <= $customerCount; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $name = $firstName . ' ' . $lastName;

            $area = $areas->random();
            $package = $packages->random();
            $router = Router::find($area->router_id) ?? $routers->random();
            $collector = User::find($area->collector_id) ?? $collectors->random();

            $street = $streets[array_rand($streets)];
            $houseNo = rand(1, 150);
            $rt = str_pad(rand(1, 15), 2, '0', STR_PAD_LEFT);
            $rw = str_pad(rand(1, 10), 2, '0', STR_PAD_LEFT);

            // Semua pelanggan aktif tanpa hutang agar mudah dihapus
            $status = 'active';
            $joinDate = Carbon::now()->subDays(rand(30, 365));
            $totalDebt = 0;
            $isRapel = false;
            $rapelAmount = null;
            $rapelMonths = null;

            $phone = '08' . rand(11, 99) . rand(1000000, 9999999);

            Customer::create([
                'customer_id' => sprintf('JI-%05d', $i),
                'name' => $name,
                'address' => "{$street} No. {$houseNo}",
                'rt_rw' => "{$rt}/{$rw}",
                'kelurahan' => $area->name,
                'kecamatan' => $area->parent?->name ?? $area->name,
                'phone' => $phone,
                'phone_alt' => rand(1, 100) <= 30 ? '08' . rand(11, 99) . rand(1000000, 9999999) : null,
                'email' => rand(1, 100) <= 50 ? strtolower($firstName) . '.' . strtolower($lastName) . rand(1, 99) . '@gmail.com' : null,
                'nik' => rand(1, 100) <= 70 ? '31' . rand(10, 99) . rand(10, 99) . rand(100000, 999999) . rand(1000, 9999) : null,
                'package_id' => $package->id,
                'area_id' => $area->id,
                'router_id' => $router->id,
                'collector_id' => $collector->id,
                'pppoe_username' => sprintf('ji%05d', $i),
                'pppoe_password' => 'pppoe' . rand(1000, 9999),
                'ip_address' => '10.' . rand(1, 254) . '.' . rand(1, 254) . '.' . rand(1, 254),
                'mac_address' => implode(':', array_map(fn() => strtoupper(dechex(rand(0, 255))), range(1, 6))),
                'onu_serial' => rand(1, 100) <= 60 ? 'ZTEG' . strtoupper(substr(md5(rand()), 0, 8)) : null,
                'status' => $status,
                'total_debt' => $totalDebt,
                'join_date' => $joinDate,
                'isolation_date' => $status === 'isolated' ? Carbon::now()->subDays(rand(1, 30)) : null,
                'isolation_reason' => $status === 'isolated' ? 'Tunggakan lebih dari 2 bulan' : null,
                'billing_type' => 'postpaid',
                'billing_date' => 1,
                'is_rapel' => $isRapel,
                'rapel_amount' => $rapelAmount,
                'rapel_months' => $rapelMonths,
                'latitude' => -6.2 + (rand(-500, 500) / 10000),
                'longitude' => 106.8 + (rand(-500, 500) / 10000),
            ]);
        }
    }
}
