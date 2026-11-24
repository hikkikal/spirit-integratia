<?php

namespace App\Http\Controllers;

use App\Support\Ekspor\Pdf;
use App\Support\Ekspor\Xlsx;
use App\Support\Kueri;
use App\Support\Laporan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Laporan & Ekspor: pratinjau (Keuangan dan Pimpinan), unduh Excel/PDF dan riwayat ekspor (hanya Keuangan). */
class LaporanController extends Controller
{
    use Konteks;

    /** Parameter bersama: ?periode=&jenis=&jurusan=&status= (status bawaan: terverifikasi). */
    private function ambil(Request $request): array
    {
        $p = $this->periodeDipilih($request);
        $jenis = (string) $request->query('jenis', 'gabungan');
        $status = (string) $request->query('status', 'terverifikasi');
        abort_unless(isset(Laporan::JENIS[$jenis]), 422, 'Jenis laporan tidak dikenal.');
        abort_unless(isset(Laporan::STATUS[$status]), 422, 'Status tidak dikenal.');
        $jurusan = trim((string) $request->query('jurusan', ''));
        $hasil = Laporan::susun(fn (string $sql, array $b) => DB::select($sql, $b), $jenis, $p->id_smt, $jurusan, $status);
        return [$p, $jenis, $status, $jurusan, $hasil];
    }

    public function data(Request $request)
    {
        [$p, $jenis, $status, $jurusan, $h] = $this->ambil($request);
        return ['data' => ['periode' => $p->label, 'jenis' => $jenis, 'status' => $status, 'jurusan' => $jurusan] + $h];
    }

    public function ekspor(Request $request)
    {
        $format = (string) $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 422, 'Format harus xlsx atau pdf.');
        [$p, $jenis, $status, $jurusan, $h] = $this->ambil($request);

        $judul = [
            'Laporan Honor '.Laporan::JENIS[$jenis].' (kelebihan SKS)',
            'Periode '.$p->label.' | Jurusan: '.($jurusan !== '' ? $jurusan : 'Semua').' | Status: '.Laporan::STATUS[$status],
            'Dicetak '.now()->timezone('Asia/Jakarta')->format('d-m-Y H:i').' WIB oleh '.($request->user()->nama ?? 'Keuangan'),
        ];
        $isi = $format === 'xlsx'
            ? Xlsx::bangun(['judul' => $judul, 'nama' => 'Honor '.Laporan::JENIS[$jenis], 'kolom' => $h['kolom'], 'baris' => $h['baris'], 'total' => $h['total']])
            : Pdf::bangun(['judul' => $judul, 'kolom' => $h['kolom'], 'baris' => $h['baris'], 'total' => $h['total']]);

        DB::insert(Kueri::LOG, [
            'pelaku' => $request->user()->id_user, 'aksi' => 'Ekspor laporan',
            'objek' => strtoupper($format).' | '.Laporan::JENIS[$jenis].' | '.$p->label.' | '.($jurusan !== '' ? $jurusan : 'Semua jurusan').' | '.Laporan::STATUS[$status].' | '.count($h['baris']).' dosen',
            'peran' => $request->session()->get('peran_aktif'), 'status' => 'berhasil',
        ]);

        $nama = 'laporan-honor-'.$jenis.'-'.Str::slug($p->label).'.'.$format;
        return response($isi, 200, [
            'Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function riwayat()
    {
        $rows = DB::select("SELECT l.waktu, l.objek, coalesce(u.nama, '-') AS oleh FROM log_aktivitas l LEFT JOIN users u ON u.id_user = l.pelaku
                            WHERE l.aksi = 'Ekspor laporan' ORDER BY l.waktu DESC LIMIT 20");
        return ['data' => array_map(fn ($r) => [
            'waktu' => \Illuminate\Support\Carbon::parse($r->waktu)->timezone('Asia/Jakarta')->format('d-m-Y H:i'),
            'objek' => $r->objek, 'oleh' => $r->oleh,
        ], $rows)];
    }
}
