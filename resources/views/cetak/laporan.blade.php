<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $laporan['judul'] }}</title>
<style>
 body{font-family:Arial,Helvetica,sans-serif;color:#202820;margin:24px;font-size:12px}
 .kop{display:flex;align-items:center;gap:14px;border-bottom:3px solid #E5B51B;padding-bottom:10px;margin-bottom:14px}
 h1{font-size:16px;margin:0;color:#075B37} h2{font-size:14px;margin:12px 0 2px}
 table{width:100%;border-collapse:collapse;margin-top:10px} th,td{border:1px solid #9aa89f;padding:4px 6px;text-align:left} th{background:#e6f2ec}
 .sum span{display:inline-block;margin-right:18px} .aksi{margin-bottom:12px}
 @media print{.aksi{display:none} body{margin:0}}
</style></head>
<body>
<div class="aksi"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
<div class="kop"><x-logo :size="56" /><div><h1>KOPERASI SERBA USAHA ASSEA LORGAN MANDIRI</h1><div>{{ app(\App\Services\PengaturanService::class)->get('koperasi.alamat') }}</div></div></div>
<h2>{{ $laporan['judul'] }}</h2><div>Periode: {{ $laporan['periode'] }} · Dicetak {{ now()->translatedFormat('d F Y H:i') }}</div>
@if($laporan['ringkasan'])<p class="sum">@foreach($laporan['ringkasan'] as $k => $v)<span>{{ $k }}: <strong>{{ is_float($v) ? number_format($v, 2, ',', '.') : $v }}</strong></span>@endforeach</p>@endif
<table><thead><tr>@foreach($laporan['headings'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead><tbody>
@forelse($laporan['rows'] as $row)<tr>@foreach($row as $c)<td>{{ is_float($c) ? number_format($c, 2, ',', '.') : $c }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ count($laporan['headings']) }}" style="text-align:center">Tidak ada data.</td></tr>@endforelse
</tbody></table>
</body></html>
