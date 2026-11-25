<?php

namespace App\Exports;

use App\Services\PengaturanService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class LaporanStokSolarSheet implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    private string $noSpbun;

    /** @param array{periode_label:string,sheet_title:string,rows:array<int,array<string,mixed>>,total_penerimaan:float,total_penjualan:float} $bulan */
    public function __construct(private array $bulan)
    {
        $pengaturan = app(PengaturanService::class);
        $this->noSpbun = (string) ($pengaturan->get('koperasi.no_spbun', '18.208.032') ?: '18.208.032');
    }

    public function array(): array
    {
        $baris = [
            ['LAPORAN STOCK PRODUK SOLAR/BIO SOLAR'],
            ['SPBUN : KSU ASSEA LORGAN MANDIRI'],
            ['NO SPBUN : ' . $this->noSpbun],
            ['PERIODE : ' . ($this->bulan['periode_label'] ?? '')],
            [''],
            ['Laporan Stock Produk : Solar/Bio Solar'],
            ['NO', 'TANGGAL', 'STOCK AWAL', 'PENERIMAAN', 'PENJUALAN', 'STOCK AKHIR'],
        ];

        $rows = $this->bulan['rows'] ?? [];
        foreach ($rows as $item) {
            $tglVal = ! empty($item['tanggal'])
                ? ExcelDate::PHPToExcel(Carbon::parse((string) $item['tanggal']))
                : null;

            $baris[] = [
                (int) $item['no'],
                $tglVal,
                $item['stock_awal'] !== null ? (float) $item['stock_awal'] : null,
                $item['penerimaan'] !== null ? (float) $item['penerimaan'] : null,
                $item['penjualan'] !== null ? (float) $item['penjualan'] : null,
                $item['stock_akhir'] !== null ? (float) $item['stock_akhir'] : null,
            ];
        }

        if ($rows === []) {
            $baris[] = ['Tidak ada data stok pada bulan ini.'];
            $baris[] = ['TOTAL', '', null, 0.0, 0.0, null];
        } else {
            $lastDataRow = 7 + count($rows);
            $baris[] = [
                'TOTAL',
                '',
                null,
                "=SUM(D8:D{$lastDataRow})",
                "=SUM(E8:E{$lastDataRow})",
                null,
            ];
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
                $firstDataRow = 8;
                $lastDataRow = $dataCount > 0 ? 7 + $dataCount : 8;
                $totalRow = $lastDataRow + 1;

                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

                // 1. KOP ATAS (Row 1-6)
                foreach ([1, 2, 3, 4, 6] as $r) {
                    $sheet->mergeCells("A{$r}:F{$r}");
                }
                for ($r = 1; $r <= 4; $r++) {
                    $sheet->getRowDimension($r)->setRowHeight(22);
                }
                $sheet->getRowDimension(5)->setRowHeight(12);
                $sheet->getRowDimension(6)->setRowHeight(19);

                $sheet->getStyle('A1:F4')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle('A6:F6')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // 2. HEADER TABEL (Row 7 — Hijau #1BF950)
                $sheet->getRowDimension(7)->setRowHeight(22);
                $sheet->getStyle('A7:F7')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1BF950']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                        'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                    ],
                ]);

                // 3. BARIS DATA (Row 8..lastDataRow)
                if ($dataCount === 0) {
                    $sheet->mergeCells('A8:F8');
                    $sheet->getRowDimension(8)->setRowHeight(22);
                    $sheet->getStyle('A8:F8')->applyFromArray([
                        'font' => ['italic' => true, 'color' => ['rgb' => '4B5563']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        ],
                    ]);
                } else {
                    $sheet->getStyle("A{$firstDataRow}:F{$lastDataRow}")->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        ],
                    ]);
                    for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight(18);
                    }

                    $sheet->getStyle("B{$firstDataRow}:B{$lastDataRow}")->getNumberFormat()->setFormatCode('d-mmm-yy');
                    $sheet->getStyle("C{$firstDataRow}:F{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');

                    foreach ($rows as $idx => $item) {
                        $r = $firstDataRow + $idx;
                        foreach (['stock_awal' => 'C', 'penerimaan' => 'D', 'penjualan' => 'E', 'stock_akhir' => 'F'] as $key => $col) {
                            if ($item[$key] !== null && fmod((float) $item[$key], 1.0) != 0.0) {
                                $sheet->getStyle("{$col}{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                            }
                        }
                    }
                }

                // 4. BARIS TOTAL
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");
                $sheet->getRowDimension($totalRow)->setRowHeight(22);
                $sheet->getStyle("A{$totalRow}:F{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                        'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                    ],
                ]);
                $sheet->getStyle("A{$totalRow}:F{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$totalRow}:E{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                if (fmod((float) ($this->bulan['total_penerimaan'] ?? 0), 1.0) != 0.0) {
                    $sheet->getStyle("D{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
                if (fmod((float) ($this->bulan['total_penjualan'] ?? 0), 1.0) != 0.0) {
                    $sheet->getStyle("E{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                }

                // 5. LEBAR KOLOM (persis format referensi)
                $sheet->getColumnDimension('A')->setWidth(6.5);
                $sheet->getColumnDimension('B')->setWidth(18.5);
                $sheet->getColumnDimension('C')->setWidth(18.5);
                $sheet->getColumnDimension('D')->setWidth(18.5);
                $sheet->getColumnDimension('E')->setWidth(18.5);
                $sheet->getColumnDimension('F')->setWidth(18.5);

                // 6. PAGE SETUP PORTRAIT A4 (tanpa freezePane)
                $pageSetup = $sheet->getPageSetup();
                $pageSetup->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
                $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
                $pageSetup->setFitToWidth(1);
                $pageSetup->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.5)->setRight(0.4)->setLeft(0.4)->setBottom(0.5);
                $pageSetup->setRowsToRepeatAtTopByStartAndEnd(7, 7);
                $pageSetup->setPrintArea("A1:F{$totalRow}");
            },
        ];
    }

    public function title(): string
    {
        $title = (string) ($this->bulan['sheet_title'] ?? 'STOCK');

        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $title), 0, 31);
    }
}
