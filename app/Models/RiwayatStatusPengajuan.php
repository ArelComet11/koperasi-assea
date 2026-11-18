<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatStatusPengajuan extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'riwayat_status_pengajuan';

    protected $guarded = ['id'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function pengajuan(): BelongsTo { return $this->belongsTo(PengajuanPembelian::class, 'pengajuan_id'); }
}
