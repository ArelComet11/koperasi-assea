<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $laporan['judul'] }}</title>
<style>
 @page{size:A4 portrait;margin:12mm}
 body{font-family:Calibri,Arial,Helvetica,sans-serif;color:#000;margin:20px;font-size:12px}
 .aksi{margin-bottom:12px}
 .kop-judul{text-align:center;font-weight:700;font-size:15px;line-height:1.4;margin-bottom:12px}
 .meta{border-collapse:collapse;margin-bottom:12px;width:auto}
 .meta td{border:0;padding:2px 10px 2px 0;font-weight:700;font-size:12px;text-align:left}
 table.realisasi{width:100%;border-collapse:collapse}
 table.realisasi th,table.realisasi td{border:1px solid #000;padding:5px 8px;font-size:12px}
 table.realisasi th{background:#1CE836;color:#000;font-weight:700;text-align:center;border-top:2px solid #000;border-bottom:2px solid #000}
 table.realisasi td.num{text-align:right}
 table.realisasi tr.total-row td{font-weight:700;background:#E2EFDA;border-top:2px solid #000;border-bottom:2px solid #000}
 .ttd-wrap{display:flex;justify-content:flex-end;margin-top:24px}
 .ttd{text-align:center;min-width:270px;font-size:12px;line-height:1.45}
 .ttd .org{font-weight:700}
 .ttd .nama{margin-top:48px;font-weight:700;text-decoration:underline}
 @media print{.aksi{display:none} body{margin:0} table.realisasi th,table.realisasi tr.total-row td{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head>
<body>
@php
    $peng = app(\App\Services\PengaturanService::class);
    $noSpbun = (string) ($peng->get('koperasi.no_spbun', '18.208.032') ?: '18.208.032');
    $alamat = (string) ($peng->get('koperasi.alamat') ?: 'Jl. Babalan Gg. Tenggiri No. 45 Pangkalan Brandan');
    $ketua = trim((string) ($peng->get('koperasi.nama_ketua', 'Syafriyani') ?: 'Syafriyani'));
    $namaPemilik = mb_convert_case(mb_strtolower($ketua), MB_CASE_TITLE, 'UTF-8');
    $namaKetuaUpper = mb_strtoupper($ketua);
    $telepon = (string) ($peng->get('koperasi.telepon') ?: '081361923255');
    $real = $laporan['realisasi'] ?? [];
    $tahun = (int) ($real['tahun'] ?? now()->year);
    $fmt = fn ($n) => fmod((float) $n, 1.0) == 0.0 ? number_format((float) $n, 0, ',', '.') : number_format((float) $n, 2, ',', '.');
@endphp
<div class="aksi"><button onclick="window.print()">Cetak / Simpan PDF</button></div>

<div class="kop-judul">
    Data Realisasi Penerimaan dan Penjualan BBM<br>
    Di Lembaga Penyalur Periode : Januari – Desember {{ $tahun }}
</div>

<table class="meta">
    <tr><td>Nomor Penyalur</td><td>: {{ $noSpbun }}</td></tr>
    <tr><td>Nama Penyalur</td><td>: KSU ASSEA LORGAN MANDIRI</td></tr>
    <tr><td>Alamat/ Telpon</td><td>: {{ $alamat }}</td></tr>
    <tr><td>Nama Pemilik Penyalur</td><td>: {{ $namaPemilik }}</td></tr>
    <tr><td>No Telp/HP</td><td>: {{ $telepon }}</td></tr>
</table>

<table class="realisasi">
    <thead>
        <tr>
            <th>Bulan</th>
            <th>Saldo Awal</th>
            <th>Penerimaan</th>
            <th>Penjualan</th>
            <th>Saldo Akhir Administrasi</th>
            <th>Saldo Akhir Pemeriksaan Fisik</th>
        </tr>
    </thead>
    <tbody>
    @foreach($real['bulan'] ?? [] as $item)
        <tr>
            <td>{{ $item['bulan'] }}</td>
            <td class="num">{{ $fmt($item['saldo_awal']) }}</td>
            <td class="num">{{ $item['penerimaan'] !== null ? $fmt($item['penerimaan']) : '' }}</td>
            <td class="num">{{ $item['penjualan'] !== null ? $fmt($item['penjualan']) : '' }}</td>
            <td class="num">{{ $fmt($item['saldo_akhir_adm']) }}</td>
            <td class="num">{{ $fmt($item['saldo_akhir_fisik']) }}</td>
        </tr>
    @endforeach
        <tr class="total-row">
            <td>Total</td>
            <td class="num">{{ $fmt($real['total']['saldo_awal'] ?? 0) }}</td>
            <td class="num">{{ $fmt($real['total']['penerimaan'] ?? 0) }}</td>
            <td class="num">{{ $fmt($real['total']['penjualan'] ?? 0) }}</td>
            <td class="num">{{ $fmt($real['total']['saldo_akhir_adm'] ?? 0) }}</td>
            <td class="num">{{ $fmt($real['total']['saldo_akhir_fisik'] ?? 0) }}</td>
        </tr>
    </tbody>
</table>

<div class="ttd-wrap">
    <div class="ttd">
        <div>Tanggal, 31 Desember {{ $tahun }}</div>
        <div>Hormat kami,</div>
        <div class="org">SPBU-N KOPERASI ASSEA LORGAN MANDIRI</div>
        <div class="nama">{{ $namaKetuaUpper }}</div>
        <div>Ketua</div>
    </div>
</div>
</body></html>
