<x-filament-panels::page>
    <style>
        .lp-form{display:flex;gap:.75rem;flex-wrap:wrap;align-items:end;margin-bottom:1rem}
        .lp-form label{display:block;font-size:.75rem;font-weight:600;margin-bottom:.2rem;opacity:.8}
        .lp-in{border:1px solid rgba(128,128,128,.4);border-radius:.5rem;padding:.45rem .65rem;background:transparent;color:inherit;font-size:.875rem;min-width:9rem;color-scheme:light dark}
        .dark .lp-in,.dark .lp-in option{background-color:#18181b;color:#f4f4f5}
        .lp-btn{background:#078B4B;color:#fff;border:0;border-radius:.5rem;padding:.5rem 1rem;font-weight:600;font-size:.875rem;cursor:pointer;text-decoration:none;display:inline-block}
        .lp-btn.alt{background:transparent;color:#078B4B;border:1px solid #078B4B}
        .lp-wrap{overflow-x:auto;border:1px solid rgba(128,128,128,.25);border-radius:.75rem;margin-top:.75rem}
        .lp-t{width:100%;border-collapse:collapse;font-size:.82rem}
        .lp-t th,.lp-t td{padding:.5rem .7rem;text-align:left;border-bottom:1px solid rgba(128,128,128,.18);white-space:nowrap}
        .lp-t th{font-weight:700;background:rgba(7,139,75,.08)}
        .lp-sum{display:flex;gap:1.25rem;flex-wrap:wrap;margin-top:.5rem;font-size:.85rem}
    </style>

    <form method="GET" class="lp-form">
        <div><label for="jenis">Jenis laporan</label>
            <select class="lp-in" id="jenis" name="jenis" onchange="if(this.form.status) this.form.status.value=''; this.form.submit()">
                @foreach($jenisBoleh as $k => $v)<option value="{{ $k }}" @selected($jenis === $k)>{{ $v }}</option>@endforeach
            </select></div>
        <div><label for="dari">Dari tanggal</label><input class="lp-in" type="date" id="dari" name="dari" value="{{ $filter['dari'] ?? '' }}"></div>
        <div><label for="sampai">Sampai tanggal</label><input class="lp-in" type="date" id="sampai" name="sampai" value="{{ $filter['sampai'] ?? '' }}"></div>
        @if($tampilAnggota)
        <div><label for="anggota_id">Anggota</label>
            <select class="lp-in" id="anggota_id" name="anggota_id"><option value="">Semua</option>
                @foreach($anggotaOpsi as $id => $nama)<option value="{{ $id }}" @selected(($filter['anggota_id'] ?? '') == $id)>{{ $nama }}</option>@endforeach
            </select></div>
        @endif
        @if($tampilKapal)
        <div><label for="kapal_id">Kapal</label>
            <select class="lp-in" id="kapal_id" name="kapal_id"><option value="">Semua</option>
                @foreach($kapalOpsi as $id => $nama)<option value="{{ $id }}" @selected(($filter['kapal_id'] ?? '') == $id)>{{ $nama }}</option>@endforeach
            </select></div>
        @endif
        @if($statusOpsi !== [])
        <div><label for="status">Status</label>
            <select class="lp-in" id="status" name="status"><option value="">Semua</option>
                @foreach($statusOpsi as $k => $label)<option value="{{ $k }}" @selected(($filter['status'] ?? '') === $k)>{{ $label }}</option>@endforeach
            </select></div>
        @endif
        <div><label for="cari">Cari</label><input class="lp-in" type="search" id="cari" name="cari" value="{{ $filter['cari'] ?? '' }}"></div>
        <button class="lp-btn" type="submit">Tampilkan</button>
    </form>

    @if($galat)
        <p style="color:#D71920;font-weight:600">{{ $galat }}</p>
    @elseif($errors->any())
        <p style="color:#D71920;font-weight:600">{{ $errors->first() }}</p>
    @elseif($laporan)
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;justify-content:space-between">
            <div><strong>{{ $laporan['judul'] }}</strong><br><span style="font-size:.82rem;opacity:.75">Periode: {{ $laporan['periode'] }}</span></div>
            <div style="display:flex;gap:.5rem">
                <a class="lp-btn" href="{{ $urlExport }}">Export Excel</a>
                <a class="lp-btn alt" href="{{ $urlCetak }}" target="_blank" rel="noopener">Cetak / PDF</a>
            </div>
        </div>

        @if($laporan['ringkasan'])
            <div class="lp-sum">@foreach($laporan['ringkasan'] as $k => $v)<span>{{ $k }}: <strong>{{ is_float($v) ? number_format($v, 2, ',', '.') : $v }}</strong></span>@endforeach</div>
        @endif

        @if(($laporan['jenis'] ?? null) === 'surat' && ! empty($laporan['bulanan']))
            @php
                $fmtAngka = fn ($n) => fmod((float) $n, 1.0) == 0.0 ? number_format((float) $n, 0, ',', '.') : number_format((float) $n, 2, ',', '.');
            @endphp
            @foreach($laporan['bulanan'] as $b)
                <div style="margin-top:1rem;font-weight:700;font-size:.9rem">{{ $b['bulan_label'] }} — Penjualan / Realisasi Penyaluran</div>
                <div class="lp-wrap">
                    <table class="lp-t">
                        <thead>
                            <tr>
                                <th rowspan="2">NO</th>
                                <th rowspan="2">Nama</th>
                                <th rowspan="2">Nama Kapal</th>
                                <th rowspan="2">No Rekom</th>
                                <th rowspan="2">Vol Rekom</th>
                                <th colspan="31" style="text-align:center">Penyaluran</th>
                                <th rowspan="2">Total</th>
                                <th rowspan="2">Total Vs Rekom</th>
                            </tr>
                            <tr>
                                @for($d = 1; $d <= 31; $d++)<th style="text-align:center">{{ $d }}</th>@endfor
                            </tr>
                        </thead>
                        <tbody>
                        @forelse(array_slice($b['rows'], 0, 300) as $idx => $row)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td>{{ $row['nama'] }}</td>
                                <td>{{ $row['nama_kapal'] }}</td>
                                <td>{{ $row['no_rekom'] }}</td>
                                <td style="text-align:right">{{ $fmtAngka($row['vol_rekom']) }}</td>
                                @for($d = 1; $d <= 31; $d++)
                                    @php $v = $row['harian'][$d] ?? null; @endphp
                                    <td style="text-align:right">{{ ($v !== null && (float) $v > 0) ? $fmtAngka($v) : '' }}</td>
                                @endfor
                                <td style="text-align:right;font-weight:600">{{ $fmtAngka($row['total']) }}</td>
                                <td style="text-align:right;font-weight:600">{{ $fmtAngka($row['total_vs_rekom']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="38" style="text-align:center;padding:1.5rem;opacity:.7">Tidak ada data pada filter ini.</td></tr>
                        @endforelse
                        @if($b['rows'] !== [])
                            <tr style="font-weight:700;background:rgba(7,139,75,.08)">
                                <td colspan="4" style="text-align:center">Total</td>
                                <td style="text-align:right">{{ $fmtAngka($b['total_vol_rekom']) }}</td>
                                @for($d = 1; $d <= 31; $d++)
                                    <td style="text-align:right">{{ $fmtAngka($b['total_harian'][$d] ?? 0) }}</td>
                                @endfor
                                <td style="text-align:right">{{ $fmtAngka($b['total_penyaluran']) }}</td>
                                <td style="text-align:right">{{ $fmtAngka($b['total_vs_rekom']) }}</td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>
                @if(count($b['rows']) > 300)<p style="font-size:.8rem;margin-top:.5rem;opacity:.75">Pratinjau menampilkan 300 baris pertama dari {{ count($b['rows']) }} pada {{ $b['bulan_label'] }}. Export Excel memuat seluruh data.</p>@endif
            @endforeach
        @elseif(($laporan['jenis'] ?? null) === 'penjualan' && ! empty($laporan['bulanan']))
            @php
                $fmtAngka = fn ($n) => fmod((float) $n, 1.0) == 0.0 ? number_format((float) $n, 0, ',', '.') : number_format((float) $n, 2, ',', '.');
            @endphp
            @foreach($laporan['bulanan'] as $b)
                <div style="margin-top:1rem;font-weight:700;font-size:.9rem">Rekapitulasi Penjualan — {{ $b['bulan_nama'] }} {{ $b['tahun'] }} (M.SOLAR)</div>
                <div class="lp-wrap">
                    <table class="lp-t">
                        <thead>
                            <tr>
                                <th rowspan="2">No</th>
                                <th rowspan="2">Nama</th>
                                <th rowspan="2">Nama Kapal</th>
                                <th rowspan="2">No. Rekomendasi</th>
                                <th colspan="31" style="text-align:center">Tanggal Penjualan</th>
                                <th rowspan="2">Total</th>
                            </tr>
                            <tr>
                                @for($d = 1; $d <= 31; $d++)<th style="text-align:center">{{ $d }}</th>@endfor
                            </tr>
                        </thead>
                        <tbody>
                        @forelse(array_slice($b['rows'], 0, 300) as $idx => $row)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td>{{ $row['nama'] }}</td>
                                <td>{{ $row['nama_kapal'] }}</td>
                                <td>{{ $row['no_rekom'] }}</td>
                                @for($d = 1; $d <= 31; $d++)
                                    @php $v = $row['harian'][$d] ?? null; @endphp
                                    <td style="text-align:right">{{ ($v !== null && (float) $v > 0) ? $fmtAngka($v) : '' }}</td>
                                @endfor
                                <td style="text-align:right;font-weight:600">{{ $fmtAngka($row['total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="36" style="text-align:center;padding:1.5rem;opacity:.7">Tidak ada data penjualan pada bulan ini.</td></tr>
                        @endforelse
                        @if($b['rows'] !== [])
                            <tr style="font-weight:700;background:rgba(7,139,75,.08)">
                                <td colspan="4" style="text-align:center">JUMLAH</td>
                                @for($d = 1; $d <= 31; $d++)
                                    <td style="text-align:right">{{ $fmtAngka($b['total_harian'][$d] ?? 0) }}</td>
                                @endfor
                                <td style="text-align:right">{{ $fmtAngka($b['total_penjualan']) }}</td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>
                @if(count($b['rows']) > 300)<p style="font-size:.8rem;margin-top:.5rem;opacity:.75">Pratinjau menampilkan 300 baris pertama dari {{ count($b['rows']) }} pada {{ $b['bulan_nama'] }} {{ $b['tahun'] }}. Export Excel memuat seluruh data.</p>@endif
            @endforeach
        @else
        <div class="lp-wrap">
            <table class="lp-t">
                <thead><tr>@foreach($laporan['headings'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse(array_slice($laporan['rows'], 0, 300) as $row)
                    <tr>@foreach($row as $c)<td>{{ is_float($c) ? number_format($c, 2, ',', '.') : $c }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($laporan['headings']) }}" style="text-align:center;padding:1.5rem;opacity:.7">Tidak ada data pada filter ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if(count($laporan['rows']) > 300)<p style="font-size:.8rem;margin-top:.5rem;opacity:.75">Pratinjau menampilkan 300 baris pertama dari {{ count($laporan['rows']) }}. Export Excel memuat seluruh data.</p>@endif
        @endif
    @endif
</x-filament-panels::page>
