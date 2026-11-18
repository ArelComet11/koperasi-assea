<?php

namespace App\Filament\Admin\Widgets;

use App\Models\KuotaRekomendasiBulanan;
use Filament\Widgets\ChartWidget;

class GrafikKuota extends ChartWidget
{
    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('surat.view');
    }

    public function getHeading(): ?string
    {
        return 'Penggunaan kuota surat rekomendasi bulan ini (10 teratas, liter)';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = KuotaRekomendasiBulanan::query()->with('suratRekomendasi')
            ->where('periode', now()->startOfMonth()->toDateString())
            ->orderByRaw('(liter_terpakai + liter_dialokasikan) DESC')->limit(10)->get();

        return [
            'datasets' => [
                ['label' => 'Aktual', 'data' => $rows->map(fn ($r) => (float) $r->liter_terpakai)->all(), 'backgroundColor' => '#078B4B'],
                ['label' => 'Alokasi aktif', 'data' => $rows->map(fn ($r) => (float) $r->liter_dialokasikan)->all(), 'backgroundColor' => '#E5B51B'],
                ['label' => 'Sisa', 'data' => $rows->map(fn ($r) => (float) $r->sisa)->all(), 'backgroundColor' => '#C9D6CE'],
            ],
            'labels' => $rows->map(fn ($r) => $r->suratRekomendasi?->nomor_surat ?? '-')->all(),
        ];
    }
}
