@extends('layouts.public')

@section('content')
<section class="relative overflow-hidden bg-white" data-hero>
    <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-brand/10 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-gold/15 blur-3xl"></div>
    <div class="container-x relative grid items-center gap-10 py-14 lg:grid-cols-2 lg:py-20">
        <div>
            <p class="eyebrow" data-hero-item>Koperasi Serba Usaha</p>
            <h1 class="mt-3 text-4xl font-extrabold leading-tight text-brand-dark sm:text-5xl" data-hero-item>{{ $pengaturan->get('koperasi.nama') }}</h1>
            <div class="gold-rule" data-hero-item></div>
            <p class="mt-5 max-w-xl text-lg leading-relaxed text-ink/80" data-hero-item>{{ $pengaturan->get('koperasi.deskripsi') }}</p>
            <div class="mt-8 flex flex-wrap gap-3" data-hero-item>
                <a href="{{ route('profil') }}" class="btn btn-outline">Lihat Profil</a>
                <a href="{{ url('/anggota/login') }}" class="btn btn-primary"><i data-lucide="log-in" class="h-4 w-4"></i> Login Anggota</a>
            </div>
            <p class="mt-4 text-xs text-ink/60" data-hero-item>Akun anggota dibuat oleh admin koperasi. Tidak tersedia pendaftaran mandiri.</p>
        </div>

        <div class="relative mx-auto w-full max-w-md" data-hero-item>
            <div class="rounded-3xl border border-brand/15 bg-white p-6 shadow-xl">
                <div class="flex justify-center"><x-logo :size="220" /></div>
                <div class="mt-6 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-xl bg-mist p-3"><i data-lucide="ship" class="mx-auto h-6 w-6 text-brand"></i><div class="mt-1 text-xs font-semibold text-ink/70">Kapal anggota</div></div>
                    <div class="rounded-xl bg-mist p-3"><i data-lucide="fuel" class="mx-auto h-6 w-6 text-brand"></i><div class="mt-1 text-xs font-semibold text-ink/70">Pelayanan BBM</div></div>
                    <div class="rounded-xl bg-mist p-3"><i data-lucide="file-check" class="mx-auto h-6 w-6 text-brand"></i><div class="mt-1 text-xs font-semibold text-ink/70">Surat rekomendasi</div></div>
                </div>
            </div>
        </div>
    </div>
    <svg class="block w-full text-mist" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true" height="40"><path fill="currentColor" d="M0 30c240 40 480 40 720 10s480-30 720 10v10H0z"/></svg>
</section>

<section class="container-x py-14">
    <div data-reveal><p class="eyebrow">Layanan</p><h2 class="section-title mt-1">Layanan koperasi</h2><div class="gold-rule"></div></div>
    <div class="mt-8 grid gap-5 md:grid-cols-3">
        @foreach([
            ['fuel', 'Penerimaan BBM', 'Pencatatan penerimaan BBM dari pemasok secara tertib dan dapat ditelusuri.'],
            ['clipboard-list', 'Pengajuan pembelian', 'Anggota mengajukan pembelian BBM secara daring sesuai kuota surat rekomendasi.'],
            ['shield-check', 'Penyaluran terkendali', 'Penyaluran dilakukan setelah persetujuan, pembayaran penuh, dan sesuai jadwal.'],
        ] as [$ikon, $judul, $isi])
            <div class="card" data-reveal>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand/10 text-brand"><i data-lucide="{{ $ikon }}" class="h-6 w-6"></i></div>
                <h3 class="mt-4 text-lg font-bold text-brand-dark">{{ $judul }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-ink/75">{{ $isi }}</p>
            </div>
        @endforeach
    </div>
    <div class="mt-6" data-reveal><a href="{{ route('layanan') }}" class="font-semibold text-brand hover:underline">Selengkapnya tentang layanan →</a></div>
</section>

<section class="bg-white py-14">
    <div class="container-x grid gap-8 lg:grid-cols-2">
        <div data-reveal>
            <p class="eyebrow">Informasi operasional</p>
            <h2 class="section-title mt-1">Ketentuan pelayanan BBM</h2><div class="gold-rule"></div>
            <ul class="mt-6 space-y-3 text-sm leading-relaxed text-ink/80">
                <li class="flex gap-3"><span class="mt-1.5 h-2 w-2 flex-none rounded-full bg-gold"></span>Pembelian BBM berdasarkan surat rekomendasi yang berlaku untuk kapal anggota.</li>
                <li class="flex gap-3"><span class="mt-1.5 h-2 w-2 flex-none rounded-full bg-gold"></span>Kuota pembelian maksimal {{ number_format((float) $kuota, 0, ',', '.') }} liter per surat rekomendasi setiap bulan.</li>
                <li class="flex gap-3"><span class="mt-1.5 h-2 w-2 flex-none rounded-full bg-gold"></span>Pembayaran dilakukan secara penuh saat pengambilan BBM.</li>
                <li class="flex gap-3"><span class="mt-1.5 h-2 w-2 flex-none rounded-full bg-gold"></span>Status pengajuan dan jadwal pembelian dapat dipantau melalui akun anggota.</li>
            </ul>
        </div>
        <div class="grid grid-cols-3 gap-4 self-center" data-reveal>
            @foreach([['Anggota aktif', number_format($statistik['anggota'], 0, ',', '.')], ['Kapal terdaftar', number_format($statistik['kapal'], 0, ',', '.')], ['Liter tersalurkan', number_format($statistik['liter'], 0, ',', '.')]] as [$l, $v])
                <div class="rounded-2xl border border-brand/10 bg-mist p-5 text-center"><div class="text-2xl font-extrabold text-brand-dark sm:text-3xl">{{ $v }}</div><div class="mt-1 text-xs font-semibold text-ink/70">{{ $l }}</div></div>
            @endforeach
        </div>
    </div>
</section>

@if($strukturPengurus->isNotEmpty())
<section class="container-x py-14">
    @include('public.partials.struktur-organisasi', ['strukturPengurus' => $strukturPengurus])
</section>
@endif

<section class="container-x py-14">
    <div class="flex items-end justify-between gap-4" data-reveal>
        <div><p class="eyebrow">Berita</p><h2 class="section-title mt-1">Berita dan informasi terbaru</h2><div class="gold-rule"></div></div>
        <a href="{{ route('berita.index') }}" class="hidden text-sm font-semibold text-brand hover:underline sm:block">Semua berita →</a>
    </div>
    @if($berita->isEmpty())
        <p class="mt-8 rounded-xl bg-white p-6 text-sm text-ink/70">Belum ada berita yang dipublikasikan.</p>
    @else
        <div class="mt-8 grid gap-6 md:grid-cols-3">
            @foreach($berita as $b) @include('public.partials.kartu-berita', ['b' => $b]) @endforeach
        </div>
    @endif
</section>

@if($galeri->isNotEmpty())
<section class="bg-white py-14">
    <div class="container-x">
        <div class="flex items-end justify-between gap-4" data-reveal>
            <div><p class="eyebrow">Galeri</p><h2 class="section-title mt-1">Dokumentasi kegiatan</h2><div class="gold-rule"></div></div>
            <a href="{{ route('galeri') }}" class="hidden text-sm font-semibold text-brand hover:underline sm:block">Lihat galeri →</a>
        </div>
        <div class="mt-8 grid grid-cols-2 gap-3 md:grid-cols-3">
            @foreach($galeri as $g)
                <figure class="group relative aspect-[4/3] overflow-hidden rounded-xl bg-mist" data-reveal>
                    <img src="{{ asset('storage/' . $g->gambar) }}" alt="{{ $g->judul }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                    <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-brand-dark/90 to-transparent p-3 text-sm font-semibold text-white opacity-0 transition group-hover:opacity-100">{{ $g->judul }}</figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="container-x py-14">
    <div class="rounded-3xl bg-brand-dark p-8 text-center text-white sm:p-12" data-reveal>
        <h2 class="text-2xl font-extrabold sm:text-3xl">Sudah menjadi anggota?</h2>
        <p class="mx-auto mt-3 max-w-xl text-white/80">Masuk untuk mengajukan pembelian BBM, melihat sisa kuota, jadwal, dan riwayat transaksi Anda.</p>
        <a href="{{ url('/anggota/login') }}" class="btn btn-light mt-6">Login Anggota</a>
    </div>
</section>
@endsection
