<x-filament-panels::page>
    <style>
        .lp-form{display:flex;gap:.75rem;flex-wrap:wrap;align-items:end;margin-bottom:1rem}
        .lp-form label{display:block;font-size:.75rem;font-weight:600;margin-bottom:.2rem;opacity:.8}
        .lp-in{border:1px solid rgba(128,128,128,.4);border-radius:.5rem;padding:.45rem .65rem;background:transparent;color:inherit;font-size:.875rem;min-width:9rem}
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
            <select class="lp-in" id="jenis" name="jenis">
                @foreach($jenisBoleh as $k => $v)<option value="{{ $k }}" @selected($jenis === $k)>{{ $v }}</option>@endforeach
            </select></div>
        <div><label for="dari">Dari tanggal</label><input class="lp-in" type="date" id="dari" name="dari" value="{{ $filter['dari'] ?? '' }}"></div>
        <div><label for="sampai">Sampai tanggal</label><input class="lp-in" type="date" id="sampai" name="sampai" value="{{ $filter['sampai'] ?? '' }}"></div>
        @can('laporan.semua')
        <div><label for="anggota_id">Anggota</label>
            <select class="lp-in" id="anggota_id" name="anggota_id"><option value="">Semua</option>
                @foreach($anggotaOpsi as $id => $nama)<option value="{{ $id }}" @selected(($filter['anggota_id'] ?? '') == $id)>{{ $nama }}</option>@endforeach
            </select></div>
        <div><label for="kapal_id">Kapal</label>
            <select class="lp-in" id="kapal_id" name="kapal_id"><option value="">Semua</option>
                @foreach($kapalOpsi as $id => $nama)<option value="{{ $id }}" @selected(($filter['kapal_id'] ?? '') == $id)>{{ $nama }}</option>@endforeach
            </select></div>
        @endcan
        <div><label for="status">Status (jika relevan)</label><input class="lp-in" id="status" name="status" value="{{ $filter['status'] ?? '' }}" placeholder="mis. aktif / selesai"></div>
        <div><label for="cari">Cari</label><input class="lp-in" type="search" id="cari" name="cari" value="{{ $filter['cari'] ?? '' }}"></div>
        <button class="lp-btn" type="submit">Tampilkan</button>
    </form>

    @if($galat)
        <p style="color:#D71920;font-weight:600">{{ $galat }}</p>
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
</x-filament-panels::page>
