<?php

namespace App\Filament\Admin\Resources\RiwayatHargaResource\Pages;

use App\Filament\Admin\Resources\RiwayatHargaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use App\Filament\Concerns\Bisnis;
use App\Models\RiwayatHarga;
use App\Services\HargaService;
use Filament\Support\Exceptions\Halt;

class ManageRiwayatHarga extends ManageRecords
{
    protected static string $resource = RiwayatHargaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Ubah harga')->icon('heroicon-o-pencil-square')
                ->visible(fn () => auth()->user()->can('harga.update'))
                ->using(function (array $data): RiwayatHarga {
                    $baris = Bisnis::jalankan(fn () => app(HargaService::class)->ubah($data['harga_per_liter'], $data['alasan'], auth()->user()), 'Harga BBM diperbarui.');

                    if (! $baris instanceof RiwayatHarga) {
                        throw new Halt();
                    }

                    return $baris;
                })
                ->successNotification(null),
        ];
    }
}
