<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display:flex;align-items:center;gap:1rem">
            <x-logo :size="56" />
            <div>
                <div style="font-size:1.25rem;font-weight:800;color:#075B37">Selamat datang, {{ $nama }}</div>
                <div style="font-size:.85rem;opacity:.75">Kode anggota {{ $kode }} · {{ now()->translatedFormat('l, d F Y') }}</div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
