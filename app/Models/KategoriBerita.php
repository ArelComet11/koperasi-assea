<?php

namespace App\Models;

use App\Models\Concerns\DiauditOtomatis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriBerita extends Model
{
    use DiauditOtomatis;

    protected $table = 'kategori_berita';

    protected $fillable = ['nama', 'slug'];

    public function berita(): HasMany { return $this->hasMany(Berita::class); }
}
