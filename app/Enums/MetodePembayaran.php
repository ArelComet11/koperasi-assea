<?php

namespace App\Enums;

use App\Enums\Concerns\LabelDariNilai;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MetodePembayaran: string implements HasLabel, HasColor
{
    use LabelDariNilai;

    case Tunai = 'tunai';
    case Transfer = 'transfer';
    case Lainnya = 'lainnya';

    public function getColor(): string
    {
        return match ($this) {
            self::Tunai => 'success',
            self::Transfer => 'info',
            self::Lainnya => 'gray',
        };
    }
}
