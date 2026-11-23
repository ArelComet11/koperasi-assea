<?php

namespace App\Filament\Admin\Pages;

use App\Enums\StatusAktif;
use App\Enums\StatusPenerimaan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusTagihan;
use App\Models\Anggota;
use App\Models\Kapal;
use App\Services\LaporanService;
use App\Support\Otorisasi;
use Carbon\Carbon;
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
        return LaporanService::jenisUntuk(Otorisasi::user()) !== [];
    }

    /** Opsi dropdown status yang relevan per jenis laporan. */
    private static function opsiStatus(string $jenis): array
    {
        return match ($jenis) {
            'anggota', 'kapal' => StatusAktif::opsi(),
            'surat' => ['aktif' => 'Aktif', 'belum_berlaku' => 'Belum berlaku', 'kedaluwarsa' => 'Kedaluwarsa', 'nonaktif' => 'Nonaktif'],
            'penerimaan' => StatusPenerimaan::opsi(),
            'transaksi' => StatusPengajuan::opsi(),
            'keuangan' => [
                StatusTagihan::BelumDibayar->value => StatusTagihan::BelumDibayar->getLabel(),
                StatusTagihan::Lunas->value => StatusTagihan::Lunas->getLabel(),
            ],
            default => [],
        };
    }

    protected function getViewData(): array
    {
        $jenisBoleh = LaporanService::jenisUntuk(Otorisasi::user());
        $jenis = (string) request()->query('jenis', array_key_first($jenisBoleh));
        $filter = array_filter(request()->only(['dari', 'sampai', 'anggota_id', 'kapal_id', 'status', 'cari']), fn ($v) => $v !== null && $v !== '');
        $statusOpsi = self::opsiStatus($jenis);
        if (! empty($filter['status']) && $statusOpsi !== [] && ! array_key_exists($filter['status'], $statusOpsi)) {
            unset($filter['status']);
        }

        $laporan = null;
        $galat = null;
        if (! isset($jenisBoleh[$jenis])) {
            $galat = 'Anda tidak memiliki izin untuk membuka jenis laporan tersebut.';
        } else {
            try {
                if (! empty($filter['dari']) && ! empty($filter['sampai']) && Carbon::parse($filter['dari'])->gt(Carbon::parse($filter['sampai']))) {
                    $galat = 'Filter tidak valid. Tanggal "Dari" tidak boleh melebihi tanggal "Sampai".';
                } else {
                    $laporan = app(LaporanService::class)->bangun($jenis, $filter);
                }
            } catch (\Throwable $e) {
                $galat = 'Filter tidak valid. Periksa tanggal dan isian lainnya.';
            }
        }

        $tampilAnggota = in_array($jenis, ['anggota', 'kapal', 'surat', 'penyaluran', 'transaksi', 'keuangan'], true);
        $tampilKapal = in_array($jenis, ['kapal', 'surat', 'penyaluran', 'transaksi', 'keuangan'], true);
        $query = http_build_query(['jenis' => $jenis] + $filter);

        return [
            'jenisBoleh' => $jenisBoleh, 'jenis' => $jenis, 'filter' => $filter, 'laporan' => $laporan, 'galat' => $galat,
            'statusOpsi' => $statusOpsi,
            'tampilAnggota' => $tampilAnggota,
            'tampilKapal' => $tampilKapal,
            'anggotaOpsi' => $tampilAnggota ? Anggota::query()->orderBy('nama_lengkap')->limit(500)->pluck('nama_lengkap', 'id') : collect(),
            'kapalOpsi' => $tampilKapal ? Kapal::query()->orderBy('nama_kapal')->limit(500)->pluck('nama_kapal', 'id') : collect(),
            'urlExport' => route('laporan.export') . '?' . $query,
            'urlCetak' => route('laporan.cetak') . '?' . $query,
        ];
    }
}
