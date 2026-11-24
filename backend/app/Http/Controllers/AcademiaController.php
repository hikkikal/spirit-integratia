<?php

namespace App\Http\Controllers;

use App\Support\Academia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rincian per minggu + pencocokan SISTER. Data akademik DITARIK oleh sinkronisasi (tombol Sinkronisasi Data di Admin Input,
 * atau php artisan sinkron:akademik) dan disimpan di tabel academia_hasil; di sini hanya dibaca. Pencocokan dengan SISTER dan
 * total honor dihitung ulang dari data terkini setiap halaman dibuka. Honor dosen itu sendiri dihitung otomatis di akhir sinkronisasi.
 */
class AcademiaController extends Controller
{
    use Konteks;

    private const SISTER = 'SELECT id_kelas::text AS id_kelas, kode_matakuliah, mata_kuliah, kelas, sks, tatap_muka_rencana, tatap_muka_realisasi
                            FROM pengajaran WHERE id_dosen = :id AND id_smt = :smt ORDER BY mata_kuliah, kelas';
    private const HITUNG = 'SELECT mk, kelas, sks_100, sks_50, sks_0, bruto, pajak, netto FROM v_detail_honor_kelas WHERE id_dosen = :id AND id_smt = :smt';

    /** Keuangan / Pimpinan / Admin: untuk satu dosen. */
    public function lihat(Request $request, string $id)
    {
        return $this->tampil($request, $id);
    }

    /** Dosen: milik sendiri. */
    public function lihatSaya(Request $request)
    {
        $id = $request->user()->id_dosen ?? null;
        abort_if(! $id, 404, 'Akun Anda belum terhubung ke data dosen.');
        return $this->tampil($request, (string) $id);
    }

    private function tampil(Request $request, string $id)
    {
        $p = $this->periodeDipilih($request);
        $r = DB::selectOne('SELECT data FROM academia_hasil WHERE id_dosen = :id AND id_smt = :smt', ['id' => $id, 'smt' => $p->id_smt]);
        $d = $r ? json_decode($r->data, true) : null;
        return ['data' => $d ? $this->sertaiBanding($d, $id, $p->id_smt) : null];
    }

    private function sertaiBanding(array $d, string $id, string $smt): array
    {
        $hitung = DB::select(self::HITUNG, ['id' => $id, 'smt' => $smt]);
        $d['banding'] = Academia::banding($d['kelas'], DB::select(self::SISTER, ['id' => $id, 'smt' => $smt]), $hitung);
        $h = Academia::honorMinggu($d['minggu'], $hitung);
        $d['minggu'] = $h['minggu'];
        $d['total'] = $h['total'];
        $d['tanpa_honor'] = $h['tanpa_honor'];
        if (! config('app.debug')) {
            unset($d['contoh']);
        }
        return $d;
    }
}
