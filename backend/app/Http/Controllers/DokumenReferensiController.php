<?php

namespace App\Http\Controllers;

use App\Support\Bentuk;
use App\Support\Kueri;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Dokumen dasar tarif (Perdir/Kepdir) untuk dibaca semua peran yang sudah login.
 * Pengelolaan (unggah, ubah, hapus) tetap di AdminInputController.
 */
class DokumenReferensiController extends Controller
{
    public function daftar()
    {
        // adaBerkas: berkasnya benar-benar ada di storage (baris lama bisa hanya punya nama berkas).
        $data = array_map(function ($d) {
            $server = (string) (DB::selectOne(Kueri::PERDIR_BY_ID, ['id' => $d->id_dokumen])->nama_file_server ?? '');
            return Bentuk::perdir($d) + ['adaBerkas' => preg_match('/^[0-9a-f-]{36}\.pdf$/', $server) === 1 && Storage::exists("perdir/$server")];
        }, DB::select(Kueri::PERDIR_SEMUA));
        return ['data' => $data];
    }

    public function berkas(int $id)
    {
        $d = DB::selectOne(Kueri::PERDIR_BY_ID, ['id' => $id]);
        abort_if($d === null, 404, 'Dokumen tidak ditemukan.');
        // Nama di server dibuat sistem (uuid.pdf); periksa bentuknya agar tidak bisa menunjuk berkas lain.
        $server = (string) $d->nama_file_server;
        abort_unless(preg_match('/^[0-9a-f-]{36}\.pdf$/', $server) === 1 && Storage::exists("perdir/$server"), 404, 'Berkas dokumen belum diunggah.');

        $nama = preg_replace('/[^A-Za-z0-9._-]+/', '_', $d->nama_file_asli ?: ('perdir-' . $id . '.pdf'));
        return response()->file(Storage::path("perdir/$server"), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nama . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
