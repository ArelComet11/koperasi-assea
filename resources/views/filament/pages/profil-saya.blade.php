<x-filament-panels::page>
    <x-filament::section heading="Data anggota">
        <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:1rem;font-size:.9rem">
            @foreach([
                'Kode anggota' => $anggota->kode_anggota,
                'Nama lengkap' => $anggota->nama_lengkap,
                'Username' => $user->username,
                'Telepon' => $anggota->telepon ?: '-',
                'Email' => $anggota->email ?: '-',
                'Status' => $anggota->status->getLabel(),
                'Bergabung' => $anggota->tanggal_bergabung?->translatedFormat('d F Y') ?: '-',
                'Alamat' => $anggota->alamat ?: '-',
            ] as $label => $nilai)
                <div><dt style="font-size:.75rem;font-weight:600;opacity:.7">{{ $label }}</dt><dd style="font-weight:600">{{ $nilai }}</dd></div>
            @endforeach
        </dl>
        <p style="margin-top:1rem;font-size:.8rem;opacity:.7">Untuk mengubah data profil, silakan hubungi admin koperasi.</p>
    </x-filament::section>
</x-filament-panels::page>
