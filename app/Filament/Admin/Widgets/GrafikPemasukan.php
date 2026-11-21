<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Support\BulananChart;
use App\Models\Pembayaran;
use Carbon\Carbon;

class GrafikPemasukan extends BulananChart
{
    protected static string $izin = 'tagihan.view';
    protected static string $judul = 'Pemasukan per bulan (Rp)';
    protected static string $label = 'Pemasukan';
    protected static string $warna = '#E5B51B';
    protected static string $jenis = 'bar';

    protected function deret(int $tahun): array
    {
        $awal = Carbon::create($tahun, 1, 1)->startOfDay();
        $akhir = Carbon::create($tahun, 12, 31)->endOfDay();
        $rows = Pembayaran::query()
            ->where('status', 'sah')
            ->whereBetween('tanggal_pembayaran', [$awal, $akhir])
            ->get(['tanggal_pembayaran', 'jumlah']);

        return $this->perBulan($rows, 'tanggal_pembayaran', 'jumlah');
    }
}
