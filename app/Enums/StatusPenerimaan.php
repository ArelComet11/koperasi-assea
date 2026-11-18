<?php

namespace App\Enums;

use App\Enums\Concerns\LabelDariNilai;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusPenerimaan: string implements HasLabel, HasColor
{
    use LabelDariNilai;

    case Terkonfirmasi = 'terkonfirmasi';
    case Dibatalkan = 'dibatalkan';

    public function getColor(): string
    {
        return match ($this) {
            self::Terkonfirmasi => 'success',
            self::Dibatalkan => 'danger',
        };
    }
}
