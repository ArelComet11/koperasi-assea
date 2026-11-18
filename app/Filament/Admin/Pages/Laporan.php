<?php

namespace App\Filament\Admin\Pages;

use App\Models\Anggota;
use App\Models\Kapal;
use App\Services\LaporanService;
use Filament\Pages\Page;

class Laporan extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Laporan';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Laporan';
    protected static ?string $slug = 'laporan';

    protected string $view = 'filament.pages.laporan';

    public static function canAccess(): bool
    {
        return LaporanService::jenisUntuk(auth()->user()) !== [];
    }

    protected function getViewData(): array
    {
        $jenisBoleh = LaporanService::jenisUntuk(auth()->user());
        $jenis = (string) request()->query('jenis', array_key_first($jenisBoleh));
        $filter = array_filter(request()->only(['dari', 'sampai', 'anggota_id', 'kapal_id', 'status', 'cari']), fn ($v) => $v !== null && $v !== '');

        $laporan = null;
        $galat = null;
        if (isset($jenisBoleh[$jenis])) {
            try {
                $laporan = app(LaporanService::class)->bangun($jenis, $filter);
            } catch (\Throwable $e) {
                $galat = 'Filter tidak valid. Periksa tanggal dan isian lainnya.';
            }
        }

        $query = http_build_query(['jenis' => $jenis] + $filter);

        return [
            'jenisBoleh' => $jenisBoleh, 'jenis' => $jenis, 'filter' => $filter, 'laporan' => $laporan, 'galat' => $galat,
            'anggotaOpsi' => Anggota::query()->orderBy('nama_lengkap')->limit(500)->pluck('nama_lengkap', 'id'),
            'kapalOpsi' => Kapal::query()->orderBy('nama_kapal')->limit(500)->pluck('nama_kapal', 'id'),
            'urlExport' => route('laporan.export') . '?' . $query,
            'urlCetak' => route('laporan.cetak') . '?' . $query,
        ];
    }
}
