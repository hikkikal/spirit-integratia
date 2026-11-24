<?php

namespace App\Http\Controllers;

use App\Support\Bentuk;
use App\Support\Kueri;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Honor kegiatan non-pengajaran (bimbingan, penguji, magang).
 * Keuangan dan Pimpinan melihat semua dosen; verifikasi hanya Keuangan (dibatasi di rute).
 * Dosen hanya melihat miliknya sendiri (id dari sesi, bukan dari permintaan).
 */
class NonPengajaranController extends Controller
{
    use Catat;
    use Konteks;

    private const JENIS = ['Skripsi', 'Tesis', 'Tugas Akhir', 'Seminar Proposal', 'PKL/Magang', 'Pembimbing Akademik'];

    public function daftar(Request $request)
    {
        $p = $this->periodeDipilih($request);
        $jur = trim((string) $request->query('jurusan', ''));
        $jenis = trim((string) $request->query('jenis', ''));
        abort_if($jenis !== '' && ! in_array($jenis, self::JENIS, true), 422, 'Jenis kegiatan tidak dikenal.');
        $q = addcslashes(trim((string) $request->query('q', '')), "%_\\");
        $rows = DB::select(Kueri::npDaftar(), ['smt' => $p->id_smt, 'jur1' => $jur, 'jur2' => $jur,
            'jn1' => $jenis, 'jn2' => $jenis, 'jn3' => $jenis, 'q1' => $q, 'q2' => $q, 'q3' => $q]);
        return ['data' => ['periode' => $p->label, 'dosen' => Bentuk::npDaftar($rows)]];
    }

    public function detail(Request $request, string $id)
    {
        return ['data' => $this->muatDetail($id, $this->periodeDipilih($request))];
    }

    /** Detail milik dosen yang sedang login. */
    public function saya(Request $request)
    {
        $id = DB::selectOne("SELECT id_dosen FROM users WHERE id_user = :id", ['id' => $request->user()->id_user])->id_dosen;
        abort_if($id === null, 403, 'Akun ini tidak terhubung ke data dosen.');
        return ['data' => $this->muatDetail($id, $this->periodeDipilih($request))];
    }

    public function verifikasi(Request $request, string $id)
    {
        $p = $this->periodeDipilih($request);
        $n = DB::transaction(function () use ($request, $id, $p) {
            $n = DB::affectingStatement(Kueri::NP_VERIFIKASI, ['id1' => $id, 'id2' => $id, 'smt1' => $p->id_smt, 'smt2' => $p->id_smt,
                'pengguna' => $request->user()->id_user]);
            if ($n === 1) {
                $this->catat($request, 'Verifikasi non-pengajaran', "dosen $id, periode {$p->label}");
            }
            return $n;
        });
        abort_if($n === 0, 409, 'Kegiatan ini tidak dapat diverifikasi (sudah terverifikasi atau belum ada kegiatan).');
        return ['data' => ['status' => 'terverifikasi']];
    }

    private function muatDetail(string $id, object $p): array
    {
        $profil = Bentuk::profil(DB::selectOne(Kueri::PROFIL, ['id' => $id]));
        abort_if($profil === null, 404, 'Dosen tidak ditemukan.');
        $status = DB::selectOne(Kueri::NP_STATUS, ['id' => $id, 'smt' => $p->id_smt])->status_verifikasi ?? null;
        $kegiatan = DB::select(Kueri::NP_KEGIATAN, ['id' => $id, 'smt' => $p->id_smt]);
        return ['periode' => $p->label] + Bentuk::npDetail($profil, $status, $kegiatan);
    }
}
