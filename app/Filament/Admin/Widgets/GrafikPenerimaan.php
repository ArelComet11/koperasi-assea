<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Support\BulananChart;
use App\Models\PenerimaanBbm;

class GrafikPenerimaan extends BulananChart
{
    protected static string $izin = 'penerimaan.view';
    protected static string $judul = 'Penerimaan BBM per bulan (liter)';
    protected static string $label = 'Liter diterima';
    protected static string $warna = '#078B4B';
    protected static string $jenis = 'bar';

    protected function deret(int $tahun): array
    {
        $rows = PenerimaanBbm::query()->where('status', 'terkonfirmasi')->whereYear('tanggal_penerimaan', $tahun)->get(['tanggal_penerimaan', 'liter']);

        return $this->perBulan($rows, 'tanggal_penerimaan', 'liter');
    }
}
