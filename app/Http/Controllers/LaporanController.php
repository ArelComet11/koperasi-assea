<?php

namespace App\Http\Controllers;

use App\Exports\LaporanExport;
use App\Exports\LaporanPenjualanExport;
use App\Exports\LaporanPenjualanHarianExport;
use App\Exports\LaporanRealisasiExport;
use App\Exports\LaporanStokSolarExport;
use App\Exports\LaporanSuratRekomendasiExport;
use App\Services\AuditService;
use App\Services\LaporanService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LaporanController extends Controller
{
    public function export(Request $request, LaporanService $svc)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        [$jenis, $filter] = $this->validasi($request);
        $laporan = $svc->bangun($jenis, $filter);
        AuditService::catat('laporan.diekspor', null, null, ['jenis' => $jenis] + $filter, $laporan['judul']);

        $export = match ($jenis) {
            'surat' => new LaporanSuratRekomendasiExport($laporan),
            'penjualan_harian' => new LaporanPenjualanHarianExport($laporan),
            'penjualan' => new LaporanPenjualanExport($laporan),
            'stok_solar' => new LaporanStokSolarExport($laporan),
            'realisasi' => new LaporanRealisasiExport($laporan),
            default => new LaporanExport($laporan),
        };

        return Excel::download($export, 'laporan-' . $jenis . '-' . now()->format('Ymd-His') . '.xlsx');
    }

    public function cetak(Request $request, LaporanService $svc)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        [$jenis, $filter] = $this->validasi($request);
        $laporan = $svc->bangun($jenis, $filter);
        $view = match ($jenis) {
            'surat' => 'cetak.laporan-surat',
            'penjualan_harian' => 'cetak.laporan-penjualan-harian',
            'penjualan' => 'cetak.laporan-penjualan',
            'stok_solar' => 'cetak.laporan-stok-solar',
            'realisasi' => 'cetak.laporan-realisasi',
            default => 'cetak.laporan',
        };

        return view($view, ['laporan' => $laporan]);
    }

    /** @return array{0:string,1:array} */
    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'jenis' => ['required', 'string'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'anggota_id' => ['nullable', 'integer'],
            'kapal_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:30'],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);

        abort_unless(array_key_exists($data['jenis'], LaporanService::jenisUntuk($request->user())), 403);

        return [$data['jenis'], array_filter(array_diff_key($data, ['jenis' => 1]), fn ($v) => $v !== null && $v !== '')];
    }
}
