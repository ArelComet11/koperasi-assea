<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $laporan['judul'] }}</title>
<style>
 @page{size:A4 landscape;margin:8mm}
 body{font-family:Calibri,Arial,Helvetica,sans-serif;color:#000;margin:16px;font-size:10px}
 .aksi{margin-bottom:10px}
 .bulan-blok{margin-bottom:24px}
 .bulan-blok + .bulan-blok{page-break-before:always}
 .kop-judul{text-align:center;font-weight:700;font-size:16px;line-height:1.35}
 .kop-sub{text-align:center;font-weight:700;font-size:12px;line-height:1.35}
 .subproduk{font-weight:700;font-size:11px;margin:10px 0 4px}
 table.ledger{width:100%;border-collapse:collapse}
 table.ledger th,table.ledger td{border:1px solid #000;padding:3px 5px;font-size:9.5px}
 table.ledger th{background:#1CE836;color:#000;font-weight:700;text-align:center}
 table.ledger thead tr:first-child th{border-top:2px solid #000}
 table.ledger thead tr:last-child th{border-bottom:2px solid #000}
 table.ledger td.num{text-align:right;white-space:nowrap}
 table.ledger td.ctr{text-align:center;white-space:nowrap}
 table.ledger td.lanjutan{background:#D9D9D9}
 .ttd-wrap{display:flex;justify-content:flex-end;margin-top:20px}
 .ttd{text-align:center;min-width:240px;font-size:10.5px;line-height:1.45}
 .ttd .nama{margin-top:52px;font-weight:700;text-decoration:underline}
 .ttd .jabatan{font-weight:700}
 @media print{.aksi{display:none} body{margin:0} table.ledger th,table.ledger td.lanjutan{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head>
<body>
@php
    $peng = app(\App\Services\PengaturanService::class);
    $noSpbun = (string) ($peng->get('koperasi.no_spbun', '18.208.032') ?: '18.208.032');
    $namaKetua = mb_strtoupper((string) ($peng->get('koperasi.nama_ketua', 'SYAFRIYANI') ?: 'SYAFRIYANI'));
    $fmt = fn ($n) => fmod((float) $n, 1.0) == 0.0 ? number_format((float) $n, 0, ',', '.') : number_format((float) $n, 2, ',', '.');
    $fmtGt = fn ($n) => fmod((float) $n, 1.0) == 0.0 ? number_format((float) $n, 0, ',', '.') : number_format((float) $n, 1, ',', '.');
@endphp
<div class="aksi"><button onclick="window.print()">Cetak / Simpan PDF</button></div>

@foreach($laporan['bulanan'] ?? [] as $b)
<div class="bulan-blok">
    <div class="kop-judul">LAPORAN PENJUALAN PRODUK SOLAR/BIO SOLAR</div>
    <div class="kop-sub">SPBUN : KSU ASSEA LORGAN MANDIRI</div>
    <div class="kop-sub">NO SPBUN : {{ $noSpbun }}</div>
    <div class="kop-sub">PERIODE : {{ $b['periode_label'] }}</div>

    <div class="subproduk">Laporan Penjualan Produk : Solar/Bio Solar</div>

    <table class="ledger">
        <thead>
            <tr>
                <th rowspan="2">No</th>
                <th rowspan="2">Tanggal</th>
                <th colspan="11">Keterangan</th>
            </tr>
            <tr>
                <th>Nama Kapal</th>
                <th>Nama Pemilik</th>
                <th>GT</th>
                <th>PK</th>
                <th>Rekomendasi</th>
                <th>JML LTR Yang Direkomendasikan<br>(Liter)</th>
                <th>STOCK AWAL<br>(Liter)</th>
                <th>PENERIMAAN<br>(Liter)</th>
                <th>PENJUALAN<br>(Liter)</th>
                <th>STOCK AKHIR<br>(Liter)</th>
                <th>Tujuan</th>
            </tr>
        </thead>
        <tbody>
        @forelse($b['rows'] as $row)
            <tr>
                <td class="ctr">{{ $row['no'] }}</td>
                <td class="ctr">{{ $row['tanggal_label'] ?? '' }}</td>
                <td>{{ $row['nama_kapal'] ?? '' }}</td>
                <td>{{ $row['nama_pemilik'] ?? '' }}</td>
                <td class="ctr">{{ $row['gt'] !== null ? $fmtGt($row['gt']) : '' }}</td>
                <td class="ctr">{{ $row['pk'] ?? '' }}</td>
                <td>{{ $row['rekomendasi'] ?? '' }}</td>
                <td class="num">{{ $row['vol_rekom'] !== null ? $fmt($row['vol_rekom']) : '' }}</td>
                <td class="num{{ ! empty($row['is_lanjutan']) ? ' lanjutan' : '' }}">{{ $row['stock_awal'] !== null ? $fmt($row['stock_awal']) : '' }}</td>
                <td class="num{{ ! empty($row['is_lanjutan']) ? ' lanjutan' : '' }}">{{ $row['penerimaan'] !== null ? $fmt($row['penerimaan']) : '' }}</td>
                <td class="num">{{ $row['penjualan'] !== null ? $fmt($row['penjualan']) : '' }}</td>
                <td class="num{{ ! empty($row['is_lanjutan']) ? ' lanjutan' : '' }}">{{ $row['stock_akhir'] !== null ? $fmt($row['stock_akhir']) : '' }}</td>
                <td class="ctr">{{ $row['tujuan'] ?? '' }}</td>
            </tr>
        @empty
            <tr><td colspan="13" class="ctr">Tidak ada data penjualan pada bulan ini.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="ttd-wrap">
        <div class="ttd">
            <div>{{ $b['tanggal_tanda_tangan'] }}</div>
            <div class="nama">{{ $namaKetua }}</div>
            <div class="jabatan">KETUA</div>
        </div>
    </div>
</div>
@endforeach
</body></html>
