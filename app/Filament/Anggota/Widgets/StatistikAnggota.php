<?php

namespace App\Filament\Anggota\Widgets;

use App\Enums\StatusPengajuan;
use App\Models\JadwalPembelian;
use App\Models\Kapal;
use App\Models\PengajuanPembelian;
use App\Models\PenyaluranBbm;
use App\Models\SuratRekomendasi;
use App\Support\Format;
use App\Support\Otorisasi;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Semua model di sini memakai global scope "milik anggota", sehingga angka hanya milik anggota yang login. */
class StatistikAnggota extends StatsOverviewWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = -2;

    public static function canView(): bool
    {
        return (bool) Otorisasi::user()?->hasRole('anggota');
    }

    protected function getStats(): array
    {
        $bulanIni = PenyaluranBbm::query()->whereHas('pengajuan')
            ->whereBetween('tanggal_penyaluran', [now()->startOfMonth(), now()->endOfMonth()])->sum('liter_aktual');
        $jadwal = JadwalPembelian::query()->whereIn('status', ['terjadwal', 'ditunda'])->where('tanggal_waktu', '>=', now())->orderBy('tanggal_waktu')->first();
        $pengajuan = PengajuanPembelian::query()
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as selesai, SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as diproses',
                [StatusPengajuan::Selesai->value, StatusPengajuan::MenungguPersetujuan->value, StatusPengajuan::Disetujui->value]
            )
            ->first();

        return [
            Stat::make('Jumlah kapal', Kapal::query()->where('status', 'aktif')->count())->icon('heroicon-o-lifebuoy'),
            Stat::make('Surat rekomendasi aktif', SuratRekomendasi::query()->aktifHariIni()->count())->icon('heroicon-o-document-text'),
            Stat::make('Pembelian bulan ini', Format::liter($bulanIni))->icon('heroicon-o-beaker'),
            Stat::make('Total transaksi selesai', (int) ($pengajuan->selesai ?? 0))->icon('heroicon-o-check-badge')->color('success'),
            Stat::make('Pengajuan diproses', (int) ($pengajuan->diproses ?? 0))->icon('heroicon-o-clock')->color('warning'),
            Stat::make('Jadwal berikutnya', $jadwal ? $jadwal->tanggal_waktu->translatedFormat('d M Y, H:i') : 'Belum ada')->icon('heroicon-o-calendar-days'),
        ];
    }
}
