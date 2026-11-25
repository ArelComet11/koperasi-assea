<?php

namespace App\Exports;

use App\Services\PengaturanService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class LaporanPenjualanHarianSheet implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    private string $noSpbun;
    private string $namaKetua;

    /** @param array{periode_label:string,sheet_title:string,tanggal_tanda_tangan:string,rows:array<int,array<string,mixed>>,total_penerimaan:float,total_penjualan:float} $bulan */
    public function __construct(private array $bulan)
    {
        $pengaturan = app(PengaturanService::class);
        $this->noSpbun = (string) ($pengaturan->get('koperasi.no_spbun', '18.208.032') ?: '18.208.032');
        $this->namaKetua = mb_strtoupper((string) ($pengaturan->get('koperasi.nama_ketua', 'SYAFRIYANI') ?: 'SYAFRIYANI'));
    }

    public function array(): array
    {
        $baris = [
            ['LAPORAN PENJUALAN PRODUK SOLAR/BIO SOLAR'],
            ['SPBUN : KSU ASSEA LORGAN MANDIRI'],
            ['NO SPBUN : ' . $this->noSpbun],
            ['PERIODE : ' . ($this->bulan['periode_label'] ?? '')],
            [''],
            ['Laporan Penjualan Produk : Solar/Bio Solar'],
            ['No', 'Tanggal', 'Keterangan', '', '', '', '', '', '', '', '', '', ''],
            [
                '',
                '',
                'Nama Kapal',
                'Nama Pemilik',
                'GT',
                'PK',
                'Rekomendasi',
                "JML LTR Yang Direkomendasikan\n(Liter)",
                "STOCK AWAL\n(Liter)",
                "PENERIMAAN\n(Liter)",
                "PENJUALAN\n(Liter)",
                "STOCK AKHIR\n(Liter)",
                'Tujuan',
            ],
        ];

        $rows = $this->bulan['rows'] ?? [];
        foreach ($rows as $item) {
            $tglVal = ! empty($item['tanggal'])
                ? ExcelDate::PHPToExcel(Carbon::parse((string) $item['tanggal']))
                : null;

            $baris[] = [
                (int) $item['no'],
                $tglVal,
                self::amankanSel((string) ($item['nama_kapal'] ?? '')),
                self::amankanSel((string) ($item['nama_pemilik'] ?? '')),
                $item['gt'] !== null ? (float) $item['gt'] : null,
                $item['pk'] !== null ? (is_numeric($item['pk']) ? (float) $item['pk'] : self::amankanSel((string) $item['pk'])) : null,
                self::amankanSel((string) ($item['rekomendasi'] ?? '')),
                $item['vol_rekom'] !== null ? (float) $item['vol_rekom'] : null,
                $item['stock_awal'] !== null ? (float) $item['stock_awal'] : null,
                $item['penerimaan'] !== null ? (float) $item['penerimaan'] : null,
                $item['penjualan'] !== null ? (float) $item['penjualan'] : null,
                $item['stock_akhir'] !== null ? (float) $item['stock_akhir'] : null,
                self::amankanSel((string) ($item['tujuan'] ?? '')),
            ];
        }

        if ($rows === []) {
            $baris[] = ['Tidak ada data penjualan pada bulan ini.'];
        }

        // Blok tanda tangan kanan bawah (persis format Lampiran 6)
        $makeSig = function (string $teks): array {
            $r = array_fill(0, 13, '');
            $r[10] = $teks;

            return $r;
        };

        $baris[] = [''];
        $baris[] = [''];
        $baris[] = $makeSig((string) ($this->bulan['tanggal_tanda_tangan'] ?? ''));
        $baris[] = [''];
        $baris[] = [''];
        $baris[] = [''];
        $baris[] = [''];
        $baris[] = $makeSig($this->namaKetua);
        $baris[] = $makeSig('KETUA');

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
                $sigStart = $lastDataRow + 3;
                $sigEnd = $sigStart + 6;

                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

                // 1. KOP ATAS (Row 1-6)
                foreach ([1, 2, 3, 4, 6] as $r) {
                    $sheet->mergeCells("A{$r}:M{$r}");
                }
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getRowDimension(2)->setRowHeight(19);
                $sheet->getRowDimension(3)->setRowHeight(19);
                $sheet->getRowDimension(4)->setRowHeight(19);
                $sheet->getRowDimension(5)->setRowHeight(10);
                $sheet->getRowDimension(6)->setRowHeight(18);

                $sheet->getStyle('A1:M1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle('A2:M4')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle('A6:M6')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // 2. HEADER TABEL 2 BARIS (Row 7 & Row 8)
                $sheet->mergeCells('A7:A8');
                $sheet->mergeCells('B7:B8');
                $sheet->mergeCells('C7:M7');
                $sheet->getRowDimension(7)->setRowHeight(20);
                $sheet->getRowDimension(8)->setRowHeight(42);

                $sheet->getStyle('A7:M8')->applyFromArray([
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
                    $sheet->mergeCells('A9:M9');
                    $sheet->getRowDimension(9)->setRowHeight(22);
                    $sheet->getStyle('A9:M9')->applyFromArray([
                        'font' => ['italic' => true, 'color' => ['rgb' => '4B5563']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        ],
                    ]);
                } else {
                    $sheet->getStyle("A{$firstDataRow}:M{$lastDataRow}")->applyFromArray([
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
                    $sheet->getStyle("E{$firstDataRow}:F{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("H{$firstDataRow}:L{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');

                    foreach ($rows as $idx => $item) {
                        $r = $firstDataRow + $idx;
                        if (! empty($item['rekomendasi'])) {
                            $sheet->setCellValueExplicit("G{$r}", self::amankanSel((string) $item['rekomendasi']), DataType::TYPE_STRING);
                        }
                        if ($item['gt'] !== null && fmod((float) $item['gt'], 1.0) != 0.0) {
                            $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode('#,##0.0');
                        }
                        if ($item['pk'] !== null && is_numeric($item['pk']) && fmod((float) $item['pk'], 1.0) != 0.0) {
                            $sheet->getStyle("F{$r}")->getNumberFormat()->setFormatCode('#,##0.0');
                        }
                        foreach (['vol_rekom' => 'H', 'stock_awal' => 'I', 'penerimaan' => 'J', 'penjualan' => 'K', 'stock_akhir' => 'L'] as $key => $col) {
                            if ($item[$key] !== null && fmod((float) $item[$key], 1.0) != 0.0) {
                                $sheet->getStyle("{$col}{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                            }
                        }
                        // Baris transaksi ke-2 dst. pada tanggal yang sama: beri arsiran abu-abu muda pada kolom stok harian (I, J, L)
                        if (! empty($item['is_lanjutan'])) {
                            foreach (['I', 'J', 'L'] as $col) {
                                $sheet->getStyle("{$col}{$r}")->getFill()
                                    ->setFillType(Fill::FILL_SOLID)
                                    ->getStartColor()->setRGB('D9D9D9');
                            }
                        }
                    }
                }

                // 4. BLOK TANDA TANGAN KANAN BAWAH
                foreach ([$sigStart, $sigStart + 5, $sigStart + 6] as $sr) {
                    $sheet->mergeCells("K{$sr}:M{$sr}");
                    $sheet->getRowDimension($sr)->setRowHeight(18);
                }
                $sheet->getStyle("K{$sigStart}:M{$sigEnd}")->applyFromArray([
                    'font' => ['size' => 10.5, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle('K' . ($sigStart + 5) . ':M' . ($sigStart + 5))->getFont()->setBold(true)->setUnderline(Font::UNDERLINE_SINGLE);
                $sheet->getStyle('K' . ($sigStart + 6) . ':M' . ($sigStart + 6))->getFont()->setBold(true);

                // 5. LEBAR KOLOM PROPORSIONAL (mengikuti Lampiran 6)
                $kapalMax = 20;
                $pemilikMax = 24;
                $rekomMax = 38;
                foreach ($rows as $item) {
                    $kapalMax = max($kapalMax, mb_strlen((string) ($item['nama_kapal'] ?? '')) + 3);
                    $pemilikMax = max($pemilikMax, mb_strlen((string) ($item['nama_pemilik'] ?? '')) + 3);
                    $rekomMax = max($rekomMax, mb_strlen((string) ($item['rekomendasi'] ?? '')) + 3);
                }

                $sheet->getColumnDimension('A')->setWidth(5.5);
                $sheet->getColumnDimension('B')->setWidth(14);
                $sheet->getColumnDimension('C')->setWidth(min(28, $kapalMax));
                $sheet->getColumnDimension('D')->setWidth(min(30, $pemilikMax));
                $sheet->getColumnDimension('E')->setWidth(6.5);
                $sheet->getColumnDimension('F')->setWidth(6.5);
                $sheet->getColumnDimension('G')->setWidth(min(44, $rekomMax));
                $sheet->getColumnDimension('H')->setWidth(17);
                $sheet->getColumnDimension('I')->setWidth(13);
                $sheet->getColumnDimension('J')->setWidth(13);
                $sheet->getColumnDimension('K')->setWidth(13);
                $sheet->getColumnDimension('L')->setWidth(13.5);
                $sheet->getColumnDimension('M')->setWidth(10);

                // 6. PAGE SETUP LANDSCAPE A4 (tanpa freezePane)
                $pageSetup = $sheet->getPageSetup();
                $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
                $pageSetup->setFitToWidth(1);
                $pageSetup->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setLeft(0.3)->setBottom(0.4);
                $pageSetup->setRowsToRepeatAtTopByStartAndEnd(7, 8);
                $pageSetup->setPrintArea("A1:M{$sigEnd}");
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
        $title = (string) ($this->bulan['sheet_title'] ?? 'PENJUALAN');

        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $title), 0, 31);
    }
}
