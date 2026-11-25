<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LaporanPenjualanHarianExport implements WithMultipleSheets
{
    /** @param array{jenis:string,judul:string,periode:string,bulanan:array<int,array<string,mixed>>} $laporan */
    public function __construct(private array $laporan)
    {
    }

    /** @return array<int, LaporanPenjualanHarianSheet> */
    public function sheets(): array
    {
        $bulanan = $this->laporan['bulanan'] ?? [];
        if ($bulanan === []) {
            $now = now();

            return [
                new LaporanPenjualanHarianSheet([
                    'periode_label' => mb_strtoupper($now->translatedFormat('F Y')),
                    'sheet_title' => mb_strtoupper($now->translatedFormat('M')),
                    'tanggal_tanda_tangan' => 'Pangkalan Brandan, ' . $now->copy()->endOfMonth()->translatedFormat('j F Y'),
                    'rows' => [],
                    'total_penerimaan' => 0.0,
                    'total_penjualan' => 0.0,
                ]),
            ];
        }

        return array_map(
            fn (array $bulan) => new LaporanPenjualanHarianSheet($bulan),
            $bulanan
        );
    }
}
