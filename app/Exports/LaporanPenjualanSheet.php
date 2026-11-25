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
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class LaporanPenjualanSheet implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    private string $noSpbun;
    private string $alamat;
    private string $namaKetua;

    /** @param array{bulan_nama:string,tahun:int,sheet_title:string,tanggal_tanda_tangan:string,rows:array<int,array<string,mixed>>,total_harian:array<int,float>,total_penjualan:float} $bulan */
    public function __construct(private array $bulan)
    {
        $pengaturan = app(PengaturanService::class);
        $this->noSpbun = (string) ($pengaturan->get('koperasi.no_spbun', '18.208.032') ?: '18.208.032');
        $this->alamat = (string) ($pengaturan->get('koperasi.alamat') ?: 'Jl. Babalan Gg. Tenggiri No. 45 Pangkalan Brandan');
        $this->namaKetua = mb_strtoupper((string) ($pengaturan->get('koperasi.nama_ketua', 'SYAFRIYANI') ?: 'SYAFRIYANI'));
    }

    public function array(): array
    {
        $row4 = array_fill(0, 36, '');
        $row4[0] = 'No. SPBU Kompak';
        $row4[2] = ': ' . $this->noSpbun;
        $row4[28] = 'Bulan';
        $row4[31] = ': ' . $this->bulan['bulan_nama'];

        $row5 = array_fill(0, 36, '');
        $row5[0] = 'Alamat';
        $row5[2] = ': ' . $this->alamat;
        $row5[28] = 'Tahun';
        $row5[31] = ': ' . $this->bulan['tahun'];

        $row6 = array_fill(0, 36, '');
        $row6[32] = 'M.SOLAR';

        $row7 = array_merge(['No', 'Nama', 'Nama Kapal', 'No. Rekomendasi', 'Tanggal Penjualan'], array_fill(0, 30, ''), ['Total']);
        $row8 = array_merge(['', '', '', ''], range(1, 31), ['']);

        $baris = [
            ['REKAPITULASI LAPORAN PENJUALAN KE SEKTOR PERIKANAN'],
            ['SPBU-N KOPERASI ASSEA LORGAN MANDIRI'],
            [''],
            $row4,
            $row5,
            $row6,
            $row7,
            $row8,
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
                ],
                $harian,
                ["=SUM(E{$excelRow}:AI{$excelRow})"]
            );
        }

        if ($rows === []) {
            $baris[] = ['Tidak ada data penjualan pada bulan ini.'];
            $baris[] = array_merge(['JUMLAH', '', '', ''], array_fill(0, 31, 0.0), [0.0]);
        } else {
            $lastDataRow = 8 + count($rows);
            $sumHarian = [];
            for ($d = 1; $d <= 31; $d++) {
                $col = Coordinate::stringFromColumnIndex(4 + $d);
                $sumHarian[] = "=SUM({$col}9:{$col}{$lastDataRow})";
            }

            $baris[] = array_merge(
                ['JUMLAH', '', '', ''],
                $sumHarian,
                ["=SUM(AJ9:AJ{$lastDataRow})"]
            );
        }

        // Blok tanda tangan kanan bawah (persis format referensi)
        $makeSig = function (string $teks): array {
            $r = array_fill(0, 36, '');
            $r[28] = $teks;

            return $r;
        };

        $baris[] = [''];
        $baris[] = [''];
        $baris[] = $makeSig('Tanggal, ' . $this->bulan['tanggal_tanda_tangan']);
        $baris[] = $makeSig('Hormat kami,');
        $baris[] = $makeSig('SPBU-N KOPERASI ASSEA LORGAN MANDIRI');
        $baris[] = [''];
        $baris[] = [''];
        $baris[] = [''];
        $baris[] = $makeSig($this->namaKetua);
        $baris[] = $makeSig('Ketua');

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
                $sigStart = $totalRow + 3;
                $sigEnd = $sigStart + 7;

                // Font dasar Calibri 10pt agar 36 kolom tampil rapi
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

                // 1. JUDUL & KOP ATAS (Row 1-6)
                $sheet->mergeCells('A1:AJ1');
                $sheet->mergeCells('A2:AJ2');
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getRowDimension(2)->setRowHeight(21);
                $sheet->getRowDimension(3)->setRowHeight(10);
                $sheet->getRowDimension(4)->setRowHeight(19);
                $sheet->getRowDimension(5)->setRowHeight(19);
                $sheet->getRowDimension(6)->setRowHeight(18);

                $sheet->getStyle('A1:AJ1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle('A2:AJ2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Identitas kiri & bulan/tahun kanan
                $sheet->mergeCells('A4:B4');
                $sheet->mergeCells('C4:L4');
                $sheet->mergeCells('A5:B5');
                $sheet->mergeCells('C5:L5');
                $sheet->mergeCells('AC4:AE4');
                $sheet->mergeCells('AF4:AJ4');
                $sheet->mergeCells('AC5:AE5');
                $sheet->mergeCells('AF5:AJ5');
                $sheet->mergeCells('AG6:AJ6');

                $sheet->getStyle('A4:L5')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10.5, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle('AC4:AJ5')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10.5, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle('AG6:AJ6')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10.5, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // 2. HEADER TABEL 2 BARIS (Row 7 & Row 8)
                foreach (['A', 'B', 'C', 'D', 'AJ'] as $col) {
                    $sheet->mergeCells("{$col}7:{$col}8");
                }
                $sheet->mergeCells('E7:AI7');

                $sheet->getRowDimension(7)->setRowHeight(22);
                $sheet->getRowDimension(8)->setRowHeight(20);
                $sheet->getStyle('A7:AJ8')->applyFromArray([
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

                // 3. BARIS DATA
                if ($dataCount === 0) {
                    $sheet->mergeCells('A9:AJ9');
                    $sheet->getRowDimension(9)->setRowHeight(22);
                    $sheet->getStyle('A9:AJ9')->applyFromArray([
                        'font' => ['italic' => true, 'color' => ['rgb' => '4B5563']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        ],
                    ]);
                } else {
                    $sheet->getStyle("A{$firstDataRow}:AJ{$lastDataRow}")->applyFromArray([
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

                    $sheet->getStyle("E{$firstDataRow}:AJ{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');

                    foreach ($rows as $idx => $item) {
                        $r = $firstDataRow + $idx;
                        if (! empty($item['no_rekom'])) {
                            $sheet->setCellValueExplicit("D{$r}", self::amankanSel((string) $item['no_rekom']), DataType::TYPE_STRING);
                        }
                        for ($d = 1; $d <= 31; $d++) {
                            $v = (float) ($item['harian'][$d] ?? 0);
                            if (fmod($v, 1.0) != 0.0) {
                                $col = Coordinate::stringFromColumnIndex(4 + $d);
                                $sheet->getStyle("{$col}{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                            }
                        }
                        if (fmod((float) ($item['total'] ?? 0), 1.0) != 0.0) {
                            $sheet->getStyle("AJ{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                        }
                    }
                }

                // 4. BARIS JUMLAH
                $sheet->mergeCells("A{$totalRow}:D{$totalRow}");
                $sheet->getRowDimension($totalRow)->setRowHeight(22);
                $sheet->getStyle("A{$totalRow}:AJ{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '000000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                        'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                    ],
                ]);
                $sheet->getStyle("A{$totalRow}:AJ{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$totalRow}:AJ{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                foreach ($this->bulan['total_harian'] ?? [] as $d => $val) {
                    if (fmod((float) $val, 1.0) != 0.0) {
                        $col = Coordinate::stringFromColumnIndex(4 + (int) $d);
                        $sheet->getStyle("{$col}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                }
                if (fmod((float) ($this->bulan['total_penjualan'] ?? 0), 1.0) != 0.0) {
                    $sheet->getStyle("AJ{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                }

                // 5. BLOK TANDA TANGAN KANAN BAWAH
                foreach ([$sigStart, $sigStart + 1, $sigStart + 2, $sigStart + 6, $sigStart + 7] as $sr) {
                    $sheet->mergeCells("AC{$sr}:AJ{$sr}");
                    $sheet->getRowDimension($sr)->setRowHeight(18);
                }
                $sheet->getStyle("AC{$sigStart}:AJ{$sigEnd}")->applyFromArray([
                    'font' => ['size' => 10.5, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle("AC" . ($sigStart + 2) . ":AJ" . ($sigStart + 2))->getFont()->setBold(true);
                $sheet->getStyle("AC" . ($sigStart + 6) . ":AJ" . ($sigStart + 6))->getFont()->setBold(true)->setUnderline(Font::UNDERLINE_SINGLE);

                // 6. LEBAR KOLOM PROPORSIONAL
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
                for ($d = 1; $d <= 31; $d++) {
                    $col = Coordinate::stringFromColumnIndex(4 + $d);
                    $sheet->getColumnDimension($col)->setWidth(7.5);
                }
                $sheet->getColumnDimension('AJ')->setWidth(12);

                // 7. PAGE SETUP LANDSCAPE A4 (tanpa freezePane)
                $pageSetup = $sheet->getPageSetup();
                $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
                $pageSetup->setFitToWidth(1);
                $pageSetup->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setLeft(0.3)->setBottom(0.4);
                $pageSetup->setRowsToRepeatAtTopByStartAndEnd(7, 8);
                $pageSetup->setPrintArea("A1:AJ{$sigEnd}");
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
        $title = (string) ($this->bulan['sheet_title'] ?? $this->bulan['bulan_nama'] ?? 'Penjualan');

        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $title), 0, 31);
    }
}
