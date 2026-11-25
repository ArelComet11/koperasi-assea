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
        .lp-tabs{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.9rem;align-items:center}
        .lp-tab{padding:.35rem .7rem;border-radius:.45rem;border:1px solid rgba(128,128,128,.35);font-size:.8rem;text-decoration:none;color:inherit;display:inline-flex;align-items:center;gap:.35rem}
        .lp-tab.active{background:#078B4B;color:#fff;border-color:#078B4B;font-weight:700}
        .lp-note{margin-top:.65rem;padding:.55rem .8rem;border-radius:.5rem;background:rgba(7,139,75,.09);border:1px solid rgba(7,139,75,.25);font-size:.82rem}
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
                $multiBulan = count($laporan['bulanan']) > 1;
                $tampilBulanan = $multiBulan ? [$laporan['bulanan'][$tabBulan ?? 0]] : $laporan['bulanan'];
            @endphp
            @if($multiBulan)
                <div class="lp-note">
                    Rentang <strong>{{ count($laporan['bulanan']) }} bulan</strong>. Pilih tab bulan di bawah untuk pratinjau penuh tiap bulan; tombol <strong>Export Excel</strong> dan <strong>Cetak / PDF</strong> di atas langsung mengekspor <strong>seluruh {{ count($laporan['bulanan']) }} bulan ({{ count($laporan['bulanan']) }} sheet)</strong> sekaligus.
                </div>
                <div class="lp-tabs">
                    @foreach($laporan['bulanan'] as $idxB => $mb)
                        <a class="lp-tab {{ ($tabBulan ?? 0) === $idxB ? 'active' : '' }}" href="{{ url()->current() . '?' . http_build_query(($baseFilterParams ?? []) + ['tab_bulan' => $idxB]) }}">
                            {{ $mb['bulan_label'] }} <span style="opacity:.75">({{ count($mb['rows']) }})</span>
                        </a>
                    @endforeach
                </div>
            @endif
            @foreach($tampilBulanan as $b)
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
                        @forelse($b['rows'] as $idx => $row)
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
            @endforeach
        @elseif(($laporan['jenis'] ?? null) === 'penjualan_harian' && ! empty($laporan['bulanan']))
            @php
                $fmtAngka = fn ($n) => fmod((float) $n, 1.0) == 0.0 ? number_format((float) $n, 0, ',', '.') : number_format((float) $n, 2, ',', '.');
                $fmtGt = fn ($n) => fmod((float) $n, 1.0) == 0.0 ? number_format((float) $n, 0, ',', '.') : number_format((float) $n, 1, ',', '.');
                $multiBulan = count($laporan['bulanan']) > 1;
                $tampilBulanan = $multiBulan ? [$laporan['bulanan'][$tabBulan ?? 0]] : $laporan['bulanan'];
            @endphp
            @if($multiBulan)
                <div class="lp-note">
                    Rentang <strong>{{ count($laporan['bulanan']) }} bulan</strong>. Pilih tab bulan di bawah untuk pratinjau penuh tiap bulan; tombol <strong>Export Excel</strong> dan <strong>Cetak / PDF</strong> di atas langsung mengekspor <strong>seluruh {{ count($laporan['bulanan']) }} bulan ({{ count($laporan['bulanan']) }} sheet)</strong> sekaligus.
                </div>
                <div class="lp-tabs">
                    @foreach($laporan['bulanan'] as $idxB => $mb)
                        <a class="lp-tab {{ ($tabBulan ?? 0) === $idxB ? 'active' : '' }}" href="{{ url()->current() . '?' . http_build_query(($baseFilterParams ?? []) + ['tab_bulan' => $idxB]) }}">
                            {{ $mb['sheet_title'] }} <span style="opacity:.75">({{ $fmtAngka($mb['total_penjualan'] ?? 0) }} L)</span>
                        </a>
                    @endforeach
                </div>
            @endif
            @foreach($tampilBulanan as $b)
                <div style="margin-top:1rem;font-weight:700;font-size:.9rem">Laporan Penjualan Produk Solar/Bio Solar — {{ $b['periode_label'] }}</div>
                <div class="lp-wrap">
                    <table class="lp-t">
                        <thead>
                            <tr>
                                <th rowspan="2">No</th>
                                <th rowspan="2">Tanggal</th>
                                <th colspan="11" style="text-align:center">Keterangan</th>
                            </tr>
                            <tr>
                                <th>Nama Kapal</th>
                                <th>Nama Pemilik</th>
                                <th style="text-align:center">GT</th>
                                <th style="text-align:center">PK</th>
                                <th>Rekomendasi</th>
                                <th style="text-align:right">JML LTR Rekom (L)</th>
                                <th style="text-align:right">Stock Awal (L)</th>
                                <th style="text-align:right">Penerimaan (L)</th>
                                <th style="text-align:right">Penjualan (L)</th>
                                <th style="text-align:right">Stock Akhir (L)</th>
                                <th style="text-align:center">Tujuan</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($b['rows'] as $row)
                            @php $bgLanjutan = ! empty($row['is_lanjutan']) ? 'background:rgba(128,128,128,.16);' : ''; @endphp
                            <tr>
                                <td>{{ $row['no'] }}</td>
                                <td>{{ $row['tanggal_label'] ?? '' }}</td>
                                <td>{{ $row['nama_kapal'] ?? '' }}</td>
                                <td>{{ $row['nama_pemilik'] ?? '' }}</td>
                                <td style="text-align:center">{{ $row['gt'] !== null ? $fmtGt($row['gt']) : '' }}</td>
                                <td style="text-align:center">{{ $row['pk'] !== null ? $fmtGt($row['pk']) : '' }}</td>
                                <td>{{ $row['rekomendasi'] ?? '' }}</td>
                                <td style="text-align:right">{{ $row['vol_rekom'] !== null ? $fmtAngka($row['vol_rekom']) : '' }}</td>
                                <td style="{{ $bgLanjutan }}text-align:right">{{ $row['stock_awal'] !== null ? $fmtAngka($row['stock_awal']) : '' }}</td>
                                <td style="{{ $bgLanjutan }}text-align:right">{{ $row['penerimaan'] !== null ? $fmtAngka($row['penerimaan']) : '' }}</td>
                                <td style="text-align:right">{{ $row['penjualan'] !== null ? $fmtAngka($row['penjualan']) : '' }}</td>
                                <td style="{{ $bgLanjutan }}text-align:right">{{ $row['stock_akhir'] !== null ? $fmtAngka($row['stock_akhir']) : '' }}</td>
                                <td style="text-align:center">{{ $row['tujuan'] ?? '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="13" style="text-align:center;padding:1.5rem;opacity:.7">Tidak ada data penjualan pada bulan ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            @endforeach
        @elseif(($laporan['jenis'] ?? null) === 'penjualan' && ! empty($laporan['bulanan']))
            @php
                $fmtAngka = fn ($n) => fmod((float) $n, 1.0) == 0.0 ? number_format((float) $n, 0, ',', '.') : number_format((float) $n, 2, ',', '.');
                $multiBulan = count($laporan['bulanan']) > 1;
                $tampilBulanan = $multiBulan ? [$laporan['bulanan'][$tabBulan ?? 0]] : $laporan['bulanan'];
            @endphp
            @if($multiBulan)
                <div class="lp-note">
                    Rentang <strong>{{ count($laporan['bulanan']) }} bulan</strong>. Pilih tab bulan di bawah untuk pratinjau penuh tiap bulan; tombol <strong>Export Excel</strong> dan <strong>Cetak / PDF</strong> di atas langsung mengekspor <strong>seluruh {{ count($laporan['bulanan']) }} bulan ({{ count($laporan['bulanan']) }} sheet)</strong> sekaligus.
                </div>
                <div class="lp-tabs">
                    @foreach($laporan['bulanan'] as $idxB => $mb)
                        <a class="lp-tab {{ ($tabBulan ?? 0) === $idxB ? 'active' : '' }}" href="{{ url()->current() . '?' . http_build_query(($baseFilterParams ?? []) + ['tab_bulan' => $idxB]) }}">
                            {{ $mb['bulan_nama'] }} <span style="opacity:.75">({{ count($mb['rows']) }})</span>
                        </a>
                    @endforeach
                </div>
            @endif
            @foreach($tampilBulanan as $b)
                <div style="margin-top:1rem;font-weight:700;font-size:.9rem">Rekap Penjualan Sektor Perikanan — {{ $b['bulan_nama'] }} {{ $b['tahun'] }} (M.SOLAR)</div>
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
                        @forelse($b['rows'] as $idx => $row)
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
            @endforeach
        @elseif(($laporan['jenis'] ?? null) === 'stok_solar' && ! empty($laporan['bulanan']))
            @php
                $fmtAngka = fn ($n) => fmod((float) $n, 1.0) == 0.0 ? number_format((float) $n, 0, ',', '.') : number_format((float) $n, 2, ',', '.');
            @endphp
            @foreach($laporan['bulanan'] as $b)
                <div style="margin-top:1rem;font-weight:700;font-size:.9rem">Laporan Stock Produk Solar/Bio Solar — {{ $b['periode_label'] }}</div>
                <div class="lp-wrap">
                    <table class="lp-t">
                        <thead>
                            <tr>
                                <th style="text-align:center">NO</th>
                                <th style="text-align:center">TANGGAL</th>
                                <th style="text-align:right">STOCK AWAL</th>
                                <th style="text-align:right">PENERIMAAN</th>
                                <th style="text-align:right">PENJUALAN</th>
                                <th style="text-align:right">STOCK AKHIR</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($b['rows'] as $row)
                            <tr>
                                <td style="text-align:center">{{ $row['no'] }}</td>
                                <td style="text-align:center">{{ $row['tanggal_label'] ?? '' }}</td>
                                <td style="text-align:right">{{ $row['stock_awal'] !== null ? $fmtAngka($row['stock_awal']) : '' }}</td>
                                <td style="text-align:right">{{ $row['penerimaan'] !== null ? $fmtAngka($row['penerimaan']) : '' }}</td>
                                <td style="text-align:right">{{ $row['penjualan'] !== null ? $fmtAngka($row['penjualan']) : '' }}</td>
                                <td style="text-align:right">{{ $row['stock_akhir'] !== null ? $fmtAngka($row['stock_akhir']) : '' }}</td>
                            </tr>
                        @endforeach
                            <tr style="font-weight:700;background:rgba(7,139,75,.08)">
                                <td colspan="2" style="text-align:center">TOTAL</td>
                                <td></td>
                                <td style="text-align:right">{{ $fmtAngka($b['total_penerimaan'] ?? 0) }}</td>
                                <td style="text-align:right">{{ $fmtAngka($b['total_penjualan'] ?? 0) }}</td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endforeach
        @elseif(($laporan['jenis'] ?? null) === 'realisasi' && ! empty($laporan['realisasi']))
            @php
                $real = $laporan['realisasi'];
                $fmtAngka = fn ($n) => fmod((float) $n, 1.0) == 0.0 ? number_format((float) $n, 0, ',', '.') : number_format((float) $n, 2, ',', '.');
            @endphp
            <div class="lp-wrap">
                <table class="lp-t">
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th style="text-align:right">Saldo Awal</th>
                            <th style="text-align:right">Penerimaan</th>
                            <th style="text-align:right">Penjualan</th>
                            <th style="text-align:right">Saldo Akhir Administrasi</th>
                            <th style="text-align:right">Saldo Akhir Pemeriksaan Fisik</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($real['bulan'] as $item)
                        <tr>
                            <td>{{ $item['bulan'] }}</td>
                            <td style="text-align:right">{{ $fmtAngka($item['saldo_awal']) }}</td>
                            <td style="text-align:right">{{ $item['penerimaan'] !== null ? $fmtAngka($item['penerimaan']) : '' }}</td>
                            <td style="text-align:right">{{ $item['penjualan'] !== null ? $fmtAngka($item['penjualan']) : '' }}</td>
                            <td style="text-align:right">{{ $fmtAngka($item['saldo_akhir_adm']) }}</td>
                            <td style="text-align:right">{{ $fmtAngka($item['saldo_akhir_fisik']) }}</td>
                        </tr>
                    @endforeach
                        <tr style="font-weight:700;background:rgba(7,139,75,.08)">
                            <td>Total</td>
                            <td style="text-align:right">{{ $fmtAngka($real['total']['saldo_awal'] ?? 0) }}</td>
                            <td style="text-align:right">{{ $fmtAngka($real['total']['penerimaan'] ?? 0) }}</td>
                            <td style="text-align:right">{{ $fmtAngka($real['total']['penjualan'] ?? 0) }}</td>
                            <td style="text-align:right">{{ $fmtAngka($real['total']['saldo_akhir_adm'] ?? 0) }}</td>
                            <td style="text-align:right">{{ $fmtAngka($real['total']['saldo_akhir_fisik'] ?? 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @else
        <div class="lp-wrap">
            <table class="lp-t">
                <thead><tr>@foreach($laporan['headings'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse($laporan['rows'] as $row)
                    <tr>@foreach($row as $c)<td>{{ is_float($c) ? number_format($c, 2, ',', '.') : $c }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($laporan['headings']) }}" style="text-align:center;padding:1.5rem;opacity:.7">Tidak ada data pada filter ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @endif
    @endif
</x-filament-panels::page>
