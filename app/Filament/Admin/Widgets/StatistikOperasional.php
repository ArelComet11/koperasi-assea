<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\StatusAktif;
use App\Enums\StatusPenerimaan;
use App\Enums\StatusPengajuan;
use App\Models\Anggota;
use App\Models\Kapal;
use App\Models\PenerimaanBbm;
use App\Models\PengajuanPembelian;
use App\Models\PenyaluranBbm;
use App\Models\SuratRekomendasi;
use App\Services\StokService;
use App\Support\Format;
use App\Support\Otorisasi;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatistikOperasional extends StatsOverviewWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return Otorisasi::bisa('penerimaan.view');
    }

    protected function getHeading(): ?string
    {
        return 'Ringkasan operasional';
    }

    protected function getStats(): array
    {
        $stok = app(StokService::class)->ringkasan();
        $suratAktif = SuratRekomendasi::query()->where('status', 'aktif')->whereDate('berlaku_mulai', '<=', today())->whereDate('berlaku_sampai', '>=', today())->count();
        $menunggu = PengajuanPembelian::query()->where('status', StatusPengajuan::MenungguPersetujuan->value)->count();

        return [
            Stat::make('Total anggota aktif', Anggota::query()->where('status', StatusAktif::Aktif->value)->count())->icon('heroicon-o-users'),
            Stat::make('Total kapal', Kapal::query()->count())->icon('heroicon-o-lifebuoy'),
            Stat::make('Surat rekomendasi aktif', $suratAktif)->icon('heroicon-o-document-text'),
            Stat::make('Total pengajuan', PengajuanPembelian::query()->count())->icon('heroicon-o-clipboard-document-list'),
            Stat::make('Menunggu persetujuan', $menunggu)->icon('heroicon-o-clock')->color($menunggu > 0 ? 'warning' : 'success'),
            Stat::make('Total BBM diterima', Format::liter(PenerimaanBbm::query()->where('status', StatusPenerimaan::Terkonfirmasi->value)->sum('liter'))),
            Stat::make('Total BBM tersalurkan', Format::liter(PenyaluranBbm::query()->sum('liter_aktual'))),
            Stat::make('Stok BBM fisik', Format::liter($stok['fisik']))->icon('heroicon-o-archive-box'),
            Stat::make('Stok dialokasikan', Format::liter($stok['dialokasikan']))->color('warning'),
            Stat::make('Stok tersedia', Format::liter($stok['tersedia']))->color('success'),
        ];
    }
}
