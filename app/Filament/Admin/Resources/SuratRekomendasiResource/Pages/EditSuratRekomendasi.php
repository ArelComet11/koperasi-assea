<?php

namespace App\Filament\Admin\Resources\SuratRekomendasiResource\Pages;

use App\Filament\Admin\Resources\SuratRekomendasiResource;
use App\Models\SuratRekomendasi;
use App\Services\KuotaService;
use Filament\Resources\Pages\EditRecord;

class EditSuratRekomendasi extends EditRecord
{
    protected static string $resource = SuratRekomendasiResource::class;

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        if ($record instanceof SuratRekomendasi) {
            app(KuotaService::class)->terapkanBatasBaru($record);
        }
    }
}
