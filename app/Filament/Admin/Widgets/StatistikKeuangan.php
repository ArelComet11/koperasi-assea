<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\StatusPembayaranCatatan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusTagihan;
use App\Models\Pembayaran;
use App\Models\PengajuanPembelian;
use App\Models\PenyesuaianTagihan;
use App\Models\Tagihan;
use App\Support\Dec;
use App\Support\Format;
use App\Support\Otorisasi;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatistikKeuangan extends StatsOverviewWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return Otorisasi::bisa('tagihan.view');
    }

    protected function getHeading(): ?string
    {
        return 'Ringkasan keuangan';
    }

    protected function getStats(): array
    {
        $hariMulai = today()->startOfDay();
        $hariSelesai = today()->endOfDay();
        $bulanMulai = now()->startOfMonth();
        $bulanSelesai = now()->endOfMonth();

        $netExpr = 'CASE WHEN t_ref.total IS NOT NULL AND pembayaran.jumlah > t_ref.total THEN t_ref.total ELSE pembayaran.jumlah END';
        $refExpr = 'CASE WHEN t_ref.total IS NOT NULL AND pembayaran.jumlah > t_ref.total THEN pembayaran.jumlah - t_ref.total ELSE 0 END';

        $bayar = Pembayaran::query()
            ->leftJoin('tagihan as t_ref', 't_ref.id', '=', 'pembayaran.tagihan_id')
            ->where('pembayaran.status', StatusPembayaranCatatan::Sah->value)
            ->selectRaw(
                "COALESCE(SUM(pembayaran.jumlah), 0) as bruto_total,
                 COALESCE(SUM({$netExpr}), 0) as neto_total,
                 COALESCE(SUM({$refExpr}), 0) as refund_total,
                 COALESCE(SUM(CASE WHEN pembayaran.tanggal_pembayaran BETWEEN ? AND ? THEN {$netExpr} ELSE 0 END), 0) as neto_hari,
                 COALESCE(SUM(CASE WHEN pembayaran.tanggal_pembayaran BETWEEN ? AND ? THEN {$netExpr} ELSE 0 END), 0) as neto_bulan",
                [$hariMulai, $hariSelesai, $bulanMulai, $bulanSelesai]
            )
            ->first();

        $refundTertundaRaw = Dec::n(PenyesuaianTagihan::query()->where('diterapkan', false)->sum('selisih'));
        $refundTertunda = Dec::lt($refundTertundaRaw, 0) ? Dec::sub('0', $refundTertundaRaw) : '0.00';
        $refundTotal = Dec::n($bayar->refund_total ?? 0);

        return [
            Stat::make('Total transaksi selesai', PengajuanPembelian::query()->where('status', StatusPengajuan::Selesai->value)->count())->icon('heroicon-o-check-badge'),
            Stat::make('Total pembayaran diterima', Format::rupiah($bayar->neto_total ?? 0))
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->description(Dec::gt($refundTotal, 0) ? 'Bruto ' . Format::rupiah($bayar->bruto_total ?? 0) . ' − refund ' . Format::rupiah($refundTotal) : null),
            Stat::make('Tagihan belum lunas', Format::rupiah(Tagihan::query()->where('status', StatusTagihan::BelumDibayar->value)->sum('total')))->icon('heroicon-o-exclamation-circle')->color('warning'),
            Stat::make('Koreksi / refund tertunda', Format::rupiah($refundTertunda))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color(Dec::gt($refundTertunda, 0) ? 'danger' : 'success'),
            Stat::make('Pemasukan hari ini', Format::rupiah($bayar->neto_hari ?? 0)),
            Stat::make('Pemasukan bulan ini', Format::rupiah($bayar->neto_bulan ?? 0)),
        ];
    }
}
