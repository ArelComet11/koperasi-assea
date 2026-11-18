<?php

namespace App\Enums;

use App\Enums\Concerns\LabelDariNilai;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusBerita: string implements HasLabel, HasColor
{
    use LabelDariNilai;

    case Draft = 'draft';
    case Terbit = 'terbit';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Terbit => 'success',
        };
    }
}
