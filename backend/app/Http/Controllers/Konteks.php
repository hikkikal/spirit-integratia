<?php

namespace App\Http\Controllers;

use App\Support\Kueri;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Bantuan bersama: menentukan periode dari parameter ?periode=<label>. */
trait Konteks
{
    protected function periodeDipilih(Request $request): object
    {
        $label = trim((string) $request->query('periode', ''));
        $p = $label !== '' ? DB::selectOne(Kueri::PERIODE_BY_LABEL, ['label' => $label]) : DB::selectOne(Kueri::PERIODE_TERBARU_BERDATA);
        abort_if($p === null, 404, 'Periode tidak ditemukan.');
        return $p;
    }
}
