<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $laporan['judul'] }}</title>
<style>
 @page{size:A4 portrait;margin:10mm}
 body{font-family:Calibri,Arial,Helvetica,sans-serif;color:#000;margin:18px;font-size:11px}
 .aksi{margin-bottom:10px}
 .bulan-blok{margin-bottom:24px}
 .bulan-blok + .bulan-blok{page-break-before:always}
 .kop-judul{text-align:center;font-weight:700;font-size:15px;line-height:1.35}
 .kop-sub{text-align:center;font-weight:700;font-size:14px;line-height:1.35}
 .subproduk{font-weight:700;font-size:11.5px;margin:12px 0 4px}
 table.stok{width:100%;border-collapse:collapse}
 table.stok th,table.stok td{border:1px solid #000;padding:4px 8px;font-size:10.5px}
 table.stok th{background:#1BF950;color:#000;font-weight:700;text-align:center;border-top:2px solid #000;border-bottom:2px solid #000}
 table.stok td.ctr{text-align:center}
 table.stok td.num{text-align:right;white-space:nowrap}
 table.stok tr.total-row td{font-weight:700;background:#E2EFDA;border-top:2px solid #000;border-bottom:2px solid #000}
 @media print{.aksi{display:none} body{margin:0} table.stok th,table.stok tr.total-row td{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head>
<body>
@php
    $peng = app(\App\Services\PengaturanService::class);
    $noSpbun = (string) ($peng->get('koperasi.no_spbun', '18.208.032') ?: '18.208.032');
    $fmt = fn ($n) => fmod((float) $n, 1.0) == 0.0 ? number_format((float) $n, 0, ',', '.') : number_format((float) $n, 2, ',', '.');
@endphp
<div class="aksi"><button onclick="window.print()">Cetak / Simpan PDF</button></div>

@foreach($laporan['bulanan'] ?? [] as $b)
<div class="bulan-blok">
    <div class="kop-judul">LAPORAN STOCK PRODUK SOLAR/BIO SOLAR</div>
    <div class="kop-sub">SPBUN : KSU ASSEA LORGAN MANDIRI</div>
    <div class="kop-sub">NO SPBUN : {{ $noSpbun }}</div>
    <div class="kop-sub">PERIODE : {{ $b['periode_label'] }}</div>

    <div class="subproduk">Laporan Stock Produk : Solar/Bio Solar</div>

    <table class="stok">
        <thead>
            <tr>
                <th style="width:7%">NO</th>
                <th style="width:19%">TANGGAL</th>
                <th style="width:18.5%">STOCK AWAL</th>
                <th style="width:18.5%">PENERIMAAN</th>
                <th style="width:18.5%">PENJUALAN</th>
                <th style="width:18.5%">STOCK AKHIR</th>
            </tr>
        </thead>
        <tbody>
        @forelse($b['rows'] as $row)
            <tr>
                <td class="ctr">{{ $row['no'] }}</td>
                <td class="ctr">{{ $row['tanggal_label'] ?? '' }}</td>
                <td class="num">{{ $row['stock_awal'] !== null ? $fmt($row['stock_awal']) : '' }}</td>
                <td class="num">{{ $row['penerimaan'] !== null ? $fmt($row['penerimaan']) : '' }}</td>
                <td class="num">{{ $row['penjualan'] !== null ? $fmt($row['penjualan']) : '' }}</td>
                <td class="num">{{ $row['stock_akhir'] !== null ? $fmt($row['stock_akhir']) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="ctr">Tidak ada data stok pada bulan ini.</td></tr>
        @endforelse
            <tr class="total-row">
                <td colspan="2" class="ctr">TOTAL</td>
                <td></td>
                <td class="num">{{ $fmt($b['total_penerimaan'] ?? 0) }}</td>
                <td class="num">{{ $fmt($b['total_penjualan'] ?? 0) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</div>
@endforeach
</body></html>
