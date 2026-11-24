<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $laporan['judul'] }}</title>
<style>
 @page{size:A4 landscape;margin:8mm}
 body{font-family:Calibri,Arial,Helvetica,sans-serif;color:#000;margin:16px;font-size:10px}
 .aksi{margin-bottom:10px}
 .bulan-blok{margin-bottom:24px}
 .bulan-blok + .bulan-blok{page-break-before:always}
 .kop-meta{border-collapse:collapse;margin-bottom:8px;width:auto}
 .kop-meta td{border:0;padding:1px 10px 1px 0;font-weight:700;font-size:11px;text-align:left}
 .sub-row{display:flex;gap:48px;font-weight:700;font-size:11px;margin:8px 0 4px}
 table.matriks{width:100%;border-collapse:collapse}
 table.matriks th,table.matriks td{border:1px solid #000;padding:3px 4px;font-size:9px;white-space:nowrap}
 table.matriks th{background:#1CE836;color:#000;font-weight:700;text-align:center}
 table.matriks thead tr:first-child th{border-top:2px solid #000}
 table.matriks thead tr:last-child th{border-bottom:2px solid #000}
 table.matriks td.num{text-align:right}
 table.matriks td.ctr{text-align:center}
 table.matriks tr.total-row td{font-weight:700;background:#E2EFDA;border-top:2px solid #000;border-bottom:2px solid #000}
 @media print{.aksi{display:none} body{margin:0} table.matriks th,table.matriks tr.total-row td{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head>
<body>
@php
    $peng = app(\App\Services\PengaturanService::class);
    $rawNoSpbun = (string) ($peng->get('koperasi.no_spbun', '18.208.032') ?: '18.208.032');
    $noLp = preg_replace('/\D+/', '', $rawNoSpbun) ?: '18208032';
    $alamat = (string) ($peng->get('koperasi.alamat') ?: 'Jl. Babalan Gg. Tenggiri No. 45');
    $fmt = fn ($n) => fmod((float) $n, 1.0) == 0.0 ? number_format((float) $n, 0, ',', '.') : number_format((float) $n, 2, ',', '.');
@endphp
<div class="aksi"><button onclick="window.print()">Cetak / Simpan PDF</button></div>

@foreach($laporan['bulanan'] ?? [] as $b)
<div class="bulan-blok">
    <table class="kop-meta">
        <tr><td>NO LP</td><td>{{ $noLp }}</td></tr>
        <tr><td>NAMA</td><td>KSU ASSEA LORGAN MANDIRI</td></tr>
        <tr><td>ALAMAT</td><td>{{ $alamat }}</td></tr>
        <tr><td colspan="2">REALISASI</td></tr>
    </table>

    <div class="sub-row">
        <span>{{ $b['bulan_label'] }}</span>
        <span>Penjualan</span>
    </div>

    <table class="matriks">
        <thead>
            <tr>
                <th rowspan="2">NO</th>
                <th rowspan="2">Nama</th>
                <th rowspan="2">Nama Kapal</th>
                <th rowspan="2">No Rekom</th>
                <th rowspan="2">Vol Rekom</th>
                <th colspan="31">Penyaluran</th>
                <th rowspan="2">Total</th>
                <th rowspan="2">Total Vs Rekom</th>
            </tr>
            <tr>
                @for($d = 1; $d <= 31; $d++)<th>{{ $d }}</th>@endfor
            </tr>
        </thead>
        <tbody>
        @forelse($b['rows'] as $i => $row)
            <tr>
                <td class="ctr">{{ $i + 1 }}</td>
                <td>{{ $row['nama'] }}</td>
                <td>{{ $row['nama_kapal'] }}</td>
                <td>{{ $row['no_rekom'] }}</td>
                <td class="num">{{ $fmt($row['vol_rekom']) }}</td>
                @for($d = 1; $d <= 31; $d++)
                    @php $v = $row['harian'][$d] ?? null; @endphp
                    <td class="num">{{ ($v !== null && (float) $v > 0) ? $fmt($v) : '' }}</td>
                @endfor
                <td class="num">{{ $fmt($row['total']) }}</td>
                <td class="num">{{ $fmt($row['total_vs_rekom']) }}</td>
            </tr>
        @empty
            <tr><td colspan="38" class="ctr">Tidak ada data surat rekomendasi pada periode ini.</td></tr>
        @endforelse
            <tr class="total-row">
                <td colspan="4" class="ctr">Total</td>
                <td class="num">{{ $fmt($b['total_vol_rekom']) }}</td>
                @for($d = 1; $d <= 31; $d++)
                    <td class="num">{{ $fmt($b['total_harian'][$d] ?? 0) }}</td>
                @endfor
                <td class="num">{{ $fmt($b['total_penyaluran']) }}</td>
                <td class="num">{{ $fmt($b['total_vs_rekom']) }}</td>
            </tr>
        </tbody>
    </table>
</div>
@endforeach
</body></html>
