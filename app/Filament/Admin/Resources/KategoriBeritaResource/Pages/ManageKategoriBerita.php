<?php

namespace App\Filament\Admin\Resources\KategoriBeritaResource\Pages;

use App\Filament\Admin\Resources\KategoriBeritaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKategoriBerita extends ManageRecords
{
    protected static string $resource = KategoriBeritaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
