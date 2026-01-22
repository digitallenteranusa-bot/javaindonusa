<?php

namespace Database\Seeders;

use App\Models\IspInfo;
use Illuminate\Database\Seeder;

class IspInfoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        IspInfo::create([
            'company_name' => 'Java Indonusa Net',
            'tagline' => 'Internet Cepat, Harga Bersahabat',
            'legal_name' => 'PT Java Indonusa Network',
            'npwp' => '12.345.678.9-012.345',
            'nib' => '1234567890123',
            'phone_primary' => '081357971531',
            'phone_secondary' => '081357971531',
            'whatsapp_number' => '081357971531',
            'email' => 'info@javaindonusa.net',
            'website' => 'https://javaindonusa.net',
            'address' => 'Jl. Raya Utama No. 123, Kelurahan Sukamaju',
            'city' => 'Jakarta Timur',
            'province' => 'DKI Jakarta',
            'postal_code' => '13450',
            'bank_accounts' => [
                [
                    'bank' => 'BCA',
                    'account' => '1234567890',
                    'name' => 'PT Java Indonusa Network',
                    'branch' => 'KCP Cakung',
                ],
                [
                    'bank' => 'Mandiri',
                    'account' => '0987654321',
                    'name' => 'PT Java Indonusa Network',
                    'branch' => 'KC Jakarta Timur',
                ],
                [
                    'bank' => 'BRI',
                    'account' => '1122334455',
                    'name' => 'PT Java Indonusa Network',
                    'branch' => 'Unit Cakung',
                ],
            ],
            'ewallet_accounts' => [
                [
                    'type' => 'Dana',
                    'number' => '081357971531',
                    'name' => 'Java Indonusa',
                ],
                [
                    'type' => 'OVO',
                    'number' => '081357971531',
                    'name' => 'Java Indonusa',
                ],
                [
                    'type' => 'GoPay',
                    'number' => '081357971531',
                    'name' => 'Java Indonusa',
                ],
            ],
            'operational_hours' => [
                'monday' => ['open' => '08:00', 'close' => '17:00'],
                'tuesday' => ['open' => '08:00', 'close' => '17:00'],
                'wednesday' => ['open' => '08:00', 'close' => '17:00'],
                'thursday' => ['open' => '08:00', 'close' => '17:00'],
                'friday' => ['open' => '08:00', 'close' => '17:00'],
                'saturday' => ['open' => '08:00', 'close' => '12:00'],
                'sunday' => ['closed' => true],
            ],
            'invoice_footer' => 'Terima kasih telah menggunakan layanan Java Indonusa Net. Pembayaran dapat dilakukan melalui transfer bank atau e-wallet yang tertera di atas.',
            'isolation_message' => 'Akses internet Anda telah diisolir karena tunggakan pembayaran. Silakan segera melakukan pembayaran untuk mengaktifkan kembali layanan Anda.',
            'payment_instructions' => "Cara Pembayaran:\n1. Transfer ke rekening bank yang tertera\n2. Gunakan ID Pelanggan sebagai berita/keterangan\n3. Kirim bukti transfer via WhatsApp\n4. Akses akan dibuka dalam 1x24 jam setelah pembayaran terverifikasi",
        ]);
    }
}
