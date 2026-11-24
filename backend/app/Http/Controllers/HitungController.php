<?php

namespace App\Http\Controllers;

use App\Support\HitungHonor;
use App\Support\HitungNonPengajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Hitung ulang honor satu periode (peran keuangan). Perhitungan terverifikasi/terkunci dan periode terkunci tidak disentuh. */
class HitungController extends Controller
{
    use Catat;
    use Konteks;

    public function hitung(Request $request)
    {
        $p = $this->periodeDipilih($request);
        $baca = fn (string $sql, array $b) => DB::select($sql, $b);
        $tulis = function (string $sql, array $b) { DB::statement($sql, $b); };
        try {
            [$h, $np] = DB::transaction(fn () => [
                HitungHonor::hitungPeriode($baca, $tulis, $p->id_smt),
                HitungNonPengajaran::hitungPeriode($baca, $tulis, $p->id_smt),
            ]);
        } catch (\DomainException $e) {
            abort($e->getCode(), $e->getMessage());
        }
        $this->catat($request, 'Hitung honor', "periode {$h['periode']}: {$h['dihitung']} dosen, {$np['dihitung']} kegiatan non-pengajaran");
        return ['data' => ['periode' => $h['periode'], 'dihitung' => $h['dihitung'], 'dilewati' => $h['dilewati'],
                           'bruto' => $h['bruto'], 'tanpaTarif' => $h['tanpa_tarif'], 'perluDitentukan' => $h['perlu_ditentukan'],
                           'nonPengajaran' => ['dihitung' => $np['dihitung'], 'bruto' => $np['bruto'], 'tanpaTarif' => $np['tanpa_tarif'], 'tidakDibayar' => $np['tidak_dibayar'], 'dilewati' => $np['dilewati']]]];
    }
}
