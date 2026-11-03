<?php

namespace App\Http\Middleware;

use App\Support\Bentuk;
use Closure;
use Illuminate\Http\Request;

/** Pemakaian: ->middleware('peran:keuangan,pimpinan'). Peran aktif disimpan di sesi dan divalidasi server. */
class PeranAktif
{
    public function handle(Request $request, Closure $next, string ...$peran)
    {
        $aktif = $request->session()->get('peran_aktif');
        if (! $request->user() || ! in_array($aktif, $peran, true)) {
            abort(403, 'Peran Anda tidak boleh mengakses halaman ini.');
        }
        abort_unless($request->user()->status_aktif, 403, 'Akun Anda dinonaktifkan.');
        return $next($request);
    }
}
