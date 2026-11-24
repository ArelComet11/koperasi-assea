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
        'otomatis' => 'Otomatis sesuai Level',
        'emas_eksklusif' => 'Biru Royal & Aura Emas (Pimpinan)',
        'biru_tua' => 'Biru Tua Elegan (Pengurus Inti)',
        'biru_cerah' => 'Biru Cerah Modern (Manajer)',
        'biru_teal' => 'Biru Teal (Seksi / Staf)',
        'hijau_koperasi' => 'Hijau Koperasi Eksklusif',
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
        $tema = $this->tema_pangkat ?: 'otomatis';
        if ($tema === 'otomatis') {
            $tema = match ((int) $this->level) {
                1 => 'emas_eksklusif',
                2 => 'biru_tua',
                3 => 'biru_cerah',
                default => 'biru_teal',
            };
        }

        return match ($tema) {
            'emas_eksklusif' => 'radial-gradient(circle at 50% 30%, #3b82d6 0%, #1c53a8 52%, #0e2a5c 88%, #2e240c 100%)',
            'biru_tua' => 'radial-gradient(circle at 50% 30%, #2f69be 0%, #174087 58%, #0c224d 100%)',
            'biru_cerah' => 'radial-gradient(circle at 50% 30%, #3384de 0%, #1655a8 58%, #0c2d63 100%)',
            'hijau_koperasi' => 'radial-gradient(circle at 50% 30%, #14a65e 0%, #076b3d 58%, #053820 100%)',
            default => 'radial-gradient(circle at 50% 30%, #2484bd 0%, #125285 58%, #0a2a4d 100%)',
        };
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
