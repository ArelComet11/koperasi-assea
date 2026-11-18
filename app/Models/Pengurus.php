<?php

namespace App\Models;

use App\Models\Concerns\DiauditOtomatis;
use Illuminate\Database\Eloquent\Model;

class Pengurus extends Model
{
    use DiauditOtomatis;

    protected $table = 'pengurus';

    protected $fillable = ['nama', 'jabatan', 'foto', 'urutan', 'tampil'];

    protected function casts(): array
    {
        return ['tampil' => 'boolean'];
    }
}
