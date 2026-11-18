<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Audit log: hanya INSERT. Tidak ada UI untuk mengubah/menghapus. */
class AktivitasAdmin extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'aktivitas_admin';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data_lama' => 'array', 'data_baru' => 'array'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
