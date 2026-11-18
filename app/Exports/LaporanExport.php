<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanExport implements FromArray, ShouldAutoSize, WithTitle
{
    /** @param array{judul:string,periode:string,ringkasan:array,headings:array,rows:array} $laporan */
    public function __construct(private array $laporan)
    {
    }

    public function array(): array
    {
        $baris = [['KOPERASI SERBA USAHA ASSEA LORGAN MANDIRI'], [$this->laporan['judul']], ['Periode: ' . $this->laporan['periode']], []];

        foreach ($this->laporan['ringkasan'] as $label => $nilai) {
            $baris[] = [$label, $nilai];
        }
        if ($this->laporan['ringkasan']) {
            $baris[] = [];
        }

        $baris[] = $this->laporan['headings'];

        return array_merge($baris, $this->laporan['rows']);
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $this->laporan['judul']), 0, 31);
    }
}
