<?php

namespace App\Models;

use App\Models\Concerns\DiauditOtomatis;
use App\Enums\StatusAktif;
use App\Models\Concerns\HanyaMilikAnggota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kapal extends Model
{
    use DiauditOtomatis, HanyaMilikAnggota, SoftDeletes;

    protected $table = 'kapal';

    protected $fillable = ['kode_kapal', 'anggota_id', 'nama_kapal', 'nama_pemilik', 'no_registrasi', 'ukuran_gt', 'daya_pk', 'status', 'keterangan'];

    protected function casts(): array
    {
        return ['status' => StatusAktif::class, 'ukuran_gt' => 'decimal:2', 'daya_pk' => 'decimal:2'];
    }

    protected static function batasiKeAnggota(Builder $query, int $anggotaId): void
    {
        $query->where($query->getModel()->getTable() . '.anggota_id', $anggotaId);
    }

    public function anggota(): BelongsTo { return $this->belongsTo(Anggota::class); }
    public function suratRekomendasi(): HasMany { return $this->hasMany(SuratRekomendasi::class); }
    public function suratRekomendasiTerbaru(): HasOne { return $this->hasOne(SuratRekomendasi::class)->ofMany(['berlaku_sampai' => 'max', 'id' => 'max']); }
    public function pengajuan(): HasMany { return $this->hasMany(PengajuanPembelian::class); }
}
