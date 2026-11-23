<?php

namespace App\Filament\Admin\Resources\TagihanResource\Pages;

use App\Enums\StatusTagihan;
use App\Filament\Admin\Resources\TagihanResource;
use App\Models\PenyesuaianTagihan;
use App\Models\Tagihan;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTagihan extends ListRecords
{
    protected static string $resource = TagihanResource::class;

    public function getTabs(): array
    {
        $belumDibayar = Tagihan::query()->where('status', StatusTagihan::BelumDibayar->value)->count();
        $perluKoreksi = PenyesuaianTagihan::query()->where('diterapkan', false)->distinct('tagihan_id')->count('tagihan_id');

        return [
            'semua' => Tab::make('Semua'),
            'belum_dibayar' => Tab::make('Belum dibayar')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', StatusTagihan::BelumDibayar->value))
                ->badge($belumDibayar > 0 ? $belumDibayar : null)
                ->badgeColor('warning'),
            'perlu_koreksi' => Tab::make('Perlu koreksi / refund')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('penyesuaian', fn ($q) => $q->where('diterapkan', false)))
                ->badge($perluKoreksi > 0 ? $perluKoreksi : null)
                ->badgeColor('danger'),
            'lunas' => Tab::make('Lunas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', StatusTagihan::Lunas->value)),
        ];
    }
}
