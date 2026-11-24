<?php

namespace App\Models;

use App\Models\Concerns\DiauditOtomatis;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Pengurus extends Model
{
    use DiauditOtomatis;

    protected $table = 'pengurus';

    public const LEVEL_OPSI = [
        1 => 'Level 1 — Pimpinan / Ketua',
        2 => 'Level 2 — Pengurus Inti (Bendahara & Sekretaris)',
        3 => 'Level 3 — Manajer',
        4 => 'Level 4 — Seksi / Pelaksana',
    ];

    public const LEVEL_RINGKAS = [
        1 => 'Pucuk Pimpinan',
        2 => 'Pengurus Inti',
        3 => 'Manajer',
        4 => 'Seksi & Pelaksana',
    ];

    public const TEMA_OPSI = [
        'otomatis' => 'Otomatis sesuai Level (Tema Hijau Koperasi)',
        'hijau_eksklusif' => 'Hijau Zamrud & Aura Emas (Pimpinan)',
        'hijau_tua' => 'Hijau Tua Elegan (Pengurus Inti)',
        'hijau_cerah' => 'Hijau Koperasi Cerah (Manajer)',
        'hijau_teal' => 'Hijau Zamrud Lembut (Seksi / Staf)',
        'biru_navy' => 'Biru Navy Klasik (Opsional)',
    ];

    protected $fillable = ['nama', 'jabatan', 'level', 'tema_pangkat', 'foto', 'foto_transparan', 'urutan', 'tampil'];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'urutan' => 'integer',
            'foto_transparan' => 'boolean',
            'tampil' => 'boolean',
        ];
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('tampil', true);
    }

    public function scopeUrutStruktur(Builder $query): Builder
    {
        return $query->orderBy('level')->orderBy('urutan')->orderBy('id');
    }

    /** @return Collection<int, Collection<int, self>> */
    public static function strukturTerurut(): Collection
    {
        return static::query()
            ->aktif()
            ->urutStruktur()
            ->get()
            ->groupBy(fn (self $p) => max(1, min(4, (int) ($p->level ?: 4))));
    }

    public function gradasiLatar(): string
    {
        return 'radial-gradient(circle at 50% 28%, #16ad62 0%, #078B4B 52%, #053d24 100%)';
    }

    public function isFotoTransparan(): bool
    {
        return (bool) $this->foto_transparan || str_ends_with(strtolower((string) $this->foto), '.png');
    }

    public function fotoUrl(): ?string
    {
        return $this->foto ? asset('storage/' . ltrim($this->foto, '/')) : null;
    }

    public function inisial(): string
    {
        $bersih = preg_replace('/\b(H|Hj|Dr|Drs|Ir|Prof)\.\s*/iu', '', $this->nama) ?? $this->nama;
        $utama = trim(explode(',', $bersih)[0]);
        $kata = array_values(array_filter(preg_split('/\s+/', $utama) ?: []));

        return mb_strtoupper(mb_substr($kata[0] ?? 'P', 0, 1) . mb_substr($kata[1] ?? '', 0, 1));
    }
}
