@extends('layouts.public')
@section('title', 'Kontak')

@section('content')
<section class="bg-white py-12"><div class="container-x"><p class="eyebrow">Kontak</p><h1 class="section-title mt-1">Hubungi Kami</h1><div class="gold-rule"></div></div></section>
<div class="container-x grid gap-8 py-12 lg:grid-cols-2">
    <div class="card space-y-5" data-reveal>
        @foreach([
            ['map-pin', 'Alamat', $pengaturan->get('koperasi.alamat')],
            ['phone', 'Telepon', $pengaturan->get('koperasi.telepon')],
            ['mail', 'Email', $pengaturan->get('koperasi.email')],
            ['clock', 'Jam layanan', $pengaturan->get('koperasi.jam_layanan')],
        ] as [$ikon, $label, $nilai])
            <div class="flex gap-4">
                <div class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-brand/10 text-brand"><i data-lucide="{{ $ikon }}" class="h-5 w-5"></i></div>
                <div><div class="text-xs font-bold uppercase tracking-wider text-ink/60">{{ $label }}</div><div class="font-semibold text-ink">{{ $nilai ?: 'Belum diisi' }}</div></div>
            </div>
        @endforeach
    </div>
    <div data-reveal>
        @if($peta)
            <iframe src="{{ $peta }}" title="Peta lokasi koperasi" class="h-80 w-full rounded-2xl border border-brand/10" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        @else
            <div class="flex h-80 items-center justify-center rounded-2xl border border-dashed border-brand/30 bg-white p-6 text-center text-sm text-ink/60">Peta lokasi akan ditampilkan setelah alamat dan tautan peta diisi oleh admin.</div>
        @endif
    </div>
</div>
@endsection
