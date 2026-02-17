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
                'John Doe',             // nama
                'Jl. Contoh No. 123',   // alamat
                'Kelurahan ABC',        // kelurahan
                '08123456789',          // telepon
                'Paket 10 Mbps',        // paket (harus sesuai nama paket di sistem)
                'Area Utara',           // area (harus sesuai nama area di sistem)
                'Router Pusat',         // router (harus sesuai nama router di sistem)
                'ODP-001',              // odp (harus sesuai nama/kode ODP di sistem, khusus PPPoE)
                'Penagih A',            // penagih (harus sesuai nama penagih di sistem)
                'pppoe',                // tipe_koneksi (pppoe/static/hotspot)
                'john_pppoe',           // pppoe_username (untuk PPPoE)
                '10.10.10.100',         // static_ip (untuk tipe koneksi static)
                'active',               // status (active/isolated/suspended/terminated)
                '0',                    // hutang
                '15-01-2024',           // tanggal_gabung (DD-MM-YYYY)
                '1',                    // tanggal_tagih (1-28)
                '3',                    // rapel_bulan (jumlah bulan toleransi rapel)
                '01-03-2026',           // mulai_ditagih (DD-MM-YYYY, kosongkan jika langsung ditagih)
                'none',                 // diskon_tipe (none/nominal/percentage)
                '0',                    // diskon_nilai (Rp atau %, sesuai tipe)
                'tidak',                // ppn (ya/tidak)
            ],
        ];
    }

    /**
     * Column headings
     */
    public function headings(): array
    {
        return [
            'nama',
            'alamat',
            'kelurahan',
            'telepon',
            'paket',
            'area',
            'router',
            'odp',
            'penagih',
            'tipe_koneksi',
            'pppoe_username',
            'static_ip',
            'status',
            'hutang',
            'tanggal_gabung',
            'tanggal_tagih',
            'rapel_bulan',
            'mulai_ditagih',
            'diskon_tipe',
            'diskon_nilai',
            'ppn',
        ];
    }

    /**
     * Style the worksheet
     */
    public function styles(Worksheet $sheet)
    {
        // Style header row (A-U = 21 columns)
        $sheet->getStyle('A1:U1')->applyFromArray([
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
        $sheet->getStyle('A2:U2')->applyFromArray([
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
        $sheet->getComment('A1')->getText()->createTextRun('WAJIB DIISI');
        $sheet->getComment('D1')->getText()->createTextRun('Format: 08xxxxxxxxxx');
        $sheet->getComment('E1')->getText()->createTextRun('Harus sesuai nama paket di sistem');
        $sheet->getComment('F1')->getText()->createTextRun('Harus sesuai nama area di sistem');
        $sheet->getComment('G1')->getText()->createTextRun('Harus sesuai nama router di sistem');
        $sheet->getComment('H1')->getText()->createTextRun('Harus sesuai nama/kode ODP (khusus PPPoE)');
        $sheet->getComment('I1')->getText()->createTextRun('Harus sesuai nama penagih di sistem');
        $sheet->getComment('J1')->getText()->createTextRun('pppoe, static, atau hotspot');
        $sheet->getComment('K1')->getText()->createTextRun('Untuk tipe koneksi PPPoE');
        $sheet->getComment('L1')->getText()->createTextRun('Untuk tipe koneksi static');
        $sheet->getComment('M1')->getText()->createTextRun('active, isolated, suspended, terminated');
        $sheet->getComment('O1')->getText()->createTextRun('Format: DD-MM-YYYY (contoh: 02-01-2025)');
        $sheet->getComment('P1')->getText()->createTextRun('Tanggal 1-28');
        $sheet->getComment('Q1')->getText()->createTextRun('Jumlah bulan toleransi rapel (default 3)');
        $sheet->getComment('R1')->getText()->createTextRun('Format: DD-MM-YYYY. Kosongkan jika langsung ditagih');
        $sheet->getComment('S1')->getText()->createTextRun('none = tanpa diskon, nominal = Rp, percentage = %');
        $sheet->getComment('T1')->getText()->createTextRun('Nilai diskon (Rp atau %) sesuai tipe diskon');
        $sheet->getComment('U1')->getText()->createTextRun('ya = dikenakan PPN 11%, tidak = tanpa PPN');

        return [];
    }

    /**
     * Column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 25,  // nama
            'B' => 35,  // alamat
            'C' => 20,  // kelurahan
            'D' => 15,  // telepon
            'E' => 20,  // paket
            'F' => 20,  // area
            'G' => 20,  // router
            'H' => 15,  // odp
            'I' => 20,  // penagih
            'J' => 12,  // tipe_koneksi
            'K' => 20,  // pppoe_username
            'L' => 15,  // static_ip
            'M' => 12,  // status
            'N' => 15,  // hutang
            'O' => 15,  // tanggal_gabung
            'P' => 12,  // tanggal_tagih
            'Q' => 12,  // rapel_bulan
            'R' => 15,  // mulai_ditagih
            'S' => 15,  // diskon_tipe
            'T' => 15,  // diskon_nilai
            'U' => 10,  // ppn
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
