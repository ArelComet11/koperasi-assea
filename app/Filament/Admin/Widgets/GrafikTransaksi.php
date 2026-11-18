<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Support\BulananChart;
use App\Models\PengajuanPembelian;

class GrafikTransaksi extends BulananChart
{
    protected static string $izin = 'transaksi.view';
    protected static string $judul = 'Transaksi selesai per bulan';
    protected static string $label = 'Transaksi';
    protected static string $warna = '#0B6FA4';
    protected static string $jenis = 'line';

    protected function deret(int $tahun): array
    {
        $rows = PengajuanPembelian::query()->where('status', 'selesai')->whereYear('selesai_pada', $tahun)->get(['selesai_pada']);

        return $this->perBulan($rows, 'selesai_pada');
    }
}
