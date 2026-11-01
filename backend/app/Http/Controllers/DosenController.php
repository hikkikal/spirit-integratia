<?php

namespace App\Http\Controllers;

use App\Support\Bentuk;
use App\Support\Kueri;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Halaman Dosen. Data SELALU dari dosen yang sedang login (id dari sesi, bukan dari permintaan). */
class DosenController extends Controller
{
    use Konteks;

    private function idDosen(Request $request): string
    {
        $id = DB::selectOne("SELECT id_dosen FROM users WHERE id_user = :id", ['id' => $request->user()->id_user])->id_dosen;
        abort_if($id === null, 403, 'Akun ini tidak terhubung ke data dosen.');
        return $id;
    }

    public function profil(Request $request)
    {
        return ['data' => Bentuk::profil(DB::selectOne(Kueri::PROFIL, ['id' => $this->idDosen($request)]))];
    }

    public function riwayat(Request $request)
    {
        return ['data' => Bentuk::riwayat(DB::select(Kueri::RIWAYAT, ['id' => $this->idDosen($request)]))];
    }

    public function kelas(Request $request)
    {
        $id = $this->idDosen($request);
        $p = $this->periodeDipilih($request);
        $ringkas = DB::selectOne(Kueri::PERHITUNGAN, ['id' => $id, 'smt' => $p->id_smt]);
        $kelas = Bentuk::kelasPeriode(DB::select(Kueri::KELAS_PERIODE, ['id' => $id, 'smt' => $p->id_smt]));
        return ['data' => [
            'periode' => $p->label, 'kelas' => $kelas,
            'ringkasan' => ['totalKelas' => count($kelas), 'sksTerlaksana' => Bentuk::angka($ringkas->sks_terlaksana ?? null),
                            'sksWajib' => Bentuk::angka($ringkas->sks_wajib ?? null), 'sksLebih' => Bentuk::angka($ringkas->sks_lebih ?? null),
                            'status' => Bentuk::status($ringkas->status_verifikasi ?? null)],
            'mingguan' => [],   // rincian per minggu belum tersedia (butuh kalender akademik)
        ]];
    }

    public function honor(Request $request)
    {
        $id = $this->idDosen($request);
        $p = $this->periodeDipilih($request);
        $d = Bentuk::detailHonor(
            DB::selectOne(Kueri::PERHITUNGAN, ['id' => $id, 'smt' => $p->id_smt]),
            DB::select(Kueri::DETAIL_KELAS, ['id' => $id, 'smt' => $p->id_smt]),
            DB::select(Kueri::TARIF_AKTIF),
        );
        abort_if($d === null, 404, 'Belum ada perhitungan untuk periode ini.');
        return ['data' => ['periode' => $p->label] + $d];
    }
}
