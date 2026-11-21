<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Support\BulananChart;
use App\Models\PengajuanPembelian;
use Carbon\Carbon;

class GrafikTransaksi extends BulananChart
{
    protected static string $izin = 'transaksi.view';
    protected static string $judul = 'Transaksi selesai per bulan';
    protected static string $label = 'Transaksi';
    protected static string $warna = '#0B6FA4';
    protected static string $jenis = 'line';

    protected function deret(int $tahun): array
    {
        $awal = Carbon::create($tahun, 1, 1)->startOfDay();
        $akhir = Carbon::create($tahun, 12, 31)->endOfDay();
        $rows = PengajuanPembelian::query()
            ->where('status', 'selesai')
            ->whereBetween('selesai_pada', [$awal, $akhir])
            ->get(['selesai_pada']);

        return $this->perBulan($rows, 'selesai_pada');
    }
}
