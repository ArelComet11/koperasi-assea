<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $pengaturan->get('koperasi.nama')) @hasSection('title') — {{ $pengaturan->get('koperasi.nama') }} @endif</title>
    <meta name="description" content="@yield('description', $pengaturan->get('koperasi.deskripsi'))">
    <link rel="icon" href="{{ asset('images/logo-koperasi.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-mist">
    <a href="#konten" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Lewati ke konten</a>

    <header class="sticky top-0 z-40 border-b-2 border-gold bg-white/95 backdrop-blur" x-data="{ buka: false }">
        <div class="container-x flex items-center justify-between gap-4 py-2.5">
            <a href="{{ route('beranda') }}" class="flex min-w-0 items-center gap-3" aria-label="Beranda">
                <x-logo :size="48" />
                <span class="min-w-0 text-sm font-extrabold leading-tight text-brand-dark sm:text-base">
                    <span class="hidden sm:inline">Koperasi Serba Usaha</span><span class="sm:hidden">KSU</span><br>Assea Lorgan Mandiri
                </span>
            </a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Navigasi utama">
                @foreach(['beranda' => 'Beranda', 'profil' => 'Profil', 'layanan' => 'Layanan', 'berita.index' => 'Berita', 'galeri' => 'Galeri', 'kontak' => 'Kontak'] as $r => $label)
                    <a href="{{ route($r) }}" class="nav-link {{ request()->routeIs($r === 'berita.index' ? 'berita.*' : $r) ? 'nav-link-active' : '' }}">{{ $label }}</a>
                @endforeach
                <a href="{{ url('/anggota/login') }}" class="btn btn-primary ml-2"><i data-lucide="log-in" class="h-4 w-4"></i> Login Anggota</a>
            </nav>

            <button type="button" class="rounded-md p-2 text-brand-dark hover:bg-brand/10 lg:hidden" @click="buka = !buka" :aria-expanded="buka" aria-label="Menu">
                <i data-lucide="menu" class="h-6 w-6" x-show="!buka"></i>
                <i data-lucide="x" class="h-6 w-6" x-show="buka" x-cloak></i>
            </button>
        </div>

        <nav class="border-t border-brand/10 bg-white lg:hidden" x-show="buka" x-cloak x-transition aria-label="Navigasi seluler">
            <div class="container-x flex flex-col gap-1 py-3">
                @foreach(['beranda' => 'Beranda', 'profil' => 'Profil', 'layanan' => 'Layanan', 'berita.index' => 'Berita', 'galeri' => 'Galeri', 'kontak' => 'Kontak'] as $r => $label)
                    <a href="{{ route($r) }}" class="nav-link">{{ $label }}</a>
                @endforeach
                <a href="{{ url('/anggota/login') }}" class="btn btn-primary mt-2">Login Anggota</a>
            </div>
        </nav>
    </header>

    <main id="konten" class="flex-1">@yield('content')</main>

    <footer class="mt-16 bg-brand-dark text-white">
        <div class="h-1 bg-gold"></div>
        <div class="container-x grid gap-8 py-12 md:grid-cols-3">
            <div>
                <div class="flex items-center gap-3"><x-logo :size="52" /><span class="font-extrabold leading-tight">{{ $pengaturan->get('koperasi.nama') }}</span></div>
                <p class="mt-4 text-sm leading-relaxed text-white/80">{{ $pengaturan->get('koperasi.deskripsi') }}</p>
            </div>
            <div>
                <h2 class="text-sm font-bold uppercase tracking-widest text-gold">Tautan</h2>
                <ul class="mt-4 space-y-2 text-sm text-white/85">
                    <li><a class="hover:underline" href="{{ route('profil') }}">Profil Koperasi</a></li>
                    <li><a class="hover:underline" href="{{ route('layanan') }}">Layanan</a></li>
                    <li><a class="hover:underline" href="{{ route('berita.index') }}">Berita dan Informasi</a></li>
                    <li><a class="hover:underline" href="{{ route('galeri') }}">Galeri</a></li>
                    <li><a class="hover:underline" href="{{ url('/anggota/login') }}">Login Anggota</a></li>
                </ul>
            </div>
            <div>
                <h2 class="text-sm font-bold uppercase tracking-widest text-gold">Kontak</h2>
                <ul class="mt-4 space-y-2 text-sm text-white/85">
                    <li>{{ $pengaturan->get('koperasi.alamat') ?: 'Alamat belum diisi' }}</li>
                    @if($pengaturan->get('koperasi.telepon'))<li>Telp: {{ $pengaturan->get('koperasi.telepon') }}</li>@endif
                    @if($pengaturan->get('koperasi.email'))<li>{{ $pengaturan->get('koperasi.email') }}</li>@endif
                    @foreach(['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube'] as $k => $n)
                        @if(preg_match('#^https?://#', (string) $pengaturan->get('sosial.' . $k)))<li><a class="underline" rel="noopener" target="_blank" href="{{ $pengaturan->get('sosial.' . $k) }}">{{ $n }}</a></li>@endif
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10 py-4 text-center text-xs text-white/60">© {{ date('Y') }} {{ $pengaturan->get('koperasi.nama') }}. Hak cipta dilindungi.</div>
    </footer>
</body>
</html>
