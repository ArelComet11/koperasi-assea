<?php

namespace App\Filament\Admin\Resources\KapalResource\Pages;

use App\Filament\Admin\Resources\KapalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKapal extends ManageRecords
{
    protected static string $resource = KapalResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
