<?php

namespace App\Models;

use App\Support\Dec;
use Illuminate\Database\Eloquent\Model;

class StokBbm extends Model
{
    protected $table = 'stok_bbm';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['stok_fisik' => 'decimal:2', 'stok_dialokasikan' => 'decimal:2'];
    }

    public function getStokTersediaAttribute(): string
    {
        return Dec::n($this->stok_fisik);
    }
}
