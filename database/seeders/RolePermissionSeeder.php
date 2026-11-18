<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $izin = [
            'anggota.view', 'anggota.create', 'anggota.update', 'anggota.nonaktif', 'anggota.reset_password',
            'kapal.view', 'kapal.manage', 'surat.view', 'surat.manage',
            'pengajuan.view', 'pengajuan.create', 'pengajuan.approve', 'pengajuan.reject', 'pengajuan.cancel',
            'penerimaan.view', 'penerimaan.manage', 'pasokan.override',
            'stok.view', 'stok.saldo_awal', 'jadwal.manage', 'penyaluran.manage',
            'tagihan.view', 'pembayaran.create', 'pembayaran.cancel', 'transaksi.view', 'harga.update',
            'laporan.semua', 'laporan.keuangan',
            'konten.manage', 'pengaturan.manage', 'user.manage', 'audit.view',
        ];

        foreach ($izin as $nama) {
            Permission::findOrCreate($nama, 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());

        // Finance tidak menerima approve / ubah data induk / ubah harga secara bawaan.
        Role::findOrCreate('finance', 'web')->syncPermissions([
            'pengajuan.view', 'tagihan.view', 'pembayaran.create', 'transaksi.view', 'laporan.keuangan',
        ]);

        // Akses data milik sendiri dijaga oleh global scope, bukan permission.
        Role::findOrCreate('anggota', 'web')->syncPermissions(['pengajuan.create']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
