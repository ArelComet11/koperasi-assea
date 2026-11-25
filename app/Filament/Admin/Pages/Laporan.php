<?php

namespace App\Filament\Admin\Pages;

use App\Enums\StatusAktif;
use App\Enums\StatusPenerimaan;
use App\Enums\StatusTagihan;
use App\Exceptions\BisnisException;
use App\Models\Anggota;
use App\Models\Kapal;
use App\Models\PenerimaanBbm;
use App\Services\LaporanPenjualanImportService;
use App\Services\LaporanService;
use App\Support\Format;
use App\Support\Otorisasi;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class Laporan extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Laporan';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Laporan';
    protected static ?string $slug = 'laporan';

    protected string $view = 'filament.pages.laporan';

    public static function canAccess(): bool
    {
        return LaporanService::jenisUntuk(Otorisasi::user()) !== [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importPenjualan')
                ->label('Import Laporan')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->visible(fn () => Otorisasi::bisa('penyaluran.manage'))
                ->modalHeading('Import Data Laporan (Excel / CSV)')
                ->modalDescription('Unggah file .xlsx, .xls, atau .csv berformat Laporan Penjualan Produk (Lampiran 6), Laporan Stock Produk Solar (update stok/penerimaan), atau Cek Rekom / Rekap Penjualan (matriks tanggal 1–31). Mendukung multi-bulan dan multi-sheet sekaligus.')
                ->modalSubmitActionLabel('Mulai Import')
                ->schema([
                    FileUpload::make('file')
                        ->label('File Excel / CSV (.xlsx, .xls, .csv)')
                        ->disk('local')
                        ->directory('imports/laporan')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                            'text/plain',
                        ])
                        ->maxSize(20480)
                        ->required()
                        ->helperText('Maksimal 20 MB. Mendukung file multi-sheet (JAN–DES) maupun CSV.'),
                    Toggle::make('impor_penerimaan')
                        ->label('Impor juga Penerimaan BBM (stok masuk) jika tanggal tersebut belum tercatat')
                        ->default(fn () => ! PenerimaanBbm::query()->where('status', StatusPenerimaan::Terkonfirmasi->value)->exists())
                        ->helperText('Biarkan nonaktif jika Penerimaan BBM sudah dicatat/diimpor di menu Penerimaan BBM agar stok masuk tidak dobel. Jika diaktifkan, tanggal yang sudah ada tetap otomatis dilewati.'),
                    Toggle::make('buat_master')
                        ->label('Buat otomatis Anggota, Kapal, dan Surat Rekomendasi jika belum terdaftar')
                        ->default(true),
                    Toggle::make('lewati_duplikat')
                        ->label('Lewati transaksi yang sudah pernah diimpor (cegah duplikat)')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    $relPath = is_array($data['file']) ? reset($data['file']) : $data['file'];
                    $fullPath = Storage::disk('local')->path((string) $relPath);

                    try {
                        $hasil = app(LaporanPenjualanImportService::class)->impor(
                            $fullPath,
                            Otorisasi::userWajib(),
                            (bool) ($data['impor_penerimaan'] ?? false),
                            (bool) ($data['buat_master'] ?? true),
                            (bool) ($data['lewati_duplikat'] ?? true),
                        );
                    } catch (BisnisException $e) {
                        Notification::make()->title('Import gagal')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    } finally {
                        if ($relPath) {
                            Storage::disk('local')->delete((string) $relPath);
                        }
                    }

                    $bagian = [];
                    if ($hasil['penjualan'] > 0 || ($hasil['penerimaan'] === 0 && ($hasil['penerimaan_dilewati'] ?? 0) === 0)) {
                        $bagian[] = "Penjualan diimpor: {$hasil['penjualan']} transaksi (" . Format::liter($hasil['liter_penjualan']) . ')';
                    }
                    if ($hasil['penerimaan'] > 0) {
                        $bagian[] = "Penerimaan BBM diimpor: {$hasil['penerimaan']} hari (" . Format::liter($hasil['liter_penerimaan']) . ')';
                    }
                    if (($hasil['penerimaan_dilewati'] ?? 0) > 0) {
                        $bagian[] = "Penerimaan sudah tercatat (dilewati): {$hasil['penerimaan_dilewati']} hari";
                    }
                    if ($hasil['duplikat_dilewati'] > 0) {
                        $bagian[] = "Duplikat penjualan dilewati: {$hasil['duplikat_dilewati']} transaksi";
                    }
                    if ($hasil['anggota_baru'] > 0 || $hasil['kapal_baru'] > 0 || $hasil['surat_baru'] > 0) {
                        $bagian[] = "Master baru: {$hasil['anggota_baru']} anggota, {$hasil['kapal_baru']} kapal, {$hasil['surat_baru']} surat rekomendasi";
                    }
                    if ($hasil['gagal'] !== []) {
                        $bagian[] = 'Baris gagal: ' . count($hasil['gagal']);
                        foreach (array_slice($hasil['gagal'], 0, 3) as $g) {
                            $bagian[] = "• {$g['sheet']} baris {$g['baris']}: {$g['alasan']}";
                        }
                    }

                    $adaPerubahan = $hasil['penjualan'] > 0
                        || $hasil['penerimaan'] > 0
                        || $hasil['anggota_baru'] > 0
                        || $hasil['kapal_baru'] > 0
                        || $hasil['surat_baru'] > 0;

                    $notif = Notification::make()
                        ->title($adaPerubahan ? 'Import Laporan selesai' : 'Tidak ada data baru yang diimpor')
                        ->body(implode("\n", $bagian))
                        ->persistent();

                    if ($hasil['gagal'] !== []) {
                        $notif->warning()->send();
                    } elseif ($adaPerubahan) {
                        $notif->success()->send();
                    } else {
                        $notif->info()->send();
                    }
                }),
        ];
    }

    /** Opsi dropdown status yang relevan per jenis laporan. */
    private static function opsiStatus(string $jenis): array
    {
        return match ($jenis) {
            'kapal' => StatusAktif::opsi(),
            'surat' => ['aktif' => 'Aktif', 'belum_berlaku' => 'Belum berlaku', 'kedaluwarsa' => 'Kedaluwarsa', 'nonaktif' => 'Nonaktif'],
            'keuangan' => [
                StatusTagihan::BelumDibayar->value => StatusTagihan::BelumDibayar->getLabel(),
                StatusTagihan::Lunas->value => StatusTagihan::Lunas->getLabel(),
            ],
            default => [],
        };
    }

    protected function getViewData(): array
    {
        @ini_set('memory_limit', '256M');

        $jenisBoleh = LaporanService::jenisUntuk(Otorisasi::user());
        $jenis = (string) request()->query('jenis', array_key_first($jenisBoleh));
        $filter = array_filter(request()->only(['dari', 'sampai', 'anggota_id', 'kapal_id', 'status', 'cari']), fn ($v) => $v !== null && $v !== '');
        $statusOpsi = self::opsiStatus($jenis);
        if (! empty($filter['status']) && $statusOpsi !== [] && ! array_key_exists($filter['status'], $statusOpsi)) {
            unset($filter['status']);
        }

        $laporan = null;
        $galat = null;
        if (! isset($jenisBoleh[$jenis])) {
            $galat = 'Anda tidak memiliki izin untuk membuka jenis laporan tersebut.';
        } else {
            try {
                if (! empty($filter['dari']) && ! empty($filter['sampai']) && Carbon::parse($filter['dari'])->gt(Carbon::parse($filter['sampai']))) {
                    $galat = 'Filter tidak valid. Tanggal "Dari" tidak boleh melebihi tanggal "Sampai".';
                } else {
                    $laporan = app(LaporanService::class)->bangun($jenis, $filter);
                }
            } catch (\Throwable $e) {
                $galat = 'Filter tidak valid. Periksa tanggal dan isian lainnya.';
            }
        }

        $bulananCount = is_array($laporan['bulanan'] ?? null) ? count($laporan['bulanan']) : 0;
        $tabBulan = null;
        if ($bulananCount > 1) {
            $reqTab = request()->query('tab_bulan');
            if ($reqTab !== null && is_numeric($reqTab) && (int) $reqTab >= 0 && (int) $reqTab < $bulananCount) {
                $tabBulan = (int) $reqTab;
            } else {
                // Default ke bulan pertama yang memiliki data agar pratinjau langsung informatif
                $tabBulan = 0;
                foreach ($laporan['bulanan'] as $idx => $b) {
                    if (! empty($b['rows'])) {
                        $tabBulan = $idx;
                        break;
                    }
                }
            }
        }

        $tampilAnggota = in_array($jenis, ['kapal', 'surat', 'penjualan_harian', 'penjualan', 'keuangan'], true);
        $tampilKapal = in_array($jenis, ['kapal', 'surat', 'penjualan_harian', 'penjualan', 'keuangan'], true);
        $query = http_build_query(['jenis' => $jenis] + $filter);

        return [
            'jenisBoleh' => $jenisBoleh, 'jenis' => $jenis, 'filter' => $filter, 'laporan' => $laporan, 'galat' => $galat,
            'statusOpsi' => $statusOpsi,
            'tampilAnggota' => $tampilAnggota,
            'tampilKapal' => $tampilKapal,
            'tabBulan' => $tabBulan,
            'baseFilterParams' => ['jenis' => $jenis] + $filter,
            'anggotaOpsi' => $tampilAnggota ? Anggota::query()->orderBy('nama_lengkap')->limit(500)->pluck('nama_lengkap', 'id') : collect(),
            'kapalOpsi' => $tampilKapal ? Kapal::query()->orderBy('nama_kapal')->limit(500)->pluck('nama_kapal', 'id') : collect(),
            'urlExport' => route('laporan.export') . '?' . $query,
            'urlCetak' => route('laporan.cetak') . '?' . $query,
        ];
    }
}
