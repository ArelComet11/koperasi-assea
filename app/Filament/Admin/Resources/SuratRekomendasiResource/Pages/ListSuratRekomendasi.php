<?php

namespace App\Filament\Admin\Resources\SuratRekomendasiResource\Pages;

use App\Filament\Admin\Resources\SuratRekomendasiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuratRekomendasi extends ListRecords
{
    protected static string $resource = SuratRekomendasiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
