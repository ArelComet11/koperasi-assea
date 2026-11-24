<?php

namespace App\Filament\Admin\Resources\AnggotaResource\Pages;

use App\Exceptions\BisnisException;
use App\Filament\Admin\Resources\AnggotaResource;
use App\Services\AnggotaImportService;
use App\Support\Otorisasi;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ListAnggota extends ListRecords
{
    protected static string $resource = AnggotaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('unduhTemplate')
                ->label('Template Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn () => Otorisasi::bisa('anggota.create'))
                ->url(fn () => route('admin.anggota.template-import')),
            Action::make('importExcel')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->visible(fn () => Otorisasi::bisa('anggota.create'))
                ->modalHeading('Import Data Anggota dari Excel')
                ->modalDescription(new HtmlString(
                    'Unggah file <strong>.xlsx / .xls / .csv</strong> sesuai format template. '
                    . '<a href="' . e(route('admin.anggota.template-import')) . '" class="font-semibold underline text-primary-600">Unduh Template Excel di sini</a>.'
                ))
                ->modalSubmitActionLabel('Mulai Import')
                ->schema([
                    FileUpload::make('file')
                        ->label('File Excel (.xlsx, .xls, .csv)')
                        ->disk('local')
                        ->directory('imports/anggota')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                            'text/plain',
                        ])
                        ->maxSize(5120)
                        ->required()
                        ->helperText('Kolom wajib: nama_lengkap dan username. Kolom kode_anggota & password boleh dikosongkan (otomatis/pakai sandi bawaan).'),
                    TextInput::make('password_bawaan')
                        ->label('Kata sandi bawaan (opsional)')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->helperText('Dipakai otomatis untuk baris yang kolom password-nya kosong di Excel. Minimal 8 karakter.'),
                ])
                ->action(function (array $data): void {
                    $relPath = is_array($data['file']) ? reset($data['file']) : $data['file'];
                    $fullPath = Storage::disk('local')->path((string) $relPath);

                    try {
                        $hasil = app(AnggotaImportService::class)->impor(
                            $fullPath,
                            Otorisasi::userWajib(),
                            $data['password_bawaan'] ?? null
                        );
                    } catch (BisnisException $e) {
                        Notification::make()->title('Import gagal')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    } finally {
                        if ($relPath) {
                            Storage::disk('local')->delete((string) $relPath);
                        }
                    }

                    $berhasil = $hasil['berhasil'];
                    $gagal = $hasil['gagal'];

                    if ($gagal === []) {
                        Notification::make()
                            ->title("Import selesai ({$berhasil} anggota ditambahkan)")
                            ->success()
                            ->send();

                        return;
                    }

                    $ringkas = collect($gagal)
                        ->take(5)
                        ->map(fn (array $g) => "Baris {$g['baris']}: {$g['alasan']}")
                        ->implode("\n");
                    if (count($gagal) > 5) {
                        $ringkas .= "\n...dan " . (count($gagal) - 5) . ' baris gagal lainnya.';
                    }

                    $notif = Notification::make()
                        ->title($berhasil > 0
                            ? "Import sebagian berhasil ({$berhasil} berhasil, " . count($gagal) . ' gagal)'
                            : 'Tidak ada data yang berhasil diimpor (' . count($gagal) . ' baris gagal)')
                        ->body($ringkas)
                        ->persistent();

                    $berhasil > 0 ? $notif->warning()->send() : $notif->danger()->send();
                }),
            CreateAction::make(),
        ];
    }
}
