<x-filament-panels::page>
    <style>
        .kt-form{display:flex;gap:.75rem;flex-wrap:wrap;align-items:end;margin-bottom:1rem}
        .kt-form label{display:block;font-size:.75rem;font-weight:600;margin-bottom:.2rem;opacity:.8}
        .kt-in{border:1px solid rgba(128,128,128,.4);border-radius:.5rem;padding:.45rem .65rem;background:transparent;color:inherit;font-size:.875rem}
        .kt-btn{background:#078B4B;color:#fff;border:0;border-radius:.5rem;padding:.5rem 1rem;font-weight:600;font-size:.875rem;cursor:pointer}
        .kt-wrap{overflow-x:auto;border:1px solid rgba(128,128,128,.25);border-radius:.75rem}
        .kt-t{width:100%;border-collapse:collapse;font-size:.85rem}
        .kt-t th,.kt-t td{padding:.6rem .8rem;text-align:left;border-bottom:1px solid rgba(128,128,128,.18);white-space:nowrap}
        .kt-t th{font-weight:700;background:rgba(7,139,75,.08)}
        .kt-bar{height:.5rem;background:rgba(128,128,128,.25);border-radius:9999px;min-width:90px;overflow:hidden}
        .kt-bar>span{display:block;height:100%;background:#078B4B}
        .kt-bar>span.tinggi{background:#E5B51B}.kt-bar>span.penuh{background:#D71920}
    </style>

    <form method="GET" class="kt-form">
        <div><label for="periode">Periode</label><input class="kt-in" type="month" id="periode" name="periode" value="{{ $periodeInput }}"></div>
        <div><label for="cari">Cari anggota / kapal / nomor surat</label><input class="kt-in" type="search" id="cari" name="cari" value="{{ $cari }}"></div>
        <button class="kt-btn" type="submit">Terapkan</button>
    </form>

    <p style="font-size:.85rem;margin-bottom:.5rem">Periode: <strong>{{ $periodeLabel }}</strong> · Sisa = batas − penggunaan aktual − alokasi aktif. Pengajuan ditolak/dibatalkan tidak dihitung.</p>

    <div class="kt-wrap">
        <table class="kt-t">
            <thead><tr><th>Anggota</th><th>Kapal</th><th>No. surat</th><th>Batas</th><th>Aktual</th><th>Alokasi aktif</th><th>Sisa</th><th>Penggunaan</th></tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td>{{ $r['anggota'] }}</td><td>{{ $r['kapal'] }}</td><td>{{ $r['nomor'] }}</td>
                    <td>{{ $r['batas'] }}</td><td>{{ $r['terpakai'] }}</td><td>{{ $r['alokasi'] }}</td><td><strong>{{ $r['sisa'] }}</strong></td>
                    <td><div class="kt-bar"><span class="{{ $r['persen'] >= 100 ? 'penuh' : ($r['persen'] >= 80 ? 'tinggi' : '') }}" style="width:{{ $r['persen'] }}%"></span></div> {{ $r['persen'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;padding:1.5rem;opacity:.7">Tidak ada surat rekomendasi aktif pada periode ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
