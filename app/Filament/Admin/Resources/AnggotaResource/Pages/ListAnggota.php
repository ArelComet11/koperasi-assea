<?php

namespace App\Filament\Admin\Resources\AnggotaResource\Pages;

use App\Filament\Admin\Resources\AnggotaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAnggota extends ListRecords
{
    protected static string $resource = AnggotaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
