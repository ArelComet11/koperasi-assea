@extends('layouts.public')
@section('title', $berita->judul)
@section('description', \Illuminate\Support\Str::limit(strip_tags($berita->ringkasan ?: $konten), 160))

@section('content')
<article class="container-x max-w-3xl py-12">
    <a href="{{ route('berita.index') }}" class="text-sm font-semibold text-brand hover:underline">← Kembali ke berita</a>
    <div class="mt-4 flex items-center gap-2 text-xs font-semibold text-ink/60">
        @if($berita->kategori)<span class="rounded-full bg-brand/10 px-2 py-0.5 text-brand-dark">{{ $berita->kategori->nama }}</span>@endif
        <time datetime="{{ $berita->published_at?->toDateString() }}">{{ $berita->published_at?->translatedFormat('l, d F Y') }}</time>
    </div>
    <h1 class="mt-3 text-3xl font-extrabold leading-tight text-brand-dark sm:text-4xl">{{ $berita->judul }}</h1><div class="gold-rule"></div>
    @if($berita->gambar)<img src="{{ asset('storage/' . $berita->gambar) }}" alt="{{ $berita->judul }}" class="mt-6 w-full rounded-2xl object-cover">@endif
    <div class="prose-koperasi mt-8">{!! $konten !!}</div>
</article>

@if($lainnya->isNotEmpty())
<section class="container-x pb-12"><h2 class="text-xl font-extrabold text-brand-dark">Berita lainnya</h2>
    <div class="mt-6 grid gap-6 md:grid-cols-3">@foreach($lainnya as $b) @include('public.partials.kartu-berita', ['b' => $b]) @endforeach</div></section>
@endif
@endsection
