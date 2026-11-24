<?php

namespace App\Http\Controllers;

use App\Enums\StatusAktif;
use App\Models\Anggota;
use App\Models\Berita;
use App\Models\Galeri;
use App\Models\Kapal;
use App\Models\KategoriBerita;
use App\Models\PenyaluranBbm;
use App\Models\Pengurus;
use App\Services\PengaturanService;
use App\Support\Sanitizer;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function __construct(private PengaturanService $p)
    {
    }

    public function beranda()
    {
        return view('public.beranda', [
            'statistik' => [
                'anggota' => Anggota::query()->where('status', StatusAktif::Aktif->value)->count(),
                'kapal' => Kapal::query()->where('status', StatusAktif::Aktif->value)->count(),
                'liter' => (float) PenyaluranBbm::query()->sum('liter_aktual'),
            ],
            'berita' => Berita::query()->terbit()->with('kategori')->latest('published_at')->take(3)->get(),
            'galeri' => Galeri::query()->where('tampil', true)->orderBy('urutan')->latest('id')->take(6)->get(),
            'strukturPengurus' => Pengurus::strukturTerurut(),
            'kuota' => $this->p->get('kuota.surat_bulanan'),
        ]);
    }

    public function profil()
    {
        $bagian = fn (string $k) => Sanitizer::html($this->p->get($k));
        $struktur = Pengurus::strukturTerurut();

        return view('public.profil', [
            'sejarah' => $bagian('koperasi.sejarah'),
            'visi' => $bagian('koperasi.visi'),
            'misi' => $bagian('koperasi.misi'),
            'tujuan' => $bagian('koperasi.tujuan'),
            'legalitas' => $bagian('koperasi.legalitas'),
            'pengurus' => $struktur->flatten(1),
            'strukturPengurus' => $struktur,
        ]);
    }

    public function layanan()
    {
        return view('public.layanan', [
            'konten' => Sanitizer::html($this->p->get('layanan.konten')),
            'kuota' => $this->p->get('kuota.surat_bulanan'),
        ]);
    }

    public function beritaIndex(Request $request)
    {
        $q = Berita::query()->terbit()->with('kategori')->latest('published_at');
        $kategori = null;
        if ($slug = $request->query('kategori')) {
            $kategori = KategoriBerita::query()->where('slug', $slug)->first();
            $kategori ? $q->where('kategori_berita_id', $kategori->id) : $q->whereRaw('1 = 0');
        }

        return view('public.berita-index', [
            'berita' => $q->paginate(9)->withQueryString(),
            'kategori' => KategoriBerita::query()->orderBy('nama')->get(),
            'aktif' => $kategori,
        ]);
    }

    public function beritaShow(string $slug)
    {
        $berita = Berita::query()->terbit()->with(['kategori', 'penulis'])->where('slug', $slug)->firstOrFail();

        return view('public.berita-show', [
            'berita' => $berita,
            'konten' => Sanitizer::html($berita->konten),
            'lainnya' => Berita::query()->terbit()->whereKeyNot($berita->id)->latest('published_at')->take(3)->get(),
        ]);
    }

    public function galeri()
    {
        return view('public.galeri', [
            'galeri' => Galeri::query()->where('tampil', true)->orderBy('urutan')->latest('id')->paginate(12),
        ]);
    }

    public function kontak()
    {
        $peta = (string) $this->p->get('koperasi.peta', '');
        $aman = preg_match('#^https://(www\.google\.com/maps/embed|maps\.google\.com/maps)#', $peta) === 1 ? $peta : null;

        return view('public.kontak', ['peta' => $aman]);
    }
}
