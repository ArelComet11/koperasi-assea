<?php

namespace App\Filament\Admin\Pages;

use App\Models\KuotaRekomendasiBulanan;
use App\Models\SuratRekomendasi;
use App\Services\KuotaService;
use App\Support\Dec;
use App\Support\Format;
use App\Support\Otorisasi;
use Carbon\Carbon;
use Filament\Pages\Page;

class MonitoringKuota extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Operasional BBM';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationLabel = 'Monitoring Kuota';
    protected static ?int $navigationSort = 5;
    protected static ?string $title = 'Monitoring Kuota Surat Rekomendasi';
    protected static ?string $slug = 'monitoring-kuota';

    protected string $view = 'filament.pages.monitoring-kuota';

    public static function canAccess(): bool
    {
        return Otorisasi::bisa('surat.view');
    }

    protected function getViewData(): array
    {
        $svc = app(KuotaService::class);
        $param = (string) request()->query('periode', now()->format('Y-m'));
        try {
            $periode = Carbon::createFromFormat('Y-m', $param)->startOfMonth();
        } catch (\Throwable) {
            $periode = now()->startOfMonth();
        }
        $cari = trim((string) request()->query('cari', ''));

        $surat = SuratRekomendasi::query()->with(['anggota', 'kapal'])
            ->where('status', 'aktif')
            ->whereDate('berlaku_mulai', '<=', $periode->copy()->endOfMonth())
            ->whereDate('berlaku_sampai', '>=', $periode)
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('nomor_surat', 'like', "%{$cari}%")
                ->orWhereHas('anggota', fn ($a) => $a->where('nama_lengkap', 'like', "%{$cari}%"))
                ->orWhereHas('kapal', fn ($k) => $k->where('nama_kapal', 'like', "%{$cari}%"))))
            ->orderBy('nomor_surat')->limit(500)->get();

        $baris = KuotaRekomendasiBulanan::query()->withoutGlobalScopes()
            ->where('periode', $periode->toDateString())->whereIn('surat_rekomendasi_id', $surat->pluck('id'))
            ->get()->keyBy('surat_rekomendasi_id');

        $rows = $surat->map(function ($s) use ($baris, $svc) {
            $b = $baris->get($s->id);
            $batas = Dec::n($b?->batas_liter ?? $svc->batas($s));
            $terpakai = Dec::n($b?->liter_terpakai);
            $alokasi = Dec::n($b?->liter_dialokasikan);

            return [
                'anggota' => $s->anggota?->nama_lengkap, 'kapal' => $s->kapal?->nama_kapal, 'nomor' => $s->nomor_surat,
                'batas' => Format::liter($batas), 'terpakai' => Format::liter($terpakai), 'alokasi' => Format::liter($alokasi),
                'sisa' => Format::liter(Dec::max0(Dec::sub(Dec::sub($batas, $terpakai), $alokasi))),
                'persen' => min(100, Format::persen(Dec::add($terpakai, $alokasi), $batas)),
            ];
        });

        return ['rows' => $rows, 'periodeInput' => $periode->format('Y-m'), 'periodeLabel' => $periode->translatedFormat('F Y'), 'cari' => $cari];
    }
}
