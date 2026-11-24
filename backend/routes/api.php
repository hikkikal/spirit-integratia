<?php

use App\Http\Controllers\AdminInputController;
use App\Http\Controllers\AdminSuperController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DokumenReferensiController;
use App\Http\Controllers\DosenController;
use App\Http\Controllers\HitungController;
use App\Http\Controllers\KeuanganController;
use App\Http\Controllers\KoreksiController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\NonPengajaranController;
use App\Http\Controllers\AcademiaController;
use App\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

// Dikenal sebagai /api/... (awalan api ditambahkan Laravel).
Route::post('/auth/sso/mulai', [SsoController::class, 'mulai'])->middleware('throttle:20,1');        // SSO PNJ (OIDC): langkah 1, minta alamat login
Route::post('/auth/sso/callback', [SsoController::class, 'callback'])->middleware('throttle:20,1');  // SSO PNJ (OIDC): langkah 2, tukar code
Route::post('/auth/login', [AuthController::class, 'loginLokal'])->middleware('throttle:30,1');   // akun lokal; matikan dengan LOGIN_LOKAL=false
Route::post('/dev-login', [AuthController::class, 'devLogin'])->middleware('throttle:30,1');   // hanya APP_ENV=local

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/profil', [AuthController::class, 'profil']);
    Route::post('/logout', [SsoController::class, 'logout']);   // juga mengakhiri sesi SSO bila login lewat SSO
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
        Route::get('/non-pengajaran', [NonPengajaranController::class, 'saya']);
        Route::get('/academia', [AcademiaController::class, 'lihatSaya']);
    });

    Route::middleware('peran:keuangan,pimpinan,admin')->group(function () {   // Admin Input: tampilan baca yang sama untuk memeriksa data tarikan
        Route::get('/dashboard', [KeuanganController::class, 'dashboard']);
        Route::get('/laporan', [KeuanganController::class, 'laporan']);
        Route::get('/laporan/data', [LaporanController::class, 'data']);
        Route::get('/data-honor', [KeuanganController::class, 'dataHonor']);
        Route::get('/tarif/daftar', [KeuanganController::class, 'tarifDaftar']);
        Route::get('/tarif/non-pengajaran', [KeuanganController::class, 'tarifNonPengajaran']);
        Route::get('/data-honor/{id}', [KeuanganController::class, 'rincian'])->whereUuid('id');
        Route::get('/data-honor/{id}/academia', [AcademiaController::class, 'lihat'])->whereUuid('id');
        Route::get('/data-honor/{id}/detail', [KeuanganController::class, 'detail'])->whereUuid('id');
        Route::get('/non-pengajaran', [NonPengajaranController::class, 'daftar']);
        Route::get('/non-pengajaran/{id}', [NonPengajaranController::class, 'detail'])->whereUuid('id');
    });

    Route::middleware('peran:keuangan,admin')->group(function () {
        Route::get('/laporan/ekspor', [LaporanController::class, 'ekspor'])->middleware('throttle:20,1');
        Route::get('/laporan/riwayat', [LaporanController::class, 'riwayat']);
    });
    // Koreksi manual jabatan, golongan, tugas tambahan (tiap perubahan wajib beralasan dan masuk log).
    Route::middleware('peran:keuangan,admin')->prefix('dosen-koreksi/{id}')->where(['id' => '[0-9a-fA-F-]{36}'])->group(function () {
        Route::get('/', [KoreksiController::class, 'tampil']);
        Route::put('/jabatan', [KoreksiController::class, 'jabatan']);
        Route::put('/golongan', [KoreksiController::class, 'golongan']);
        Route::post('/tugas', [KoreksiController::class, 'tugasTambah']);
        Route::post('/tugas/{tugas}/kelompok', [KoreksiController::class, 'tugasKelompok'])->whereUuid('tugas');
        Route::post('/tugas/{tugas}/hapus', [KoreksiController::class, 'tugasHapus'])->whereUuid('tugas');
        Route::post('/tugas/{tugas}/pulihkan', [KoreksiController::class, 'tugasPulihkan'])->whereUuid('tugas');
    });
    Route::post('/hitung-honor', [HitungController::class, 'hitung'])->middleware(['peran:keuangan', 'throttle:6,1']);
    Route::post('/non-pengajaran/{id}/verifikasi', [NonPengajaranController::class, 'verifikasi'])
        ->whereUuid('id')->middleware('peran:keuangan');
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
        Route::get('/tarif-np', [AdminInputController::class, 'tarifNp']);
        Route::put('/tarif-np/{kode}', [AdminInputController::class, 'tarifNpUbah'])->where('kode', '[a-z_]+');

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
        Route::get('/log/aksi', [AdminSuperController::class, 'logAksi']);
    });
});
