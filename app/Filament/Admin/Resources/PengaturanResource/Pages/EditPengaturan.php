<?php

namespace App\Filament\Admin\Resources\PengaturanResource\Pages;

use App\Filament\Admin\Resources\PengaturanResource;
use App\Services\KuotaService;
use App\Services\PasokanService;
use App\Services\PengaturanService;
use App\Support\Otorisasi;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPengaturan extends EditRecord
{
    protected static string $resource = PengaturanResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $nilai = $record->nilai;

        $data['nilai_string'] = $record->tipe === 'string' ? $nilai : null;
        $data['nilai_angka'] = $record->tipe === 'number' ? $nilai : null;
        $data['nilai_text'] = $record->tipe === 'text' ? $nilai : null;
        $data['nilai_rich'] = $record->tipe === 'richtext' ? $nilai : null;
        $data['nilai_file'] = $record->tipe === 'file' ? ($nilai ?: null) : null;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $nilai = match ($record->tipe) {
            'number' => $data['nilai_angka'] ?? null,
            'text' => $data['nilai_text'] ?? null,
            'richtext' => $data['nilai_rich'] ?? null,
            'file' => $data['nilai_file'] ?? null,
            default => $data['nilai_string'] ?? null,
        };

        if (is_array($nilai)) {
            $nilai = reset($nilai) ?: null;
        }

        app(PengaturanService::class)->set($record->kunci, $nilai, Otorisasi::userWajib());

        if ($record->kunci === 'pasokan.batas_bulanan') {
            app(PasokanService::class)->terapkanBatasBaru();
        } elseif ($record->kunci === 'kuota.surat_bulanan') {
            app(KuotaService::class)->terapkanBatasBaru();
        }

        return $record->refresh();
    }
}
