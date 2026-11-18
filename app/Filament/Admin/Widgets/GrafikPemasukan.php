<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Support\BulananChart;
use App\Models\Pembayaran;

class GrafikPemasukan extends BulananChart
{
    protected static string $izin = 'tagihan.view';
    protected static string $judul = 'Pemasukan per bulan (Rp)';
    protected static string $label = 'Pemasukan';
    protected static string $warna = '#E5B51B';
    protected static string $jenis = 'bar';

    protected function deret(int $tahun): array
    {
        $rows = Pembayaran::query()->where('status', 'sah')->whereYear('tanggal_pembayaran', $tahun)->get(['tanggal_pembayaran', 'jumlah']);

        return $this->perBulan($rows, 'tanggal_pembayaran', 'jumlah');
    }
}
