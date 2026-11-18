<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\StatusPembayaranCatatan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusTagihan;
use App\Models\Pembayaran;
use App\Models\PengajuanPembelian;
use App\Models\Tagihan;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatistikKeuangan extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('tagihan.view');
    }

    protected function getHeading(): ?string
    {
        return 'Ringkasan keuangan';
    }

    protected function getStats(): array
    {
        $sah = fn () => Pembayaran::query()->where('status', StatusPembayaranCatatan::Sah->value);

        return [
            Stat::make('Total transaksi selesai', PengajuanPembelian::query()->where('status', StatusPengajuan::Selesai->value)->count())->icon('heroicon-o-check-badge'),
            Stat::make('Total pembayaran diterima', Format::rupiah($sah()->sum('jumlah')))->icon('heroicon-o-banknotes')->color('success'),
            Stat::make('Tagihan belum lunas', Format::rupiah(Tagihan::query()->where('status', StatusTagihan::BelumDibayar->value)->sum('total')))->icon('heroicon-o-exclamation-circle')->color('warning'),
            Stat::make('Pemasukan hari ini', Format::rupiah($sah()->whereDate('tanggal_pembayaran', today())->sum('jumlah'))),
            Stat::make('Pemasukan bulan ini', Format::rupiah($sah()->whereBetween('tanggal_pembayaran', [now()->startOfMonth(), now()->endOfMonth()])->sum('jumlah'))),
        ];
    }
}
