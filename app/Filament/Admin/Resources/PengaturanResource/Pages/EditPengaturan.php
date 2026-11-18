<?php

namespace App\Filament\Admin\Resources\PengaturanResource\Pages;

use App\Filament\Admin\Resources\PengaturanResource;
use Filament\Resources\Pages\EditRecord;
use App\Services\PasokanService;
use App\Services\PengaturanService;
use Illuminate\Database\Eloquent\Model;

class EditPengaturan extends EditRecord
{
    protected static string $resource = PengaturanResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $nilai = $this->getRecord()->nilai;
        $data['nilai_string'] = $data['nilai_text'] = $data['nilai_rich'] = $nilai;
        $data['nilai_angka'] = $nilai;
        $data['nilai_file'] = $nilai ?: null;

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

        app(PengaturanService::class)->set($record->kunci, $nilai, auth()->user());

        if ($record->kunci === 'pasokan.batas_bulanan') {
            app(PasokanService::class)->terapkanBatasBaru();
        }

        return $record->refresh();
    }
}
