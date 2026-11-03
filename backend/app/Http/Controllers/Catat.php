<?php

namespace App\Http\Controllers;

use App\Support\Kueri;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Mencatat aktivitas ke log_aktivitas (pelaku dan peran aktif diambil dari sesi). */
trait Catat
{
    protected function catat(Request $request, string $aksi, string $objek, string $status = 'berhasil'): void
    {
        DB::insert(Kueri::LOG, ['pelaku' => $request->user()->id_user, 'aksi' => $aksi, 'objek' => $objek,
            'peran' => $request->session()->get('peran_aktif'), 'status' => $status]);
    }
}
