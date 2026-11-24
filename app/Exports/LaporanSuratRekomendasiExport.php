<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LaporanSuratRekomendasiExport implements WithMultipleSheets
{
    /** @param array{jenis:string,judul:string,periode:string,bulanan:array<int,array<string,mixed>>} $laporan */
    public function __construct(private array $laporan)
    {
    }

    /** @return array<int, LaporanSuratRekomendasiSheet> */
    public function sheets(): array
    {
        $bulanan = $this->laporan['bulanan'] ?? [];
        if ($bulanan === []) {
            return [
                new LaporanSuratRekomendasiSheet([
                    'bulan_label' => now()->translatedFormat('F'),
                    'sheet_title' => now()->translatedFormat('F Y'),
                    'rows' => [],
                    'total_harian' => array_fill(1, 31, 0.0),
                    'total_vol_rekom' => 0.0,
                    'total_penyaluran' => 0.0,
                    'total_vs_rekom' => 0.0,
                ]),
            ];
        }

        return array_map(
            fn (array $bulan) => new LaporanSuratRekomendasiSheet($bulan),
            $bulanan
        );
    }
}
