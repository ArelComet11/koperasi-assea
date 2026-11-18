<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Support\BulananChart;
use App\Models\PenyaluranBbm;

class GrafikPenyaluran extends BulananChart
{
    protected static string $izin = 'penerimaan.view';
    protected static string $judul = 'Penyaluran BBM per bulan (liter)';
    protected static string $label = 'Liter disalurkan';
    protected static string $warna = '#075B37';
    protected static string $jenis = 'bar';

    protected function deret(int $tahun): array
    {
        $rows = PenyaluranBbm::query()->whereYear('tanggal_penyaluran', $tahun)->get(['tanggal_penyaluran', 'liter_aktual']);

        return $this->perBulan($rows, 'tanggal_penyaluran', 'liter_aktual');
    }
}
