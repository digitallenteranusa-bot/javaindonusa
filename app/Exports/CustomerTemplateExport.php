<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class CustomerTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    /**
     * Sample data rows
     */
    public function array(): array
    {
        return [
            [
                '',                     // id_pelanggan (auto generate)
                'John Doe',             // nama
                'Jl. Contoh No. 123',   // alamat
                '01/02',                // rt_rw
                'Kelurahan ABC',        // kelurahan
                'Kecamatan XYZ',        // kecamatan
                '08123456789',          // telepon
                '08198765432',          // telepon_alternatif
                'john@email.com',       // email
                '3201234567890001',     // nik
                'Paket 10 Mbps',        // paket (harus sesuai nama paket di sistem)
                'Area Utara',           // area (harus sesuai nama area di sistem)
                'Router Pusat',         // router (harus sesuai nama router di sistem)
                'ODP-001',              // odp (harus sesuai nama/kode ODP di sistem, khusus PPPoE)
                'Penagih A',            // penagih (harus sesuai nama penagih di sistem)
                'pppoe',                // tipe_koneksi (pppoe/static/hotspot)
                'john_pppoe',           // pppoe_username (untuk PPPoE)
                'password123',          // pppoe_password (untuk PPPoE)
                '192.168.1.100',        // ip_address
                '10.10.10.100',         // static_ip (untuk tipe koneksi static)
                'AA:BB:CC:DD:EE:FF',    // mac_address
                'ZTE-F609',             // onu_serial
                'active',               // status (active/isolated/suspended/terminated)
                'postpaid',             // tipe_billing (prepaid/postpaid)
                '0',                    // hutang
                '2024-01-15',           // tanggal_gabung (YYYY-MM-DD)
                '1',                    // tanggal_tagih (1-28)
                'regular',              // perilaku_bayar (regular/rapel/problematic)
                '0',                    // is_rapel (0/1)
                '3',                    // rapel_bulan (jumlah bulan toleransi rapel)
                'Catatan pelanggan',    // catatan
                '-6.123456',            // latitude
                '106.123456',           // longitude
            ],
        ];
    }

    /**
     * Column headings
     */
    public function headings(): array
    {
        return [
            'id_pelanggan',
            'nama',
            'alamat',
            'rt_rw',
            'kelurahan',
            'kecamatan',
            'telepon',
            'telepon_alternatif',
            'email',
            'nik',
            'paket',
            'area',
            'router',
            'odp',
            'penagih',
            'tipe_koneksi',
            'pppoe_username',
            'pppoe_password',
            'ip_address',
            'static_ip',
            'mac_address',
            'onu_serial',
            'status',
            'tipe_billing',
            'hutang',
            'tanggal_gabung',
            'tanggal_tagih',
            'perilaku_bayar',
            'is_rapel',
            'rapel_bulan',
            'catatan',
            'latitude',
            'longitude',
        ];
    }

    /**
     * Style the worksheet
     */
    public function styles(Worksheet $sheet)
    {
        // Style header row (A-AG = 33 columns)
        $sheet->getStyle('A1:AG1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        // Style data row (sample)
        $sheet->getStyle('A2:AG2')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FEF3C7'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        // Add comments/notes
        $sheet->getComment('A1')->getText()->createTextRun('Kosongkan untuk auto-generate');
        $sheet->getComment('B1')->getText()->createTextRun('WAJIB DIISI');
        $sheet->getComment('G1')->getText()->createTextRun('Format: 08xxxxxxxxxx');
        $sheet->getComment('K1')->getText()->createTextRun('Harus sesuai nama paket di sistem');
        $sheet->getComment('L1')->getText()->createTextRun('Harus sesuai nama area di sistem');
        $sheet->getComment('M1')->getText()->createTextRun('Harus sesuai nama router di sistem');
        $sheet->getComment('N1')->getText()->createTextRun('Harus sesuai nama/kode ODP di sistem (khusus PPPoE)');
        $sheet->getComment('O1')->getText()->createTextRun('Harus sesuai nama penagih di sistem');
        $sheet->getComment('P1')->getText()->createTextRun('pppoe, static, atau hotspot');
        $sheet->getComment('Q1')->getText()->createTextRun('Untuk tipe koneksi PPPoE');
        $sheet->getComment('T1')->getText()->createTextRun('Untuk tipe koneksi static');
        $sheet->getComment('W1')->getText()->createTextRun('active, isolated, suspended, terminated');
        $sheet->getComment('X1')->getText()->createTextRun('prepaid atau postpaid');
        $sheet->getComment('Z1')->getText()->createTextRun('Format: YYYY-MM-DD');
        $sheet->getComment('AA1')->getText()->createTextRun('Tanggal 1-28');
        $sheet->getComment('AB1')->getText()->createTextRun('regular, rapel, atau problematic');
        $sheet->getComment('AC1')->getText()->createTextRun('0 = tidak, 1 = ya');
        $sheet->getComment('AD1')->getText()->createTextRun('Jumlah bulan toleransi rapel (default 3)');

        return [];
    }

    /**
     * Column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 15,  // id_pelanggan
            'B' => 25,  // nama
            'C' => 35,  // alamat
            'D' => 10,  // rt_rw
            'E' => 20,  // kelurahan
            'F' => 20,  // kecamatan
            'G' => 15,  // telepon
            'H' => 15,  // telepon_alternatif
            'I' => 25,  // email
            'J' => 20,  // nik
            'K' => 20,  // paket
            'L' => 20,  // area
            'M' => 20,  // router
            'N' => 15,  // odp
            'O' => 20,  // penagih
            'P' => 12,  // tipe_koneksi
            'Q' => 20,  // pppoe_username
            'R' => 15,  // pppoe_password
            'S' => 15,  // ip_address
            'T' => 15,  // static_ip
            'U' => 20,  // mac_address
            'V' => 20,  // onu_serial
            'W' => 12,  // status
            'X' => 12,  // tipe_billing
            'Y' => 15,  // hutang
            'Z' => 15,  // tanggal_gabung
            'AA' => 12, // tanggal_tagih
            'AB' => 14, // perilaku_bayar
            'AC' => 10, // is_rapel
            'AD' => 12, // rapel_bulan
            'AE' => 30, // catatan
            'AF' => 12, // latitude
            'AG' => 12, // longitude
        ];
    }

    /**
     * Sheet title
     */
    public function title(): string
    {
        return 'Template Import Pelanggan';
    }
}
