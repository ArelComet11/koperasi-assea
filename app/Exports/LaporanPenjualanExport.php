<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;

class LaporanPenjualanExport implements WithMultipleSheets, WithPreCalculateFormulas
{
    /** @param array{jenis:string,judul:string,periode:string,bulanan:array<int,array<string,mixed>>} $laporan */
    public function __construct(private array $laporan)
    {
    }

    /** @return array<int, LaporanPenjualanSheet> */
    public function sheets(): array
    {
        $bulanan = $this->laporan['bulanan'] ?? [];
        if ($bulanan === []) {
            $now = now();

            return [
                new LaporanPenjualanSheet([
                    'bulan_nama' => $now->translatedFormat('F'),
                    'tahun' => (int) $now->year,
                    'sheet_title' => $now->translatedFormat('F'),
                    'tanggal_tanda_tangan' => $now->copy()->endOfMonth()->translatedFormat('j F Y'),
                    'rows' => [],
                    'total_harian' => array_fill(1, 31, 0.0),
                    'total_penjualan' => 0.0,
                ]),
            ];
        }

        return array_map(
            fn (array $bulan) => new LaporanPenjualanSheet($bulan),
            $bulanan
        );
    }
}
