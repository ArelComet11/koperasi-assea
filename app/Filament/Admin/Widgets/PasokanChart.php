<?php

namespace App\Filament\Admin\Widgets;

use App\Models\KuotaPasokanBulanan;
use App\Services\PasokanService;
use Filament\Widgets\ChartWidget;

class PasokanChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('penerimaan.view');
    }

    public function getHeading(): ?string
    {
        return 'Pemakaian pasokan 6 bulan terakhir (liter)';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $labels = $diterima = $digunakan = $batas = [];
        for ($i = 5; $i >= 0; $i--) {
            $p = now()->startOfMonth()->subMonths($i);
            $r = KuotaPasokanBulanan::query()->where('periode', $p->toDateString())->first();
            $labels[] = $p->translatedFormat('M Y');
            $diterima[] = (float) ($r?->total_diterima ?? 0);
            $digunakan[] = (float) (($r?->total_dialokasikan ?? 0) + ($r?->total_disalurkan ?? 0));
            $batas[] = (float) ($r?->batas_liter ?? app(PasokanService::class)->batas());
        }

        return [
            'datasets' => [
                ['label' => 'Diterima', 'data' => $diterima, 'backgroundColor' => '#078B4B'],
                ['label' => 'Alokasi + tersalurkan', 'data' => $digunakan, 'backgroundColor' => '#E5B51B'],
                ['type' => 'line', 'label' => 'Batas', 'data' => $batas, 'borderColor' => '#D71920', 'backgroundColor' => '#D71920', 'pointRadius' => 0],
            ],
            'labels' => $labels,
        ];
    }
}
