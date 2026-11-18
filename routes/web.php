<?php

use App\Http\Controllers\DokumenController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::controller(PublicController::class)->group(function () {
    Route::get('/', 'beranda')->name('beranda');
    Route::get('/profil', 'profil')->name('profil');
    Route::get('/layanan', 'layanan')->name('layanan');
    Route::get('/berita', 'beritaIndex')->name('berita.index');
    Route::get('/berita/{slug}', 'beritaShow')->name('berita.show');
    Route::get('/galeri', 'galeri')->name('galeri');
    Route::get('/kontak', 'kontak')->name('kontak');
});

// Tidak ada registrasi publik. "login" diperlukan oleh middleware auth bawaan Laravel.
Route::redirect('/login-anggota', '/anggota/login');
Route::redirect('/login', '/anggota/login')->name('login');

Route::middleware('auth')->group(function () {
    Route::get('/dokumen/surat/{surat}', [DokumenController::class, 'surat'])->name('dokumen.surat');
    Route::get('/cetak/pembayaran/{pembayaran}', [DokumenController::class, 'buktiPembayaran'])->name('cetak.pembayaran');
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('/laporan/export', [LaporanController::class, 'export'])->name('laporan.export');
        Route::get('/laporan/cetak', [LaporanController::class, 'cetak'])->name('laporan.cetak');
    });
});
