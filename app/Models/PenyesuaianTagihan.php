<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenyesuaianTagihan extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'penyesuaian_tagihan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['total_lama' => 'decimal:2', 'total_baru' => 'decimal:2', 'selisih' => 'decimal:2', 'diterapkan' => 'boolean'];
    }

    public function tagihan(): BelongsTo { return $this->belongsTo(Tagihan::class); }
}
