<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Models\User;
use App\Support\Dec;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PengaturanService
{
    private const CACHE = 'pengaturan.semua';

    /** Definisi kunci pengaturan: kunci => [tipe, kelompok, label, nilai awal]. Dipakai seeder dan UI. */
    public static function definisi(): array
    {
        return [
            'koperasi.nama' => ['string', 'Identitas', 'Nama koperasi', 'Koperasi Serba Usaha Assea Lorgan Mandiri'],
            'koperasi.no_spbun' => ['string', 'Identitas', 'Nomor SPBUN', '18.208.032'],
            'koperasi.nama_ketua' => ['string', 'Identitas', 'Nama Ketua (penandatangan laporan)', 'SYAFRIYANI'],
            'koperasi.logo' => ['file', 'Identitas', 'Logo (kosongkan untuk memakai logo resmi bawaan)', null],
            'koperasi.deskripsi' => ['text', 'Identitas', 'Deskripsi singkat', 'Koperasi yang melayani penerimaan dan penyaluran BBM kepada anggota sesuai ketentuan dan surat rekomendasi.'],
            'koperasi.alamat' => ['text', 'Kontak', 'Alamat', null],
            'koperasi.telepon' => ['string', 'Kontak', 'Nomor telepon', null],
            'koperasi.email' => ['string', 'Kontak', 'Email', null],
            'koperasi.peta' => ['string', 'Kontak', 'URL embed Google Maps (src dari kode sematan)', null],
            'koperasi.jam_layanan' => ['string', 'Kontak', 'Jam layanan', null],
            'koperasi.sejarah' => ['richtext', 'Profil', 'Sejarah', null],
            'koperasi.visi' => ['richtext', 'Profil', 'Visi', null],
            'koperasi.misi' => ['richtext', 'Profil', 'Misi', null],
            'koperasi.tujuan' => ['richtext', 'Profil', 'Tujuan koperasi', null],
            'koperasi.legalitas' => ['richtext', 'Profil', 'Legalitas', null],
            'layanan.konten' => ['richtext', 'Layanan', 'Informasi layanan', null],
            'sosial.facebook' => ['string', 'Media sosial', 'Facebook (URL)', null],
            'sosial.instagram' => ['string', 'Media sosial', 'Instagram (URL)', null],
            'sosial.youtube' => ['string', 'Media sosial', 'YouTube (URL)', null],
            'sosial.whatsapp' => ['string', 'Media sosial', 'WhatsApp (URL atau nomor)', null],
            'kuota.surat_bulanan' => ['number', 'Operasional', 'Batas kuota per surat rekomendasi per bulan (liter)', '1000'],
            'pasokan.batas_bulanan' => ['number', 'Operasional', 'Batas pasokan BBM per bulan (liter) — 10 tangki x 16.000 liter', '160000'],
        ];
    }

    public function semua(): array
    {
        return Cache::rememberForever(self::CACHE, fn () => Pengaturan::query()->pluck('nilai', 'kunci')->all());
    }

    public function get(string $kunci, mixed $default = null): mixed
    {
        $v = $this->semua()[$kunci] ?? null;

        return ($v === null || $v === '') ? ($default ?? (self::definisi()[$kunci][3] ?? null)) : $v;
    }

    public function angka(string $kunci): string
    {
        return Dec::n($this->get($kunci, 0));
    }

    public function set(string $kunci, mixed $nilai, ?User $user = null): void
    {
        $baris = Pengaturan::query()->where('kunci', $kunci)->first();
        $lama = $baris?->nilai;

        if (! $baris) {
            [$tipe, $kelompok, $label] = self::definisi()[$kunci] ?? ['string', 'Lainnya', $kunci];
            $baris = new Pengaturan();
            $baris->forceFill(['kunci' => $kunci, 'tipe' => $tipe, 'kelompok' => $kelompok, 'label' => $label]);
        }

        $baris->nilai = $nilai === null ? null : (string) $nilai;
        $baris->save();
        Cache::forget(self::CACHE);

        if ((string) $lama !== (string) $nilai) {
            AuditService::catat('pengaturan.diubah', $baris, ['nilai' => $this->ringkas($kunci, $lama)], ['nilai' => $this->ringkas($kunci, $nilai)], $kunci, $user);
        }
    }

    public function logoUrl(): string
    {
        $path = $this->get('koperasi.logo');

        return $path && Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : asset('images/logo-koperasi.png');
    }

    private function ringkas(string $kunci, mixed $v): ?string
    {
        $v = $v === null ? null : (string) $v;

        return $v !== null && mb_strlen($v) > 200 ? mb_substr($v, 0, 200) . '…' : $v;
    }
}
