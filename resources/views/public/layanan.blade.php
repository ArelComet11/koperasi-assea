@extends('layouts.public')
@section('title', 'Layanan')

@section('content')
<section class="bg-white py-12"><div class="container-x"><p class="eyebrow">Layanan</p><h1 class="section-title mt-1">Layanan Koperasi</h1><div class="gold-rule"></div>
    <p class="mt-5 max-w-3xl text-lg text-ink/80">Pelayanan penerimaan dan penyaluran BBM kepada anggota sesuai ketentuan dan surat rekomendasi.</p></div></section>

<div class="container-x space-y-10 py-12">
    @if($konten)<section class="card" data-reveal><div class="prose-koperasi">{!! $konten !!}</div></section>@endif

    <section data-reveal>
        <h2 class="text-xl font-extrabold text-brand-dark">Alur pembelian BBM anggota</h2><div class="gold-rule"></div>
        <ol class="mt-6 grid gap-4 md:grid-cols-2">
            @foreach([
                'Anggota memiliki akun dan surat rekomendasi yang berlaku untuk kapalnya.',
                'Anggota login dan mengajukan pembelian BBM (kapal, surat rekomendasi, jumlah liter).',
                'Sistem memeriksa kuota surat rekomendasi (maksimal ' . number_format((float) $kuota, 0, ',', '.') . ' liter per bulan), stok, dan batas pasokan.',
                'Admin menyetujui atau menolak pengajuan. Harga dikunci saat persetujuan.',
                'Admin mengatur jadwal pembelian atau penyaluran.',
                'Anggota datang sesuai jadwal, membayar penuh, dan BBM disalurkan. Transaksi selesai.',
            ] as $i => $langkah)
                <li class="card flex gap-4"><span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-brand font-bold text-white">{{ $i + 1 }}</span><span class="text-sm leading-relaxed text-ink/80">{{ $langkah }}</span></li>
            @endforeach
        </ol>
    </section>
    <div class="text-center" data-reveal><a href="{{ url('/anggota/login') }}" class="btn btn-primary">Login Anggota</a></div>
</div>
@endsection
