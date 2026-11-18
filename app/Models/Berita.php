<?php

namespace App\Models;

use App\Models\Concerns\DiauditOtomatis;
use App\Enums\StatusBerita;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Berita extends Model
{
    use DiauditOtomatis, SoftDeletes;

    protected $table = 'berita';

    protected $fillable = ['kategori_berita_id', 'penulis_id', 'judul', 'slug', 'ringkasan', 'konten', 'gambar', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['status' => StatusBerita::class, 'published_at' => 'datetime'];
    }

    public function kategori(): BelongsTo { return $this->belongsTo(KategoriBerita::class, 'kategori_berita_id'); }
    public function penulis(): BelongsTo { return $this->belongsTo(User::class, 'penulis_id'); }

    public function scopeTerbit(Builder $q): Builder
    {
        return $q->where('status', StatusBerita::Terbit->value)->where('published_at', '<=', now());
    }
}
