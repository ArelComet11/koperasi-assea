<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use App\Models\SuratRekomendasi;
use Illuminate\Support\Facades\Storage;

class DokumenController extends Controller
{
    /** File surat disimpan di disk privat. Route binding memakai global scope, sehingga anggota hanya bisa membuka suratnya sendiri. */
    public function surat(SuratRekomendasi $surat)
    {
        $user = auth()->user();
        abort_unless($user->can('surat.view') || $user->hasRole('anggota'), 403);
        abort_unless($surat->file_path && Storage::disk('local')->exists($surat->file_path), 404);

        return Storage::disk('local')->response($surat->file_path, null, [
            'Content-Disposition' => 'inline; filename="surat-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $surat->nomor_surat) . '.' . pathinfo($surat->file_path, PATHINFO_EXTENSION) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function buktiPembayaran(Pembayaran $pembayaran)
    {
        $user = auth()->user();
        abort_unless($user->can('tagihan.view') || $user->hasRole('anggota'), 403);

        $pembayaran->load(['tagihan.pengajuan.kapal', 'tagihan.anggota', 'penerima']);

        return view('cetak.bukti', ['bayar' => $pembayaran]);
    }
}
