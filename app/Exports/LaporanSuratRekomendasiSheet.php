<?php

namespace App\Exports;

use App\Services\PengaturanService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class LaporanSuratRekomendasiSheet implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    private string $noLp;
    private string $alamat;

    /** @param array{bulan_label:string,sheet_title:string,rows:array<int,array<string,mixed>>,total_harian:array<int,float>,total_vol_rekom:float,total_penyaluran:float,total_vs_rekom:float} $bulan */
    public function __construct(private array $bulan)
    {
        $pengaturan = app(PengaturanService::class);
        $rawNoSpbun = (string) ($pengaturan->get('koperasi.no_spbun', '18.208.032') ?: '18.208.032');
        $this->noLp = preg_replace('/\D+/', '', $rawNoSpbun) ?: '18208032';
        $this->alamat = (string) ($pengaturan->get('koperasi.alamat') ?: 'Jl. Babalan Gg. Tenggiri No. 45');
    }

    public function array(): array
    {
        $hariHeader = array_merge(['', '', '', '', ''], range(1, 31), ['', '']);

        $baris = [
            ['NO LP', '', $this->noLp],
            ['NAMA', '', 'KSU ASSEA LORGAN MANDIRI'],
            ['ALAMAT', '', $this->alamat],
            ['REALISASI'],
            [''],
            [$this->bulan['bulan_label'], '', '', 'Penjualan'],
            array_merge(['NO', 'Nama', 'Nama Kapal', 'No Rekom', 'Vol Rekom', 'Penyaluran'], array_fill(0, 30, ''), ['Total', 'Total Vs Rekom']),
            $hariHeader,
        ];

        $rows = $this->bulan['rows'] ?? [];
        foreach ($rows as $idx => $item) {
            $excelRow = 9 + $idx;
            $harian = [];
            for ($d = 1; $d <= 31; $d++) {
                $val = $item['harian'][$d] ?? null;
                $harian[] = ($val !== null && (float) $val > 0) ? (float) $val : null;
            }

            $baris[] = array_merge(
                [
                    $idx + 1,
                    self::amankanSel((string) ($item['nama'] ?? '')),
                    self::amankanSel((string) ($item['nama_kapal'] ?? '')),
                    self::amankanSel((string) ($item['no_rekom'] ?? '')),
                    (float) ($item['vol_rekom'] ?? 0),
                ],
                $harian,
                [
                    "=SUM(F{$excelRow}:AJ{$excelRow})",
                    "=AK{$excelRow}-E{$excelRow}",
                ]
            );
        }

        if ($rows === []) {
            $baris[] = ['Tidak ada data surat rekomendasi pada periode ini.'];
            $baris[] = array_merge(['Total', '', '', '', 0.0], array_fill(0, 31, 0.0), [0.0, 0.0]);
        } else {
            $lastDataRow = 8 + count($rows);
            $sumHarian = [];
            for ($d = 1; $d <= 31; $d++) {
                $col = Coordinate::stringFromColumnIndex(5 + $d);
                $sumHarian[] = "=SUM({$col}9:{$col}{$lastDataRow})";
            }

            $baris[] = array_merge(
                ['Total', '', '', '', "=SUM(E9:E{$lastDataRow})"],
                $sumHarian,
                ["=SUM(AK9:AK{$lastDataRow})", "=SUM(AL9:AL{$lastDataRow})"]
            );
        }

        return $baris;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $rows = $this->bulan['rows'] ?? [];
                $dataCount = count($rows);
                $firstDataRow = 9;
                $lastDataRow = $dataCount > 0 ? 8 + $dataCount : 9;
                $totalRow = $lastDataRow + 1;

                // Font dasar Calibri 10pt agar 38 kolom tampil rapi dan proporsional
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

                // 1. KOP IDENTITAS SPBUN (NO LP, NAMA, ALAMAT, REALISASI)
                $sheet->setCellValueExplicit('C1', $this->noLp, DataType::TYPE_STRING);
                foreach ([1, 2, 3] as $r) {
                    $sheet->mergeCells("A{$r}:B{$r}");
                    $sheet->mergeCells("C{$r}:H{$r}");
                    $sheet->getRowDimension($r)->setRowHeight(19);
                }
                $sheet->mergeCells('A4:H4');
                $sheet->getRowDimension(4)->setRowHeight(20);
                $sheet->getRowDimension(5)->setRowHeight(10);

                $sheet->getStyle('A1:B4')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle('C1:H3')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // 2. BARIS BULAN & PENJUALAN (Row 6)
                $sheet->mergeCells('A6:C6');
                $sheet->mergeCells('D6:E6');
                $sheet->getRowDimension(6)->setRowHeight(21);
                $sheet->getStyle('A6:E6')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // 3. HEADER TABEL 2 BARIS (Row 7 & Row 8)
                foreach (['A', 'B', 'C', 'D', 'E', 'AK', 'AL'] as $col) {
                    $sheet->mergeCells("{$col}7:{$col}8");
                }
                $sheet->mergeCells('F7:AJ7');

                $sheet->getRowDimension(7)->setRowHeight(22);
                $sheet->getRowDimension(8)->setRowHeight(20);
                $sheet->getStyle('A7:AL8')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '000000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1CE836']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                        'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                    ],
                ]);

                // 4. BARIS DATA
                if ($dataCount === 0) {
                    $sheet->mergeCells('A9:AL9');
                    $sheet->getRowDimension(9)->setRowHeight(22);
                    $sheet->getStyle('A9:AL9')->applyFromArray([
                        'font' => ['italic' => true, 'color' => ['rgb' => '4B5563']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        ],
                    ]);
                } else {
                    $sheet->getStyle("A{$firstDataRow}:AL{$lastDataRow}")->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        ],
                    ]);
                    for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight(19);
                    }

                    foreach ($rows as $idx => $item) {
                        if (! empty($item['no_rekom'])) {
                            $r = $firstDataRow + $idx;
                            $sheet->setCellValueExplicit("D{$r}", self::amankanSel((string) $item['no_rekom']), DataType::TYPE_STRING);
                        }
                    }

                    $sheet->getStyle("E{$firstDataRow}:AK{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.##');
                    $sheet->getStyle("AL{$firstDataRow}:AL{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.##;-#,##0.##;0');
                }

                // 5. BARIS TOTAL BAWAH
                $sheet->mergeCells("A{$totalRow}:D{$totalRow}");
                $sheet->getRowDimension($totalRow)->setRowHeight(22);
                $sheet->getStyle("A{$totalRow}:AL{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '000000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                        'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                    ],
                ]);
                $sheet->getStyle("A{$totalRow}:AL{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$totalRow}:AK{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.##');
                $sheet->getStyle("AL{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.##;-#,##0.##;0');

                // 6. LEBAR KOLOM PROPORSIONAL (kompak agar 31 kolom tanggal terbaca lega)
                $namaMax = 18;
                $kapalMax = 18;
                $rekomMax = 24;
                foreach ($rows as $item) {
                    $namaMax = max($namaMax, mb_strlen((string) ($item['nama'] ?? '')) + 3);
                    $kapalMax = max($kapalMax, mb_strlen((string) ($item['nama_kapal'] ?? '')) + 3);
                    $rekomMax = max($rekomMax, mb_strlen((string) ($item['no_rekom'] ?? '')) + 3);
                }

                $sheet->getColumnDimension('A')->setWidth(5.5);
                $sheet->getColumnDimension('B')->setWidth(min(30, $namaMax));
                $sheet->getColumnDimension('C')->setWidth(min(26, $kapalMax));
                $sheet->getColumnDimension('D')->setWidth(min(44, $rekomMax));
                $sheet->getColumnDimension('E')->setWidth(11);
                for ($d = 1; $d <= 31; $d++) {
                    $col = Coordinate::stringFromColumnIndex(5 + $d);
                    $sheet->getColumnDimension($col)->setWidth(7.5);
                }
                $sheet->getColumnDimension('AK')->setWidth(11);
                $sheet->getColumnDimension('AL')->setWidth(13);

                // 7. PAGE SETUP LANDSCAPE A4 (tanpa freezePane agar scroll mulus)
                $pageSetup = $sheet->getPageSetup();
                $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
                $pageSetup->setFitToWidth(1);
                $pageSetup->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setLeft(0.3)->setBottom(0.4);
                $pageSetup->setRowsToRepeatAtTopByStartAndEnd(7, 8);
                $pageSetup->setPrintArea("A1:AL{$totalRow}");
            },
        ];
    }

    /** Mencegah Excel Formula Injection pada sel teks yang diawali karakter formula. */
    private static function amankanSel(string $nilai): string
    {
        if ($nilai === '') {
            return $nilai;
        }

        return preg_match('/^[=+\-@\t\r]/', $nilai) === 1 ? "'" . $nilai : $nilai;
    }

    public function title(): string
    {
        $title = (string) ($this->bulan['sheet_title'] ?? $this->bulan['bulan_label'] ?? 'Realisasi');

        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $title), 0, 31);
    }
}
