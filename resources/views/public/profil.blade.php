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

    @if($strukturPengurus->isNotEmpty())
        <div class="pt-4">
            @include('public.partials.struktur-organisasi', ['strukturPengurus' => $strukturPengurus])
        </div>
    @endif
</div>
@endsection
