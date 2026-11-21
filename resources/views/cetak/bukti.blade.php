<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>Bukti Pembayaran {{ $bayar->nomor_pembayaran }}</title>
<style>
 body{font-family:Arial,Helvetica,sans-serif;color:#202820;margin:24px;font-size:13px;max-width:640px}
 .kop{display:flex;align-items:center;gap:14px;border-bottom:3px solid #E5B51B;padding-bottom:10px;margin-bottom:14px}
 h1{font-size:16px;margin:0;color:#075B37} table{width:100%;border-collapse:collapse} td{padding:6px 4px;border-bottom:1px solid #dfe6e1;vertical-align:top} td:first-child{width:38%;color:#555}
 .total{font-size:18px;font-weight:bold;color:#075B37} .batal{color:#D71920;font-weight:bold;border:2px solid #D71920;display:inline-block;padding:2px 10px;margin:8px 0}
 @media print{.aksi{display:none} body{margin:0}}
</style></head>
<body>
<div class="aksi"><button onclick="window.print()">Cetak</button></div>
<div class="kop"><x-logo :size="56" /><div><h1>KOPERASI SERBA USAHA ASSEA LORGAN MANDIRI</h1><div>Bukti Pembayaran</div></div></div>
@if($bayar->status !== \App\Enums\StatusPembayaranCatatan::Sah)<div class="batal">PEMBAYARAN DIBATALKAN</div>@endif
<table>
<tr><td>No. pembayaran</td><td><strong>{{ $bayar->nomor_pembayaran }}</strong></td></tr>
<tr><td>Tanggal</td><td>{{ $bayar->tanggal_pembayaran->translatedFormat('d F Y H:i') }}</td></tr>
<tr><td>Anggota</td><td>{{ $bayar->tagihan->anggota->nama_lengkap }} ({{ $bayar->tagihan->anggota->kode_anggota }})</td></tr>
<tr><td>No. tagihan</td><td>{{ $bayar->tagihan->nomor_tagihan }}</td></tr>
<tr><td>No. pengajuan</td><td>{{ $bayar->tagihan->pengajuan->nomor_pengajuan }}</td></tr>
<tr><td>Kapal</td><td>{{ $bayar->tagihan->pengajuan->kapal?->nama_kapal }}</td></tr>
<tr><td>Jumlah BBM</td><td>{{ \App\Support\Format::liter($bayar->tagihan->liter) }}</td></tr>
<tr><td>Harga per liter</td><td>{{ \App\Support\Format::rupiah($bayar->tagihan->harga_per_liter) }}</td></tr>
<tr><td>Metode</td><td>{{ $bayar->metode->getLabel() }}@if($bayar->nomor_bukti) · {{ $bayar->nomor_bukti }}@endif</td></tr>
<tr><td>Jumlah dibayar</td><td class="total">{{ \App\Support\Format::rupiah($bayar->jumlah) }}</td></tr>
<tr><td>Diterima oleh</td><td>{{ $bayar->penerima?->name }}</td></tr>
@if($bayar->tagihan->penyesuaian->isNotEmpty())
@php
    $selisihTotal = $bayar->tagihan->penyesuaian->sum('selisih');
    $adaTertunda = $bayar->tagihan->penyesuaian->contains(fn ($p) => ! $p->diterapkan);
    $nominalRefund = \App\Support\Dec::lt($selisihTotal, 0) ? \App\Support\Dec::sub('0', $selisihTotal) : '0.00';
    $neto = \App\Support\Dec::max0(\App\Support\Dec::sub($bayar->jumlah, $nominalRefund));
@endphp
<tr><td>Koreksi / pengembalian selisih</td><td>
    <strong>{{ \App\Support\Format::rupiah($nominalRefund) }}</strong>
    ({{ $adaTertunda ? 'Menunggu pengembalian ke anggota' : 'Sudah dikembalikan / dikoreksi' }})
    @if($bayar->tagihan->pengajuan?->penyaluran)
        <br><small>Realisasi penyaluran aktual: {{ \App\Support\Format::liter($bayar->tagihan->pengajuan->penyaluran->liter_aktual) }} · Nilai neto: {{ \App\Support\Format::rupiah($neto) }}</small>
    @endif
</td></tr>
@endif
@if($bayar->status !== \App\Enums\StatusPembayaranCatatan::Sah)
<tr><td>Waktu dibatalkan</td><td>{{ $bayar->dibatalkan_pada?->translatedFormat('d F Y H:i') ?? '-' }}</td></tr>
<tr><td>Alasan pembatalan</td><td>{{ $bayar->alasan_pembatalan ?: '-' }}</td></tr>
@endif
</table>
</body></html>
