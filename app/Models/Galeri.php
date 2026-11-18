<?php

namespace App\Models;

use App\Models\Concerns\DiauditOtomatis;
use Illuminate\Database\Eloquent\Model;

class Galeri extends Model
{
    use DiauditOtomatis;

    protected $table = 'galeri';

    protected $fillable = ['judul', 'gambar', 'deskripsi', 'tanggal_kegiatan', 'urutan', 'tampil'];

    protected function casts(): array
    {
        return ['tanggal_kegiatan' => 'date', 'tampil' => 'boolean'];
    }
}
