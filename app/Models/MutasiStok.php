<?php

namespace App\Models;

use App\Enums\JenisMutasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Ledger stok: hanya INSERT. */
class MutasiStok extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'mutasi_stok';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisMutasi::class,
            'tanggal' => 'datetime',
            'delta_fisik' => 'decimal:2',
            'delta_alokasi' => 'decimal:2',
            'saldo_fisik' => 'decimal:2',
            'saldo_alokasi' => 'decimal:2',
        ];
    }

    public function referensi(): MorphTo { return $this->morphTo(); }
    public function pembuat(): BelongsTo { return $this->belongsTo(User::class, 'dibuat_oleh'); }
}
