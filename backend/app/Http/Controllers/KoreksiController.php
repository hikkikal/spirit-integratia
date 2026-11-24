<?php

namespace App\Http\Controllers;

use App\Support\Bentuk;
use App\Support\HitungHonor;
use App\Support\HitungNonPengajaran;
use App\Support\KoreksiDosen as K;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi manual jabatan fungsional, golongan, dan tugas tambahan satu dosen (peran keuangan, admin input, admin super).
 * Setiap perubahan: wajib beralasan, tercatat di log aktivitas (nilai lama -> baru + alasan), honor dosen itu dihitung ulang
 * pada periode yang sedang dipilih, dan verifikasi yang sudah ada dikembalikan ke belum terverifikasi.
 */
class KoreksiController extends Controller
{
    use Catat;
    use Konteks;

    public function tampil(string $id)
    {
        return ['data' => $this->muat($id)];
    }

    public function jabatan(Request $request, string $id)
    {
        $v = $request->validate(['id_jabfung' => 'nullable|string|max:64', 'alasan' => 'required|string|min:5|max:500']);
        $lama = $this->dosen($id);
        $baru = $v['id_jabfung'] ?? null;
        if ($baru !== null) {
            abort_unless(DB::selectOne("SELECT 1 AS x FROM jabatan_fungsional WHERE id_jabfung = :j", ['j' => $baru]), 422, 'Jabatan fungsional tidak dikenal.');
        }
        return $this->terapkan($request, $id, $lama, 'Koreksi jabatan fungsional', $v['alasan'],
            'jabatan ' . K::ringkas($lama->nama_jabfung) . ' → ' . K::ringkas($baru === null ? 'kembali ke data SISTER' : $this->namaJabfung($baru)),
            function () use ($request, $id, $baru, $lama) {
                // Kosong = kembali mengikuti SISTER: nilai tetap sampai sinkron berikutnya.
                DB::update(K::UBAH_JABATAN, ['jab' => $baru ?? $lama->id_jabfung, 'manual' => $baru !== null ? 'true' : 'false', 'oleh' => $request->user()->id_user, 'id' => $id]);
            });
    }

    public function golongan(Request $request, string $id)
    {
        $v = $request->validate(['golongan' => 'nullable|string|in:' . implode(',', K::GOLONGAN), 'alasan' => 'required|string|min:5|max:500']);
        $lama = $this->dosen($id);
        $baru = $v['golongan'] ?? null;
        return $this->terapkan($request, $id, $lama, 'Koreksi golongan', $v['alasan'],
            'golongan ' . K::ringkas($lama->golongan_efektif) . ' → ' . ($baru === null ? 'kembali ke turunan jabatan' : $baru),
            fn () => DB::update(K::UBAH_GOLONGAN, ['gol' => $baru, 'oleh' => $request->user()->id_user, 'id' => $id]));
    }

    /** Tugas tambahan baru: nama bebas (tulis seperti di SISTER), kelompok wajib dipilih. */
    public function tugasTambah(Request $request, string $id)
    {
        $v = $request->validate(['jenis_tugas' => 'required|string|min:3|max:200', 'kelompok' => 'required|integer|in:1,2,3', 'unit_kerja' => 'nullable|string|max:200',
            'tanggal_mulai' => 'nullable|date', 'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai', 'alasan' => 'required|string|min:5|max:500']);
        $lama = $this->dosen($id);
        $nama = trim($v['jenis_tugas']);
        $baru = (string) \Illuminate\Support\Str::uuid();
        return $this->terapkan($request, $id, $lama, 'Tambah tugas tambahan', $v['alasan'], "tugas \"$nama\" (kelompok {$v['kelompok']}) ditambahkan",
            function () use ($request, $id, $v, $nama, $baru) {
                DB::insert(K::TUGAS_TAMBAH_ID, ['tugas' => $baru, 'id' => $id, 'jenis' => $nama, 'unit' => $v['unit_kerja'] ?? null, 'mulai' => $v['tanggal_mulai'] ?? null,
                    'selesai' => $v['tanggal_selesai'] ?? null, 'alasan' => $v['alasan'], 'oleh' => $request->user()->id_user]);
                DB::statement(K::KELOMPOK_SET, ['tugas' => $baru, 'id' => $id, 'kel' => (int) $v['kelompok'], 'alasan' => $v['alasan'], 'oleh' => $request->user()->id_user]);
            });
    }

    /** Tentukan (atau cabut) kelompok satu tugas, misalnya tugas SISTER yang belum dipetakan. Kosong = ikut pemetaan jenis. */
    public function tugasKelompok(Request $request, string $id, string $tugas)
    {
        $v = $request->validate(['kelompok' => 'nullable|integer|in:1,2,3', 'alasan' => 'required|string|min:5|max:500']);
        $lama = $this->dosen($id);
        $t = DB::selectOne(K::TUGAS_SATU, ['tugas' => $tugas, 'id' => $id]);
        abort_if($t === null, 404, 'Tugas tambahan tidak ditemukan.');
        $kel = $v['kelompok'] ?? null;
        abort_if($kel === null && $t->sumber === 'manual', 422, 'Tugas manual harus punya kelompok. Hapus tugasnya bila tidak berlaku.');
        return $this->terapkan($request, $id, $lama, 'Koreksi kelompok tugas tambahan', $v['alasan'],
            "tugas \"{$t->jenis_tugas}\" " . ($kel === null ? 'kembali mengikuti pemetaan jenis' : "ditetapkan kelompok $kel"),
            fn () => $kel === null ? DB::delete(K::KELOMPOK_HAPUS, ['tugas' => $tugas, 'id' => $id])
                : DB::statement(K::KELOMPOK_SET, ['tugas' => $tugas, 'id' => $id, 'kel' => (int) $kel, 'alasan' => $v['alasan'], 'oleh' => $request->user()->id_user]));
    }

    /** Tugas manual dihapus; tugas dari SISTER diabaikan (bisa dipulihkan). */
    public function tugasHapus(Request $request, string $id, string $tugas)
    {
        $v = $request->validate(['alasan' => 'required|string|min:5|max:500']);
        $lama = $this->dosen($id);
        $t = DB::selectOne(K::TUGAS_SATU, ['tugas' => $tugas, 'id' => $id]);
        abort_if($t === null, 404, 'Tugas tambahan tidak ditemukan.');
        $manual = $t->sumber === 'manual';
        return $this->terapkan($request, $id, $lama, $manual ? 'Hapus tugas tambahan' : 'Abaikan tugas tambahan', $v['alasan'],
            "tugas \"{$t->jenis_tugas}\" " . ($manual ? 'dihapus' : 'dari SISTER diabaikan'),
            fn () => $manual ? (DB::delete(K::TUGAS_HAPUS_MANUAL, ['tugas' => $tugas, 'id' => $id]) + DB::delete(K::KELOMPOK_HAPUS, ['tugas' => $tugas, 'id' => $id]))
                : DB::statement(K::TUGAS_ABAIKAN, ['tugas' => $tugas, 'id' => $id, 'alasan' => $v['alasan'], 'oleh' => $request->user()->id_user]));
    }

    public function tugasPulihkan(Request $request, string $id, string $tugas)
    {
        $v = $request->validate(['alasan' => 'required|string|min:5|max:500']);
        $lama = $this->dosen($id);
        $t = DB::selectOne(K::TUGAS_SATU, ['tugas' => $tugas, 'id' => $id]);
        abort_if($t === null, 404, 'Tugas tambahan tidak ditemukan.');
        return $this->terapkan($request, $id, $lama, 'Pulihkan tugas tambahan', $v['alasan'], "tugas \"{$t->jenis_tugas}\" diberlakukan kembali",
            fn () => DB::delete(K::TUGAS_PULIHKAN, ['tugas' => $tugas, 'id' => $id]));
    }

    // ---------- bersama ----------
    private function terapkan(Request $request, string $id, object $lama, string $aksi, string $alasan, string $ringkas, callable $ubah): array
    {
        // Data periode dibaca sendiri (id_smt, label, terkunci) supaya tidak bergantung pada bentuk keluaran periodeDipilih().
        $dipilih = $this->periodeDipilih($request);
        $p = DB::selectOne(HitungNonPengajaran::PERIODE, ['smt' => $dipilih->id_smt]);
        abort_if($p === null, 404, 'Periode tidak ditemukan.');
        $hasil = DB::transaction(function () use ($request, $id, $lama, $aksi, $alasan, $ringkas, $ubah, $p) {
            $ubah();
            $this->catat($request, $aksi, "{$lama->nama_sdm}: $ringkas. Alasan: $alasan");
            $info = ['periode' => $p->label, 'dihitungUlang' => false, 'verifikasiDireset' => false, 'catatan' => []];
            if ($p->terkunci) {
                $info['catatan'][] = "Periode {$p->label} sudah dikunci, honornya tidak dihitung ulang.";
                return $info;
            }
            $reset = DB::update(K::RESET_PENGAJARAN, ['id' => $id, 'smt' => $p->id_smt]) + DB::delete(K::RESET_NP, ['id' => $id, 'smt' => $p->id_smt]);
            $baca = fn (string $sql, array $b) => DB::select($sql, $b);
            $tulis = function (string $sql, array $b) { DB::statement($sql, $b); };
            $h = HitungHonor::hitungPeriode($baca, $tulis, $p->id_smt, $id);
            HitungNonPengajaran::hitungPeriode($baca, $tulis, $p->id_smt, $id);
            $info['dihitungUlang'] = true;
            $info['verifikasiDireset'] = $reset > 0;
            if ($h['perlu_ditentukan']) {
                $info['catatan'][] = 'Honor pengajaran belum bisa dihitung: ' . implode('; ', $h['perlu_ditentukan']);
            }
            if ($h['tanpa_tarif']) {
                $info['catatan'][] = 'Tarif belum ada: ' . implode('; ', $h['tanpa_tarif']);
            }
            if (array_sum($h['dilewati']) > 0) {
                $info['catatan'][] = 'Perhitungan yang sudah disesuaikan/dikunci tidak diubah.';
            }
            if ($reset > 0) {
                $this->catat($request, 'Reset verifikasi', "{$lama->nama_sdm}, periode {$p->label}: verifikasi dikembalikan karena data dikoreksi");
            }
            return $info;
        });
        return ['data' => $this->muat($id), 'hitung' => $hasil];
    }

    private function dosen(string $id): object
    {
        $d = DB::selectOne(K::DOSEN, ['id' => $id]);
        abort_if($d === null, 404, 'Dosen tidak ditemukan.');
        return $d;
    }

    private function namaJabfung(string $id): string
    {
        return DB::selectOne("SELECT nama_jabfung FROM jabatan_fungsional WHERE id_jabfung = :j", ['j' => $id])->nama_jabfung ?? $id;
    }

    private function muat(string $id): array
    {
        return K::bentuk($this->dosen($id), DB::select(K::TUGAS, ['id' => $id]), DB::select(K::JABFUNG_DAFTAR), []);
    }
}
