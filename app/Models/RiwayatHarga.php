<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatHarga extends Model
{
    protected $table = 'riwayat_harga';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['harga_per_liter' => 'decimal:2', 'berlaku_mulai' => 'datetime'];
    }

    public function pengubah(): BelongsTo { return $this->belongsTo(User::class, 'diubah_oleh'); }
}
