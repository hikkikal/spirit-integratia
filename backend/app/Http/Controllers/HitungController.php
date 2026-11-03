<?php

namespace App\Http\Controllers;

use App\Support\HitungHonor;
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
        try {
            $h = DB::transaction(fn () => HitungHonor::hitungPeriode(
                fn (string $sql, array $b) => DB::select($sql, $b),
                function (string $sql, array $b) { DB::statement($sql, $b); },
                $p->id_smt,
            ));
        } catch (\DomainException $e) {
            abort($e->getCode(), $e->getMessage());
        }
        $this->catat($request, 'Hitung honor', "periode {$h['periode']}: {$h['dihitung']} dosen");
        return ['data' => ['periode' => $h['periode'], 'dihitung' => $h['dihitung'], 'dilewati' => $h['dilewati'],
                           'bruto' => $h['bruto'], 'tanpaTarif' => $h['tanpa_tarif']]];
    }
}
