<?php

namespace App\Filament\Anggota\Widgets;

use App\Enums\StatusPengajuan;
use App\Models\JadwalPembelian;
use App\Models\Kapal;
use App\Models\PengajuanPembelian;
use App\Models\PenyaluranBbm;
use App\Models\SuratRekomendasi;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Semua model di sini memakai global scope "milik anggota", sehingga angka hanya milik anggota yang login. */
class StatistikAnggota extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasRole('anggota');
    }

    protected function getStats(): array
    {
        $bulanIni = PenyaluranBbm::query()->whereHas('pengajuan')
            ->whereBetween('tanggal_penyaluran', [now()->startOfMonth(), now()->endOfMonth()])->sum('liter_aktual');
        $jadwal = JadwalPembelian::query()->whereIn('status', ['terjadwal', 'ditunda'])->where('tanggal_waktu', '>=', now())->orderBy('tanggal_waktu')->first();

        return [
            Stat::make('Jumlah kapal', Kapal::query()->where('status', 'aktif')->count())->icon('heroicon-o-lifebuoy'),
            Stat::make('Surat rekomendasi aktif', SuratRekomendasi::query()->where('status', 'aktif')->whereDate('berlaku_mulai', '<=', today())->whereDate('berlaku_sampai', '>=', today())->count())->icon('heroicon-o-document-text'),
            Stat::make('Pembelian bulan ini', Format::liter($bulanIni))->icon('heroicon-o-beaker'),
            Stat::make('Total transaksi selesai', PengajuanPembelian::query()->where('status', StatusPengajuan::Selesai->value)->count())->icon('heroicon-o-check-badge')->color('success'),
            Stat::make('Pengajuan diproses', PengajuanPembelian::query()->whereIn('status', [StatusPengajuan::MenungguPersetujuan->value, StatusPengajuan::Disetujui->value])->count())->icon('heroicon-o-clock')->color('warning'),
            Stat::make('Jadwal berikutnya', $jadwal ? $jadwal->tanggal_waktu->translatedFormat('d M Y, H:i') : 'Belum ada')->icon('heroicon-o-calendar-days'),
        ];
    }
}
