<?php

namespace App\Support;

use App\Enums\StatusAktif;
use App\Enums\StatusJadwal;
use App\Enums\StatusPengajuan;
use App\Enums\StatusTagihan;
use App\Models\JadwalPembelian;
use App\Models\PengajuanPembelian;
use App\Models\PenyesuaianTagihan;
use App\Models\SuratRekomendasi;
use App\Models\Tagihan;
use Illuminate\Support\Facades\Cache;

/** Cache terpusat untuk seluruh lencana notifikasi sidebar Admin (otomatis di-flush saat data berubah). */
class LencanaCache
{
    private const KEY = 'lencana.admin.v1';

    /**
     * @return array{
     *     pengajuan: int,
     *     tagihan: int,
     *     jadwal: int,
     *     jadwal_ditunda: bool,
     *     surat: int,
     *     surat_kedaluwarsa: bool
     * }
     */
    public static function semua(): array
    {
        return Cache::remember(self::KEY, 30, function (): array {
            $hariIni = today()->toDateString();
            $batas14Hari = today()->addDays(14)->toDateString();

            $pengajuan = PengajuanPembelian::query()
                ->where('status', StatusPengajuan::MenungguPersetujuan->value)
                ->count();

            $tagihan = Tagihan::query()->where('status', StatusTagihan::BelumDibayar->value)->count()
                + PenyesuaianTagihan::query()->where('diterapkan', false)->count();

            $jadwalRow = JadwalPembelian::query()
                ->whereIn('status', [StatusJadwal::Terjadwal->value, StatusJadwal::Ditunda->value])
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as ditunda', [StatusJadwal::Ditunda->value])
                ->first();

            $suratRow = SuratRekomendasi::query()
                ->where('status', StatusAktif::Aktif->value)
                ->whereDate('berlaku_sampai', '<=', $batas14Hari)
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN DATE(berlaku_sampai) < ? THEN 1 ELSE 0 END) as kedaluwarsa', [$hariIni])
                ->first();

            return [
                'pengajuan' => $pengajuan,
                'tagihan' => $tagihan,
                'jadwal' => (int) ($jadwalRow->total ?? 0),
                'jadwal_ditunda' => (int) ($jadwalRow->ditunda ?? 0) > 0,
                'surat' => (int) ($suratRow->total ?? 0),
                'surat_kedaluwarsa' => (int) ($suratRow->kedaluwarsa ?? 0) > 0,
            ];
        });
    }

    public static function hapus(): void
    {
        Cache::forget(self::KEY);
    }
}
