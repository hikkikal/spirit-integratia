<?php

use App\Http\Controllers\AdminInputController;
use App\Http\Controllers\AdminSuperController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DokumenReferensiController;
use App\Http\Controllers\DosenController;
use App\Http\Controllers\HitungController;
use App\Http\Controllers\KeuanganController;
use Illuminate\Support\Facades\Route;

// Dikenal sebagai /api/... (awalan api ditambahkan Laravel).
Route::post('/auth/sso/validate', [AuthController::class, 'ssoValidate'])->middleware('throttle:20,1');
Route::post('/auth/login', [AuthController::class, 'loginLokal'])->middleware('throttle:30,1');   // akun lokal; matikan dengan LOGIN_LOKAL=false
Route::post('/dev-login', [AuthController::class, 'devLogin'])->middleware('throttle:30,1');   // hanya APP_ENV=local

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/role', [AuthController::class, 'ganti']);

    Route::get('/periode', [KeuanganController::class, 'periode']);
    Route::get('/tarif', [KeuanganController::class, 'tarif']);
    Route::get('/dokumen-referensi', [DokumenReferensiController::class, 'daftar']);
    Route::get('/dokumen-referensi/{id}/berkas', [DokumenReferensiController::class, 'berkas'])->whereNumber('id');

    Route::middleware('peran:dosen')->prefix('dosen')->group(function () {
        Route::get('/profil', [DosenController::class, 'profil']);
        Route::get('/riwayat', [DosenController::class, 'riwayat']);
        Route::get('/kelas', [DosenController::class, 'kelas']);
        Route::get('/honor', [DosenController::class, 'honor']);
    });

    Route::middleware('peran:keuangan,pimpinan')->group(function () {
        Route::get('/dashboard', [KeuanganController::class, 'dashboard']);
        Route::get('/laporan', [KeuanganController::class, 'laporan']);
        Route::get('/data-honor', [KeuanganController::class, 'dataHonor']);
        Route::get('/data-honor/{id}', [KeuanganController::class, 'rincian'])->whereUuid('id');
        Route::get('/data-honor/{id}/detail', [KeuanganController::class, 'detail'])->whereUuid('id');
    });

    Route::post('/hitung-honor', [HitungController::class, 'hitung'])->middleware(['peran:keuangan', 'throttle:6,1']);
    Route::post('/data-honor/{id}/verifikasi', [KeuanganController::class, 'verifikasi'])
        ->whereUuid('id')->middleware('peran:keuangan');

    Route::middleware('peran:admin')->prefix('admin')->group(function () {
        Route::get('/sinkron/koneksi', [AdminInputController::class, 'koneksi']);
        Route::get('/sinkron', [AdminInputController::class, 'riwayat']);
        Route::post('/sinkron', [AdminInputController::class, 'mulai'])->middleware('throttle:10,1');
        Route::get('/sinkron/{id}/status', [AdminInputController::class, 'status'])->whereUuid('id');
        Route::get('/sinkron/{id}', [AdminInputController::class, 'log'])->whereUuid('id');

        Route::get('/tarif', [AdminInputController::class, 'tarif']);
        Route::post('/tarif', [AdminInputController::class, 'tarifTambah']);
        Route::put('/tarif/{id}', [AdminInputController::class, 'tarifUbah'])->whereNumber('id');

        Route::get('/perdir', [AdminInputController::class, 'perdir']);
        Route::post('/perdir', [AdminInputController::class, 'perdirTambah']);
        Route::patch('/perdir/{id}', [AdminInputController::class, 'perdirStatus'])->whereNumber('id');
        Route::delete('/perdir/{id}', [AdminInputController::class, 'perdirHapus'])->whereNumber('id');
    });

    Route::middleware('peran:admin_super')->prefix('admin-super')->group(function () {
        Route::get('/pengguna', [AdminSuperController::class, 'pengguna']);
        Route::put('/pengguna/{username}/role', [AdminSuperController::class, 'ubahRole'])->where('username', '[A-Za-z0-9._@-]+');
        Route::patch('/pengguna/{username}', [AdminSuperController::class, 'ubahStatus'])->where('username', '[A-Za-z0-9._@-]+');
        Route::get('/log', [AdminSuperController::class, 'log']);
    });
});
