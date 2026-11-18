@extends('layouts.public')
@section('title', 'Berita dan Informasi')

@section('content')
<section class="bg-white py-12"><div class="container-x"><p class="eyebrow">Berita</p><h1 class="section-title mt-1">Berita dan Informasi</h1><div class="gold-rule"></div>
    <div class="mt-6 flex flex-wrap gap-2">
        <a href="{{ route('berita.index') }}" class="nav-link {{ $aktif ? '' : 'nav-link-active' }}">Semua</a>
        @foreach($kategori as $k)<a href="{{ route('berita.index', ['kategori' => $k->slug]) }}" class="nav-link {{ $aktif?->id === $k->id ? 'nav-link-active' : '' }}">{{ $k->nama }}</a>@endforeach
    </div></div></section>

<div class="container-x py-12">
    @if($berita->isEmpty())
        <p class="rounded-xl bg-white p-6 text-sm text-ink/70">Belum ada berita.</p>
    @else
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">@foreach($berita as $b) @include('public.partials.kartu-berita', ['b' => $b]) @endforeach</div>
        <div class="mt-10">{{ $berita->links() }}</div>
    @endif
</div>
@endsection
