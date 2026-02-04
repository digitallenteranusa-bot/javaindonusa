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
                'Penagih A',            // penagih (harus sesuai nama penagih di sistem)
                'john_pppoe',           // pppoe_username
                'password123',          // pppoe_password
                '192.168.1.100',        // ip_address
                'AA:BB:CC:DD:EE:FF',    // mac_address
                'TP-Link Archer C6',    // merk_router
                'active',               // status (active/isolated/suspended/terminated)
                '0',                    // hutang
                '2024-01-15',           // tanggal_gabung (YYYY-MM-DD)
                '1',                    // tanggal_tagih (1-28)
                'pppoe',                // tipe_koneksi (pppoe/static)
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
            'penagih',
            'pppoe_username',
            'pppoe_password',
            'ip_address',
            'mac_address',
            'merk_router',
            'status',
            'hutang',
            'tanggal_gabung',
            'tanggal_tagih',
            'tipe_koneksi',
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
        // Style header row
        $sheet->getStyle('A1:AA1')->applyFromArray([
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
        $sheet->getStyle('A2:AA2')->applyFromArray([
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
        $sheet->getComment('T1')->getText()->createTextRun('active, isolated, suspended, terminated');
        $sheet->getComment('V1')->getText()->createTextRun('Format: YYYY-MM-DD');
        $sheet->getComment('W1')->getText()->createTextRun('Tanggal 1-28');

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
            'N' => 20,  // penagih
            'O' => 20,  // pppoe_username
            'P' => 15,  // pppoe_password
            'Q' => 15,  // ip_address
            'R' => 20,  // mac_address
            'S' => 20,  // onu_serial
            'T' => 12,  // status
            'U' => 15,  // hutang
            'V' => 15,  // tanggal_gabung
            'W' => 12,  // tanggal_tagih
            'X' => 12,  // tipe_koneksi
            'Y' => 30,  // catatan
            'Z' => 12,  // latitude
            'AA' => 12, // longitude
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
