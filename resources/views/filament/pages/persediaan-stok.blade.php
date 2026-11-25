<x-filament-panels::page>
    <x-filament::section>
        <div style="font-size:.9rem;line-height:1.6">
            <p><strong>Stok fisik = penerimaan − penyaluran.</strong> Stok fisik bertambah saat penerimaan BBM dicatat dan berkurang saat penyaluran BBM aktual dicatat.</p>
            <p style="margin-top:.5rem">Setiap perubahan tercatat pada
                <a href="{{ $urlLedger }}" style="color:#078B4B;font-weight:700;text-decoration:underline">Riwayat Pergerakan Stok</a>.
                Pemeriksaan konsistensi saldo terhadap ledger:
                @if($konsisten)
                    <strong style="color:#078B4B">konsisten ✓</strong>
                @else
                    <strong style="color:#D71920">TIDAK konsisten — jalankan <code>php artisan stok:verifikasi</code> dan periksa ledger.</strong>
                @endif
            </p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
