<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DosenController;
use App\Http\Controllers\KeuanganController;
use Illuminate\Support\Facades\Route;

// Dikenal sebagai /api/... (awalan api ditambahkan Laravel).
Route::post('/dev-login', [AuthController::class, 'devLogin'])->middleware('throttle:30,1');   // hanya APP_ENV=local

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/role', [AuthController::class, 'ganti']);

    Route::get('/periode', [KeuanganController::class, 'periode']);
    Route::get('/tarif', [KeuanganController::class, 'tarif']);

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

    Route::post('/data-honor/{id}/verifikasi', [KeuanganController::class, 'verifikasi'])
        ->whereUuid('id')->middleware('peran:keuangan');
});
