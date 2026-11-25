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

class LaporanExport implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    private string $noSpbun;

    /** @param array{jenis?:string,judul:string,subjudul?:?string,periode:string,ringkasan:array,headings:array,rows:array} $laporan */
    public function __construct(private array $laporan)
    {
        $this->noSpbun = (string) (app(PengaturanService::class)->get('koperasi.no_spbun', '18.208.032') ?: '18.208.032');
    }

    public function array(): array
    {
        $baris = [
            [mb_strtoupper($this->laporan['judul'])],
            ['SPBUN : KSU ASSEA LORGAN MANDIRI'],
            ['NO SPBUN : ' . $this->noSpbun],
            ['PERIODE : ' . mb_strtoupper($this->laporan['periode'])],
        ];

        if (! empty($this->laporan['ringkasan'])) {
            foreach ($this->laporan['ringkasan'] as $label => $nilai) {
                $baris[] = [$label, $nilai];
            }
            $baris[] = [''];
        }

        $baris[] = array_map(fn ($h) => mb_strtoupper((string) $h), $this->laporan['headings']);
        $aman = array_map(fn (array $row) => array_map([self::class, 'amankanSel'], $row), $this->laporan['rows']);

        return array_merge($baris, $aman);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $colCount = max(2, count($this->laporan['headings']));
                $lastCol = Coordinate::stringFromColumnIndex($colCount);
                $subjudul = $this->laporan['subjudul'] ?? $this->laporan['judul'];

                // Sisipkan 2 baris di bawah kop (baris 5 kosong, baris 6 subjudul kiri)
                $sheet->insertNewRowBefore(5, 2);
                $sheet->setCellValue('A6', (string) $subjudul);

                $ringkasanCount = ! empty($this->laporan['ringkasan']) ? count($this->laporan['ringkasan']) : 0;
                $ringStart = 7;
                $ringEnd = $ringkasanCount > 0 ? 6 + $ringkasanCount : 6;

                // Deteksi posisi baris header tabel secara aktual agar tidak pernah meleset
                $firstHeadingUpper = mb_strtoupper((string) ($this->laporan['headings'][0] ?? ''));
                $headerRow = $ringkasanCount > 0 ? $ringEnd + 2 : 7;
                $maxRow = $sheet->getHighestRow();
                for ($scan = max(7, $ringEnd + 1); $scan <= min($maxRow, $ringEnd + 5); $scan++) {
                    if (mb_strtoupper(trim((string) $sheet->getCell("A{$scan}")->getValue())) === $firstHeadingUpper) {
                        $headerRow = $scan;
                        break;
                    }
                }
                $dataCount = count($this->laporan['rows']);
                $firstDataRow = $headerRow + 1;
                $lastDataRow = $dataCount > 0 ? $headerRow + $dataCount : $firstDataRow;

                // Font dasar Calibri 11pt (gaya Excel formal SPBUN)
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

                // 1. KOP 4 BARIS PERSIS REFERENSI:
                //    Row 1: Putih, Hitam Tebal 15pt (Judul Laporan)
                //    Row 2: Abu-abu (#BFBFBF), Hitam Tebal 13pt (SPBUN : KSU ASSEA LORGAN MANDIRI)
                //    Row 3: Abu-abu (#BFBFBF), Hitam Tebal 13pt (NO SPBUN : 18.208.032)
                //    Row 4: Putih, Hitam Tebal 13pt (PERIODE : ...)
                foreach ([1, 2, 3, 4] as $r) {
                    $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
                }

                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getRowDimension(2)->setRowHeight(24);
                $sheet->getRowDimension(3)->setRowHeight(24);
                $sheet->getRowDimension(4)->setRowHeight(24);
                $sheet->getRowDimension(5)->setRowHeight(16);
                $sheet->getRowDimension(6)->setRowHeight(20);

                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => '000000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                $sheet->getStyle("A2:{$lastCol}3")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '000000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'BFBFBF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '808080']],
                    ],
                ]);

                $sheet->getStyle("A4:{$lastCol}4")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '000000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => [
                        'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']],
                    ],
                ]);

                // Baris 6: subjudul kiri
                $sheet->mergeCells("A6:{$lastCol}6");
                $sheet->getStyle("A6:{$lastCol}6")->applyFromArray([
                    'font' => ['bold' => false, 'size' => 11, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_BOTTOM],
                ]);

                // 2. KOTAK RINGKASAN
                if ($ringkasanCount > 0) {
                    $sheet->getRowDimension($ringEnd + 1)->setRowHeight(10);
                    for ($r = $ringStart; $r <= $ringEnd; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight(20);
                        $val = $sheet->getCell("B{$r}")->getValue();
                        if (is_float($val)) {
                            $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode('#,##0.##');
                        } elseif (is_int($val)) {
                            $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode('#,##0');
                        }
                    }

                    $sheet->getStyle("A{$ringStart}:A{$ringEnd}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10.5, 'color' => ['rgb' => '000000']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("B{$ringStart}:B{$ringEnd}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10.5, 'color' => ['rgb' => '000000']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("A{$ringStart}:B{$ringEnd}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        ],
                    ]);
                }

                // 3. HEADER TABEL UTAMA (Hijau Cerah #1CE836, Teks Hitam, Border Hitam Tebal Atas & Bawah)
                $sheet->getRowDimension($headerRow)->setRowHeight(24);
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'font' => ['bold' => false, 'size' => 11, 'color' => ['rgb' => '000000']],
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

                // 4. BARIS DATA TABEL (Border hitam penuh persis referensi)
                if ($dataCount === 0) {
                    $sheet->setCellValue("A{$firstDataRow}", 'Tidak ada data pada filter ini.');
                    $sheet->mergeCells("A{$firstDataRow}:{$lastCol}{$firstDataRow}");
                    $sheet->getRowDimension($firstDataRow)->setRowHeight(22);
                    $sheet->getStyle("A{$firstDataRow}:{$lastCol}{$firstDataRow}")->applyFromArray([
                        'font' => ['italic' => true, 'color' => ['rgb' => '4B5563']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        ],
                    ]);
                } else {
                    $sheet->getStyle("A{$firstDataRow}:{$lastCol}{$lastDataRow}")->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        ],
                    ]);

                    for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight(20);
                    }

                    // Format angka/teks per kolom berdasarkan judul kolom (semua data rata tengah)
                    foreach ($this->laporan['headings'] as $idx => $heading) {
                        $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
                        $range = "{$colLetter}{$firstDataRow}:{$colLetter}{$lastDataRow}";
                        $h = mb_strtolower((string) $heading);

                        if (preg_match('/harga|total nilai|total tagihan|pemasukan|\(rp\)/', $h) === 1) {
                            $sheet->getStyle($range)->getNumberFormat()->setFormatCode('"Rp "#,##0');
                        } elseif (preg_match('/liter|kuota|disetujui|aktual|sisa|\(l\)/', $h) === 1) {
                            $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.##;(#,##0.##);"-"');
                        } elseif (preg_match('/^jumlah/', $h) === 1) {
                            $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0');
                        } elseif (preg_match('/rekomendasi|telepon|no\.|nomor/', $h) === 1) {
                            for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                                $val = $sheet->getCell("{$colLetter}{$r}")->getValue();
                                if ($val !== null && $val !== '') {
                                    $sheet->setCellValueExplicit("{$colLetter}{$r}", (string) $val, DataType::TYPE_STRING);
                                }
                            }
                        }
                    }
                }

                $tableBottomRow = $lastDataRow;

                // 5. AUTO-WIDTH KOLOM PRESISI
                for ($c = 1; $c <= $colCount; $c++) {
                    $colLetter = Coordinate::stringFromColumnIndex($c);
                    $hText = (string) ($this->laporan['headings'][$c - 1] ?? '');
                    $maxLen = mb_strlen($hText) + 5;

                    if ($c <= 2 && $ringkasanCount > 0) {
                        foreach ($this->laporan['ringkasan'] as $label => $nilai) {
                            $txt = $c === 1 ? (string) $label : (string) $nilai;
                            $maxLen = max($maxLen, mb_strlen($txt) + 4);
                        }
                    }

                    foreach ($this->laporan['rows'] as $row) {
                        $val = $row[$c - 1] ?? '';
                        $txt = is_float($val) ? number_format($val, 0, ',', '.') : (string) $val;
                        $maxLen = max($maxLen, mb_strlen($txt) + 5);
                    }

                    $minWidth = mb_strtolower($hText) === 'no' ? 7 : 16;
                    $sheet->getColumnDimension($colLetter)->setWidth(max($minWidth, min(42, $maxLen)));
                }

                // 7. PAGE SETUP SIAP CETAK A4 (tanpa freezePane agar tidak memecah layar saat di-scroll)
                $pageSetup = $sheet->getPageSetup();
                $pageSetup->setOrientation($colCount >= 7 ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT);
                $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
                $pageSetup->setFitToWidth(1);
                $pageSetup->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.5)->setRight(0.4)->setLeft(0.4)->setBottom(0.5);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
                $sheet->getPageSetup()->setPrintArea("A1:{$lastCol}{$tableBottomRow}");
            },
        ];
    }

    /** Mencegah Excel Formula Injection pada sel teks yang diawali karakter formula. */
    private static function amankanSel(mixed $nilai): mixed
    {
        if (! is_string($nilai) || $nilai === '') {
            return $nilai;
        }

        return preg_match('/^[=+\-@\t\r]/', $nilai) === 1 ? "'" . $nilai : $nilai;
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $this->laporan['judul']), 0, 31);
    }
}
