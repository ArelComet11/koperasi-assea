<?php

namespace App\Enums;

use App\Enums\Concerns\LabelDariNilai;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusTagihan: string implements HasLabel, HasColor
{
    use LabelDariNilai;

    case BelumDibayar = 'belum_dibayar';
    case Lunas = 'lunas';
    case Dibatalkan = 'dibatalkan';

    public function getColor(): string
    {
        return match ($this) {
            self::BelumDibayar => 'warning',
            self::Lunas => 'success',
            self::Dibatalkan => 'danger',
        };
    }
}
