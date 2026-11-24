<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $laporan['judul'] }}</title>
<style>
 body{font-family:Calibri,Arial,Helvetica,sans-serif;color:#000;margin:24px;font-size:12px}
 .kop-spbun{width:100%;text-align:center;margin-bottom:14px}
 .kop-judul{font-size:18px;font-weight:700;padding:4px 0;background:#fff}
 .kop-abu{font-size:15px;font-weight:700;padding:4px 0;background:#BFBFBF;border-top:1px solid #888;border-bottom:1px solid #888}
 .kop-abu + .kop-abu{border-top:0}
 .kop-periode{font-size:14px;font-weight:700;padding:4px 0;background:#fff;border-bottom:1px solid #d9d9d9}
 .subjudul{margin:14px 0 4px;font-size:13px}
 table{width:100%;border-collapse:collapse}
 th,td{border:1px solid #000;padding:5px 8px;font-size:12px}
 th{background:#1CE836;color:#000;font-weight:600;text-align:center;border-top:2px solid #000;border-bottom:2px solid #000}
 td.num{text-align:right}
 td.ctr{text-align:center}
 .sum{margin:10px 0;font-size:12px}
 .sum span{display:inline-block;margin-right:18px}
 .aksi{margin-bottom:12px}
 @media print{.aksi{display:none} body{margin:0} th,.kop-abu{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head>
<body>
@php
    $peng = app(\App\Services\PengaturanService::class);
    $noSpbun = $peng->get('koperasi.no_spbun', '18.208.032') ?: '18.208.032';
@endphp
<div class="aksi"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
<div class="kop-spbun">
    <div class="kop-judul">{{ mb_strtoupper($laporan['judul']) }}</div>
    <div class="kop-abu">SPBUN : KSU ASSEA LORGAN MANDIRI</div>
    <div class="kop-abu">NO SPBUN : {{ $noSpbun }}</div>
    <div class="kop-periode">PERIODE : {{ mb_strtoupper($laporan['periode']) }}</div>
</div>

@if(! empty($laporan['ringkasan']))
    <p class="sum">@foreach($laporan['ringkasan'] as $k => $v)<span>{{ $k }}: <strong>{{ is_float($v) ? number_format($v, 2, ',', '.') : $v }}</strong></span>@endforeach</p>
@endif

<div class="subjudul">{{ $laporan['subjudul'] ?? $laporan['judul'] }}</div>

<table>
    <thead>
        <tr>@foreach($laporan['headings'] as $h)<th>{{ mb_strtoupper($h) }}</th>@endforeach</tr>
    </thead>
    <tbody>
    @forelse($laporan['rows'] as $row)
        <tr>
            @foreach($row as $c)
                <td class="{{ is_float($c) ? 'num' : '' }}">{{ is_float($c) ? number_format($c, 2, ',', '.') : $c }}</td>
            @endforeach
        </tr>
    @empty
        <tr><td colspan="{{ count($laporan['headings']) }}" class="ctr">Tidak ada data.</td></tr>
    @endforelse
    </tbody>
</table>
</body></html>
