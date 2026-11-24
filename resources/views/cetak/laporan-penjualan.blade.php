<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $laporan['judul'] }}</title>
<style>
 @page{size:A4 landscape;margin:8mm}
 body{font-family:Calibri,Arial,Helvetica,sans-serif;color:#000;margin:16px;font-size:10px}
 .aksi{margin-bottom:10px}
 .bulan-blok{margin-bottom:24px}
 .bulan-blok + .bulan-blok{page-break-before:always}
 .kop-judul{text-align:center;font-weight:700;font-size:14px;line-height:1.35}
 .kop-sub{text-align:center;font-weight:700;font-size:12px;margin-bottom:10px}
 .meta-grid{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;font-weight:700;font-size:10.5px}
 .meta-grid table{border-collapse:collapse}
 .meta-grid td{border:0;padding:1px 8px 1px 0;text-align:left}
 .produk{text-align:right;font-weight:700;font-size:10.5px;margin-bottom:4px}
 table.matriks{width:100%;border-collapse:collapse}
 table.matriks th,table.matriks td{border:1px solid #000;padding:3px 4px;font-size:9px;white-space:nowrap}
 table.matriks th{background:#1CE836;color:#000;font-weight:700;text-align:center}
 table.matriks thead tr:first-child th{border-top:2px solid #000}
 table.matriks thead tr:last-child th{border-bottom:2px solid #000}
 table.matriks td.num{text-align:right}
 table.matriks td.ctr{text-align:center}
 table.matriks tr.total-row td{font-weight:700;background:#E2EFDA;border-top:2px solid #000;border-bottom:2px solid #000}
 .ttd-wrap{display:flex;justify-content:flex-end;margin-top:18px}
 .ttd{text-align:center;min-width:260px;font-size:10.5px;line-height:1.45}
 .ttd .org{font-weight:700}
 .ttd .nama{margin-top:44px;font-weight:700;text-decoration:underline}
 @media print{.aksi{display:none} body{margin:0} table.matriks th,table.matriks tr.total-row td{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head>
<body>
@php
    $peng = app(\App\Services\PengaturanService::class);
    $noSpbun = (string) ($peng->get('koperasi.no_spbun', '18.208.032') ?: '18.208.032');
    $alamat = (string) ($peng->get('koperasi.alamat') ?: 'Jl. Babalan Gg. Tenggiri No. 45 Pangkalan Brandan');
    $namaKetua = mb_strtoupper((string) ($peng->get('koperasi.nama_ketua', 'SYAFRIYANI') ?: 'SYAFRIYANI'));
    $fmt = fn ($n) => fmod((float) $n, 1.0) == 0.0 ? number_format((float) $n, 0, ',', '.') : number_format((float) $n, 2, ',', '.');
@endphp
<div class="aksi"><button onclick="window.print()">Cetak / Simpan PDF</button></div>

@foreach($laporan['bulanan'] ?? [] as $b)
<div class="bulan-blok">
    <div class="kop-judul">REKAPITULASI LAPORAN PENJUALAN KE SEKTOR PERIKANAN</div>
    <div class="kop-sub">SPBU-N KOPERASI ASSEA LORGAN MANDIRI</div>

    <div class="meta-grid">
        <table>
            <tr><td>No. SPBU Kompak</td><td>: {{ $noSpbun }}</td></tr>
            <tr><td>Alamat</td><td>: {{ $alamat }}</td></tr>
        </table>
        <table>
            <tr><td>Bulan</td><td>: {{ $b['bulan_nama'] }}</td></tr>
            <tr><td>Tahun</td><td>: {{ $b['tahun'] }}</td></tr>
        </table>
    </div>
    <div class="produk">M.SOLAR</div>

    <table class="matriks">
        <thead>
            <tr>
                <th rowspan="2">No</th>
                <th rowspan="2">Nama</th>
                <th rowspan="2">Nama Kapal</th>
                <th rowspan="2">No. Rekomendasi</th>
                <th colspan="31">Tanggal Penjualan</th>
                <th rowspan="2">Total</th>
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
                @for($d = 1; $d <= 31; $d++)
                    @php $v = $row['harian'][$d] ?? null; @endphp
                    <td class="num">{{ ($v !== null && (float) $v > 0) ? $fmt($v) : '' }}</td>
                @endfor
                <td class="num">{{ $fmt($row['total']) }}</td>
            </tr>
        @empty
            <tr><td colspan="36" class="ctr">Tidak ada data penjualan pada bulan ini.</td></tr>
        @endforelse
            <tr class="total-row">
                <td colspan="4" class="ctr">JUMLAH</td>
                @for($d = 1; $d <= 31; $d++)
                    <td class="num">{{ $fmt($b['total_harian'][$d] ?? 0) }}</td>
                @endfor
                <td class="num">{{ $fmt($b['total_penjualan']) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="ttd-wrap">
        <div class="ttd">
            <div>Tanggal, {{ $b['tanggal_tanda_tangan'] }}</div>
            <div>Hormat kami,</div>
            <div class="org">SPBU-N KOPERASI ASSEA LORGAN MANDIRI</div>
            <div class="nama">{{ $namaKetua }}</div>
            <div>Ketua</div>
        </div>
    </div>
</div>
@endforeach
</body></html>
