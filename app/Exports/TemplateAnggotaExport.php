<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TemplateAnggotaExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            [
                'kode_anggota',
                'nama_lengkap',
                'username',
                'password',
                'telepon',
                'email',
                'tanggal_bergabung',
                'alamat',
                'keterangan',
            ],
            [
                'ANG-0101',
                'Budi Santoso',
                'budi_santoso',
                'Rahasia123',
                '081234567890',
                'budi@contoh.id',
                today()->format('Y-m-d'),
                'Jl. Pelabuhan Nelayan No. 12',
                'Contoh baris dengan kode & password diisi',
            ],
            [
                '',
                'Siti Aminah',
                'siti_aminah',
                '',
                '085298765432',
                '',
                today()->format('d/m/Y'),
                'Desa Pesisir RT 02',
                'Kode otomatis & pakai password bawaan bila dikosongkan',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '075B37']],
            ],
        ];
    }

    public function title(): string
    {
        return 'Template Anggota';
    }
}
