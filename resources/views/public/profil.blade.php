@extends('layouts.public')
@section('title', 'Profil Koperasi')

@section('content')
<section class="bg-white py-12"><div class="container-x"><p class="eyebrow">Profil</p><h1 class="section-title mt-1">Profil {{ $pengaturan->get('koperasi.nama') }}</h1><div class="gold-rule"></div>
    <p class="mt-5 max-w-3xl text-lg text-ink/80">{{ $pengaturan->get('koperasi.deskripsi') }}</p></div></section>

<div class="container-x space-y-6 py-12">
    @php $bagian = ['Sejarah' => $sejarah, 'Visi' => $visi, 'Misi' => $misi, 'Tujuan Koperasi' => $tujuan, 'Legalitas' => $legalitas]; @endphp
    @foreach($bagian as $judul => $isi)
        @if($isi)
            <section class="card" data-reveal><h2 class="text-xl font-extrabold text-brand-dark">{{ $judul }}</h2><div class="gold-rule"></div>
                <div class="prose-koperasi mt-4">{!! $isi !!}</div></section>
        @endif
    @endforeach

    @if(! array_filter($bagian))
        <p class="rounded-xl bg-white p-6 text-sm text-ink/70">Informasi profil sedang disiapkan oleh pengurus koperasi.</p>
    @endif

    @if($pengurus->isNotEmpty())
        <section data-reveal>
            <h2 class="text-xl font-extrabold text-brand-dark">Struktur Organisasi</h2><div class="gold-rule"></div>
            <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach($pengurus as $p)
                    <div class="card text-center">
                        @if($p->foto)<img src="{{ asset('storage/' . $p->foto) }}" alt="{{ $p->nama }}" loading="lazy" class="mx-auto h-24 w-24 rounded-full object-cover">
                        @else<div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-brand/10 text-brand"><i data-lucide="users" class="h-9 w-9"></i></div>@endif
                        <div class="mt-3 font-bold text-brand-dark">{{ $p->nama }}</div><div class="text-sm text-ink/70">{{ $p->jabatan }}</div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
