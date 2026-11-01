<?php

namespace App\Http\Controllers;

use App\Support\Bentuk;
use App\Support\Kueri;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Halaman Keuangan dan Pimpinan (Pimpinan hanya membaca; verifikasi dibatasi ke peran keuangan di rute). */
class KeuanganController extends Controller
{
    use Konteks;

    public function periode()
    {
        return ['data' => array_column(DB::select(Kueri::PERIODE_SEMUA), 'label')];
    }

    public function dashboard(Request $request)
    {
        $p = $this->periodeDipilih($request);
        $verif = (int) DB::selectOne(Kueri::HONOR_TERVERIFIKASI, ['smt' => $p->id_smt])->n;
        return ['data' => ['periode' => $p->label] + Bentuk::dashboard(DB::select(Kueri::DASHBOARD, ['smt' => $p->id_smt]), $verif)];
    }

    public function laporan(Request $request)
    {
        return $this->dashboard($request);
    }

    public function dataHonor(Request $request)
    {
        $p = $this->periodeDipilih($request);
        $q = addcslashes(trim((string) $request->query('q', '')), "%_\\");
        $jur = trim((string) $request->query('jurusan', ''));
        $rows = DB::select(Kueri::DATA_HONOR, ['smt' => $p->id_smt, 'jur1' => $jur, 'jur2' => $jur, 'q1' => $q, 'q2' => $q]);
        return ['data' => ['periode' => $p->label, 'dosen' => Bentuk::dataHonor($rows)]];
    }

    /** Rincian satu dosen (profil, kartu ringkasan, daftar kelas). */
    public function rincian(Request $request, string $id)
    {
        $p = $this->periodeDipilih($request);
        $profil = Bentuk::profil(DB::selectOne(Kueri::PROFIL, ['id' => $id]));
        abort_if($profil === null, 404, 'Dosen tidak ditemukan.');
        $ringkas = DB::selectOne(Kueri::PERHITUNGAN, ['id' => $id, 'smt' => $p->id_smt]);
        $kelas = Bentuk::kelasPeriode(DB::select(Kueri::KELAS_PERIODE, ['id' => $id, 'smt' => $p->id_smt]));
        return ['data' => [
            'periode' => $p->label, 'profil' => $profil, 'kelas' => $kelas,
            'ringkasan' => ['totalKelas' => count($kelas), 'sksTerlaksana' => Bentuk::angka($ringkas->sks_terlaksana ?? null),
                            'sksWajib' => Bentuk::angka($ringkas->sks_wajib ?? null), 'sksLebih' => Bentuk::angka($ringkas->sks_lebih ?? null),
                            'status' => Bentuk::status($ringkas->status_verifikasi ?? null)],
            'mingguan' => [],
        ]];
    }

    public function detail(Request $request, string $id)
    {
        $p = $this->periodeDipilih($request);
        $d = Bentuk::detailHonor(
            DB::selectOne(Kueri::PERHITUNGAN, ['id' => $id, 'smt' => $p->id_smt]),
            DB::select(Kueri::DETAIL_KELAS, ['id' => $id, 'smt' => $p->id_smt]),
            DB::select(Kueri::TARIF_AKTIF),
        );
        abort_if($d === null, 404, 'Belum ada perhitungan untuk periode ini.');
        return ['data' => ['periode' => $p->label] + $d];
    }

    public function verifikasi(Request $request, string $id)
    {
        $p = $this->periodeDipilih($request);
        $baris = DB::transaction(function () use ($request, $id, $p) {
            $n = DB::update(Kueri::VERIFIKASI, ['pengguna' => $request->user()->id_user, 'id' => $id, 'smt' => $p->id_smt]);
            if ($n === 1) {
                DB::insert(Kueri::LOG, ['pelaku' => $request->user()->id_user, 'aksi' => 'Verifikasi honor', 'objek' => "dosen $id, periode {$p->label}"]);
            }
            return $n;
        });
        abort_if($baris === 0, 409, 'Honor ini tidak dapat diverifikasi (sudah terverifikasi, terkunci, atau belum dihitung).');
        return ['data' => ['status' => 'terverifikasi']];
    }

    public function tarif()
    {
        return ['data' => Bentuk::tarif(DB::select(Kueri::TARIF_AKTIF), DB::select(Kueri::PAJAK), DB::select(Kueri::ATURAN))];
    }
}
