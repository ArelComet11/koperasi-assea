@props(['size' => 48, 'circle' => false])
@php
    $kustom = app(\App\Services\PengaturanService::class)->get('koperasi.logo');
    $src = $kustom ? app(\App\Services\PengaturanService::class)->logoUrl() : asset('images/logo-koperasi.png');
    $radius = $circle ? '9999px' : '10px';
    $bg = $circle ? '#FFFFFF' : '#F7F7F7';
    $ring = $circle ? 'none' : '0 0 0 1px rgba(7,91,55,.18)';
@endphp
{{-- Logo resmi ditampilkan dari file ASLI. Hanya jendela tampilan (viewport) yang dipotong ke area lambang;
     gambar tidak diubah, tidak diregangkan, dan rasio aslinya (661x377) dipertahankan. --}}
<span {{ $attributes }} role="img" aria-label="Logo Koperasi Serba Usaha Assea Lorgan Mandiri"
      style="display:inline-block;position:relative;overflow:hidden;flex:none;width:{{ $size }}px;height:{{ $size }}px;background:{{ $bg }};border-radius:{{ $radius }};box-shadow:{{ $ring }}">
    @if($kustom)
        <img src="{{ $src }}" alt="" style="width:100%;height:100%;object-fit:contain">
    @else
        <img src="{{ $src }}" alt="" style="position:absolute;max-width:none;height:auto;width:264.4%;left:-80.4%;top:-24.4%">
    @endif
</span>
