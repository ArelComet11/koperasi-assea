<?php

namespace App\Exports;

use App\Services\PengaturanService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class LaporanRealisasiExport implements FromArray, WithEvents, WithPreCalculateFormulas, WithStrictNullComparison, WithTitle
{
    private string $noSpbun;
    private string $alamat;
    private string $namaPemilik;
    private string $namaKetuaUpper;
    private string $telepon;

    /** @param array{jenis:string,judul:string,tahun?:int,realisasi?:array<string,mixed>} $laporan */
    public function __construct(private array $laporan)
    {
        $peng = app(PengaturanService::class);
        $this->noSpbun = (string) ($peng->get('koperasi.no_spbun', '18.208.032') ?: '18.208.032');
        $this->alamat = (string) ($peng->get('koperasi.alamat') ?: 'Jl. Babalan Gg. Tenggiri No. 45 Pangkalan Brandan');
        $ketua = trim((string) ($peng->get('koperasi.nama_ketua', 'Syafriyani') ?: 'Syafriyani'));
        $this->namaPemilik = mb_convert_case(mb_strtolower($ketua), MB_CASE_TITLE, 'UTF-8');
        $this->namaKetuaUpper = mb_strtoupper($ketua);
        $this->telepon = (string) ($peng->get('koperasi.telepon') ?: '081361923255');
    }

    public function array(): array
    {
        $real = $this->laporan['realisasi'] ?? [];
        $tahun = (int) ($real['tahun'] ?? now()->year);
        $bulanRows = $real['bulan'] ?? [];

        $baris = [
            ["Data Realisasi Penerimaan dan Penjualan BBM\nDi Lembaga Penyalur Periode : Januari – Desember {$tahun}"],
            ['Nomor Penyalur', ': ' . $this->noSpbun],
            ['Nama Penyalur', ': KSU ASSEA LORGAN MANDIRI'],
            ['Alamat/ Telpon', ': ' . $this->alamat],
            ['Nama Pemilik Penyalur', ': ' . $this->namaPemilik],
            ['No Telp/HP', ': ' . $this->telepon],
            ['Bulan', 'Saldo Awal', 'Penerimaan', 'Penjualan', 'Saldo Akhir Administrasi', 'Saldo Akhir Pemeriksaan Fisik'],
        ];

        foreach ($bulanRows as $idx => $item) {
            $r = 8 + $idx;
            $terima = $item['penerimaan'] ?? null;
            $jual = $item['penjualan'] ?? null;
            $koreksi = (float) ($item['koreksi'] ?? 0);
            $fisikFormula = abs($koreksi) > 0.0001
                ? '=E' . $r . ($koreksi > 0 ? '+' : '') . $koreksi
                : '=E' . $r;

            $baris[] = [
                $item['bulan'],
                (float) ($item['saldo_awal'] ?? 0),
                ($terima !== null && (float) $terima > 0) ? (float) $terima : null,
                ($jual !== null && (float) $jual > 0) ? (float) $jual : null,
                "=B{$r}+C{$r}-D{$r}",
                $fisikFormula,
            ];
        }

        $lastDataRow = 7 + count($bulanRows);
        $totalRow = $lastDataRow + 1;

        $baris[] = [
            'Total',
            '=B8',
            "=SUM(C8:C{$lastDataRow})",
            "=SUM(D8:D{$lastDataRow})",
            "=B{$totalRow}+C{$totalRow}-D{$totalRow}",
            "=F{$lastDataRow}",
        ];

        // Blok tanda tangan kanan bawah
        $baris[] = [''];
        $baris[] = [''];
        $baris[] = ['', '', '', "Tanggal, 31 Desember {$tahun}"];
        $baris[] = ['', '', '', 'Hormat kami,'];
        $baris[] = ['', '', '', 'SPBU-N KOPERASI ASSEA LORGAN MANDIRI'];
        $baris[] = [''];
        $baris[] = [''];
        $baris[] = [''];
        $baris[] = ['', '', '', $this->namaKetuaUpper];
        $baris[] = ['', '', '', 'Ketua'];

        return $baris;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $real = $this->laporan['realisasi'] ?? [];
                $bulanRows = $real['bulan'] ?? [];
                $lastDataRow = 7 + count($bulanRows);
                $totalRow = $lastDataRow + 1;
                $sigStart = $totalRow + 3;
                $sigEnd = $sigStart + 7;

                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

                // 1. JUDUL 2 BARIS (Row 1)
                $sheet->mergeCells('A1:F1');
                $sheet->getRowDimension(1)->setRowHeight(40);
                $sheet->getStyle('A1:F1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '000000']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                // 2. KOP IDENTITAS PENYALUR (Row 2-6)
                $sheet->setCellValueExplicit('B6', ': ' . $this->telepon, DataType::TYPE_STRING);
                for ($r = 2; $r <= 6; $r++) {
                    $sheet->mergeCells("B{$r}:F{$r}");
                    $sheet->getRowDimension($r)->setRowHeight(20);
                }
                $sheet->getStyle('A2:F6')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // 3. HEADER TABEL (Row 7 — Hijau SPBUN #1CE836, border hitam)
                $sheet->getRowDimension(7)->setRowHeight(30);
                $sheet->getStyle('A7:F7')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10.5, 'color' => ['rgb' => '000000']],
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

                // 4. BARIS DATA 12 BULAN (Row 8..19)
                $sheet->getStyle("A8:F{$lastDataRow}")->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                    ],
                ]);
                for ($r = 8; $r <= $lastDataRow; $r++) {
                    $sheet->getRowDimension($r)->setRowHeight(21);
                }
                $sheet->getStyle("B8:F{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');

                foreach ($bulanRows as $idx => $item) {
                    $r = 8 + $idx;
                    foreach (['B' => 'saldo_awal', 'C' => 'penerimaan', 'D' => 'penjualan', 'E' => 'saldo_akhir_adm', 'F' => 'saldo_akhir_fisik'] as $col => $field) {
                        $val = (float) ($item[$field] ?? 0);
                        if (fmod($val, 1.0) != 0.0) {
                            $sheet->getStyle("{$col}{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                        }
                    }
                }

                // 5. BARIS TOTAL (Row 20)
                $sheet->getRowDimension($totalRow)->setRowHeight(23);
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
                $sheet->getStyle("B{$totalRow}:F{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');

                // 6. BLOK TANDA TANGAN KANAN BAWAH
                foreach ([$sigStart, $sigStart + 1, $sigStart + 2, $sigStart + 6, $sigStart + 7] as $sr) {
                    $sheet->mergeCells("D{$sr}:F{$sr}");
                    $sheet->getRowDimension($sr)->setRowHeight(19);
                }
                $sheet->getStyle("D{$sigStart}:F{$sigEnd}")->applyFromArray([
                    'font' => ['size' => 11, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle('D' . ($sigStart + 2) . ':F' . ($sigStart + 2))->getFont()->setBold(true);
                $sheet->getStyle('D' . ($sigStart + 6) . ':F' . ($sigStart + 6))->getFont()->setBold(true)->setUnderline(Font::UNDERLINE_SINGLE);

                // 7. LEBAR KOLOM PROPORSIONAL
                $sheet->getColumnDimension('A')->setWidth(24);
                $sheet->getColumnDimension('B')->setWidth(16);
                $sheet->getColumnDimension('C')->setWidth(16);
                $sheet->getColumnDimension('D')->setWidth(16);
                $sheet->getColumnDimension('E')->setWidth(20);
                $sheet->getColumnDimension('F')->setWidth(22);

                // 8. PAGE SETUP PORTRAIT A4 (1 halaman penuh, tanpa freezePane)
                $pageSetup = $sheet->getPageSetup();
                $pageSetup->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
                $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
                $pageSetup->setFitToWidth(1);
                $pageSetup->setFitToHeight(1);
                $sheet->getPageMargins()->setTop(0.5)->setRight(0.4)->setLeft(0.4)->setBottom(0.5);
                $pageSetup->setPrintArea("A1:F{$sigEnd}");
            },
        ];
    }

    public function title(): string
    {
        $tahun = (int) ($this->laporan['realisasi']['tahun'] ?? now()->year);

        return "Realisasi BBM {$tahun}";
    }
}
