<?php

namespace App\Filament\Support;

use App\Support\Otorisasi;
use Filament\Widgets\ChartWidget;

/** Dasar grafik 12 bulan dengan filter tahun. Subclass cukup mengisi deret(). */
abstract class BulananChart extends ChartWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static string $izin = '';
    protected static string $judul = '';
    protected static string $label = '';
    protected static string $warna = '#078B4B';
    protected static string $jenis = 'bar';

    protected ?string $maxHeight = '260px';

    /** @return float[] 12 nilai (Jan..Des) */
    abstract protected function deret(int $tahun): array;

    public static function canView(): bool
    {
        return Otorisasi::bisa(static::$izin);
    }

    public function getHeading(): ?string
    {
        return static::$judul;
    }

    protected function getType(): string
    {
        return static::$jenis;
    }

    protected function getFilters(): ?array
    {
        $y = now()->year;

        return [(string) $y => (string) $y, (string) ($y - 1) => (string) ($y - 1), (string) ($y - 2) => (string) ($y - 2)];
    }

    protected function getData(): array
    {
        $tahun = (int) ($this->filter ?: now()->year);

        return [
            'datasets' => [[
                'label' => static::$label,
                'data' => $this->deret($tahun),
                'backgroundColor' => static::$warna . (static::$jenis === 'line' ? '33' : ''),
                'borderColor' => static::$warna,
                'fill' => static::$jenis === 'line',
            ]],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
        ];
    }

    /** Menjumlahkan/menghitung per bulan dari koleksi (portabel lintas MySQL/SQLite). */
    protected function perBulan($rows, string $kolomTanggal, ?string $kolomNilai = null): array
    {
        $hasil = array_fill(1, 12, 0.0);
        foreach ($rows as $r) {
            $bulan = (int) \Carbon\Carbon::parse($r->{$kolomTanggal})->format('n');
            $hasil[$bulan] += $kolomNilai ? (float) $r->{$kolomNilai} : 1;
        }

        return array_values($hasil);
    }
}
