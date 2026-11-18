<?php

namespace App\Models;

use App\Enums\StatusAktif;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Anggota extends Model
{
    use SoftDeletes;

    protected $table = 'anggota';

    protected $fillable = ['kode_anggota', 'nama_lengkap', 'alamat', 'telepon', 'email', 'status', 'tanggal_bergabung', 'keterangan'];

    protected function casts(): array
    {
        return ['status' => StatusAktif::class, 'tanggal_bergabung' => 'date'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function kapal(): HasMany { return $this->hasMany(Kapal::class); }
    public function suratRekomendasi(): HasMany { return $this->hasMany(SuratRekomendasi::class); }
    public function pengajuan(): HasMany { return $this->hasMany(PengajuanPembelian::class); }
    public function tagihan(): HasMany { return $this->hasMany(Tagihan::class); }

    public function isAktif(): bool
    {
        return $this->status === StatusAktif::Aktif;
    }
}
