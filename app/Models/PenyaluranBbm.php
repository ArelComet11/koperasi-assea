<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenyaluranBbm extends Model
{
    protected $table = 'penyaluran_bbm';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tanggal_penyaluran' => 'datetime', 'liter_disetujui' => 'decimal:2', 'liter_aktual' => 'decimal:2'];
    }

    public function pengajuan(): BelongsTo { return $this->belongsTo(PengajuanPembelian::class, 'pengajuan_id'); }
    public function alokasi(): BelongsTo { return $this->belongsTo(AlokasiBbm::class, 'alokasi_id'); }
    public function petugas(): BelongsTo { return $this->belongsTo(User::class, 'petugas_id'); }
}
