@extends('layouts.public')
@section('title', 'Galeri')

@section('content')
<section class="bg-white py-12"><div class="container-x"><p class="eyebrow">Galeri</p><h1 class="section-title mt-1">Dokumentasi Kegiatan</h1><div class="gold-rule"></div></div></section>
<div class="container-x py-12" x-data="{ gambar: null, judul: '' }" @keydown.escape.window="gambar = null">
    @if($galeri->isEmpty())
        <p class="rounded-xl bg-white p-6 text-sm text-ink/70">Belum ada foto kegiatan.</p>
    @else
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
            @foreach($galeri as $g)
                <button type="button" class="group relative aspect-square overflow-hidden rounded-xl bg-white text-left" data-reveal @click="gambar = '{{ asset('storage/' . $g->gambar) }}'; judul = @js($g->judul)">
                    <img src="{{ asset('storage/' . $g->gambar) }}" alt="{{ $g->judul }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                    <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-brand-dark/90 to-transparent p-3 text-sm font-semibold text-white opacity-0 transition group-hover:opacity-100">{{ $g->judul }}</span>
                </button>
            @endforeach
        </div>
        <div class="mt-10">{{ $galeri->links() }}</div>
        <div x-show="gambar" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" @click="gambar = null" role="dialog" aria-modal="true">
            <figure class="max-h-full max-w-4xl"><img :src="gambar" :alt="judul" class="max-h-[80vh] rounded-xl"><figcaption class="mt-2 text-center text-sm text-white" x-text="judul"></figcaption></figure>
        </div>
    @endif
</div>
@endsection
