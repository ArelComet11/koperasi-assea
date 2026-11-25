<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;

class LaporanStokSolarExport implements WithMultipleSheets, WithPreCalculateFormulas
{
    /** @param array{jenis:string,judul:string,periode:string,bulanan:array<int,array<string,mixed>>} $laporan */
    public function __construct(private array $laporan)
    {
    }

    /** @return array<int, LaporanStokSolarSheet> */
    public function sheets(): array
    {
        $bulanan = $this->laporan['bulanan'] ?? [];
        if ($bulanan === []) {
            $now = now();

            return [
                new LaporanStokSolarSheet([
                    'periode_label' => mb_strtoupper($now->translatedFormat('F')),
                    'sheet_title' => mb_strtoupper($now->translatedFormat('M')),
                    'rows' => [],
                    'total_penerimaan' => 0.0,
                    'total_penjualan' => 0.0,
                ]),
            ];
        }

        return array_map(
            fn (array $bulan) => new LaporanStokSolarSheet($bulan),
            $bulanan
        );
    }
}
