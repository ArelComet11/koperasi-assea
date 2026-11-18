<?php

namespace App\Filament\Admin\Resources\GaleriResource\Pages;

use App\Filament\Admin\Resources\GaleriResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGaleri extends ManageRecords
{
    protected static string $resource = GaleriResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
