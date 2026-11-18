<?php

namespace App\Enums;

use App\Enums\Concerns\LabelDariNilai;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusPembayaranCatatan: string implements HasLabel, HasColor
{
    use LabelDariNilai;

    case Sah = 'sah';
    case Dibatalkan = 'dibatalkan';

    public function getColor(): string
    {
        return match ($this) {
            self::Sah => 'success',
            self::Dibatalkan => 'danger',
        };
    }
}
