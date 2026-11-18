<?php

namespace App\Filament\Anggota\Resources\PengajuanSayaResource\Pages;

use App\Filament\Anggota\Resources\PengajuanSayaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPengajuanSaya extends ListRecords
{
    protected static string $resource = PengajuanSayaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Ajukan Pembelian BBM')];
    }
}
