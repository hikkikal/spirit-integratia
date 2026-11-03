<?php

namespace App\Http\Controllers;

use App\Support\Bentuk;
use App\Support\Kueri;
use App\Support\SinkronSimulasi;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Admin Input: sinkronisasi SISTER (tiruan), master tarif, dokumen Perdir. Rute dibatasi peran:admin. */
class AdminInputController extends Controller
{
    use Catat;

    // ---------------- Sinkronisasi ----------------

    /** Menutup sinkronisasi tiruan yang waktunya sudah habis (juga yang ditinggalkan karena tab ditutup). */
    private function selesaikan(): void
    {
        foreach (DB::select(Kueri::SINKRON_BERJALAN) as $r) {
            $gagal = (bool) $r->simulasi_gagal;
            if (! SinkronSimulasi::selesai((float) $r->lewat, $gagal)) {
                continue;
            }
            $rinci = SinkronSimulasi::rincian($gagal);
            DB::transaction(function () use ($r, $gagal, $rinci) {
                $n = DB::update(Kueri::SINKRON_SELESAI, ['status' => $gagal ? 'gagal' : 'berhasil',
                    'durasi' => max(1, (int) round((float) $r->lewat)), 'pesan' => $rinci['pesan'], 'id' => $r->id_sinkron]);
                if ($n !== 1) {
                    return;   // sudah ditutup oleh permintaan lain
                }
                foreach ($rinci['endpoint'] as $e) {
                    DB::insert(Kueri::SINKRON_EP_TAMBAH, ['id' => $r->id_sinkron, 'no' => $e['no'], 'nama' => $e['nama'], 'path' => $e['path'],
                        'status' => $e['status'], 'detik' => $e['detik'], 'rekord' => $e['rekord'], 'ket' => $e['ket']]);
                }
                foreach ($rinci['atribut'] as $a) {
                    DB::insert(Kueri::SINKRON_ATR_TAMBAH, ['id' => $r->id_sinkron, 'no' => $a['no'], 'nama' => $a['nama'], 'urut' => $a['urut'], 'status' => $a['status']]);
                }
            });
        }
    }

    public function koneksi()
    {
        $this->selesaikan();
        return ['data' => Bentuk::koneksi(DB::selectOne(Kueri::SINKRON_KONEKSI))];
    }

    public function riwayat()
    {
        $this->selesaikan();
        return ['data' => array_map(fn ($r) => ['id' => $r->id_sinkron, 'mulai' => Bentuk::stempel($r->mulai), 'status' => $r->status],
            DB::select(Kueri::SINKRON_RIWAYAT))];
    }

    public function mulai(Request $request)
    {
        $this->selesaikan();
        $smt = DB::selectOne(Kueri::PERIODE_SEMUA);
        $id = (string) Str::uuid();
        // Simulasi gagal hanya untuk pengembangan lokal (Shift + klik di frontend).
        $sim = app()->environment('local') && $request->boolean('simulasiGagal');
        try {
            DB::insert(Kueri::SINKRON_BARU, ['id' => $id, 'smt' => $smt->id_smt ?? null, 'oleh' => $request->user()->id_user, 'sim' => $sim ? 'true' : 'false']);
        } catch (UniqueConstraintViolationException $e) {
            abort(409, 'Sinkronisasi sedang berjalan. Tunggu sampai selesai.');
        }
        $this->catat($request, 'Sinkronisasi SISTER', 'semester ' . ($smt->id_smt ?? '-'));
        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function status(string $id)
    {
        $this->selesaikan();
        $r = DB::selectOne(Kueri::SINKRON_BY_ID, ['id' => $id]);
        abort_if($r === null, 404, 'Sinkronisasi tidak ditemukan.');
        if ($r->status === 'berjalan') {
            $b = DB::selectOne(Kueri::SINKRON_BERJALAN);   // hanya satu yang berjalan
            $k = SinkronSimulasi::kemajuan((float) $b->lewat, (bool) $b->simulasi_gagal);
            return ['data' => $k + ['logId' => null]];
        }
        return ['data' => SinkronSimulasi::kemajuanSelesai($r->status) + ['logId' => $r->id_sinkron]];
    }

    public function log(string $id)
    {
        $this->selesaikan();
        $r = DB::selectOne(Kueri::SINKRON_BY_ID, ['id' => $id]);
        abort_if($r === null || $r->status === 'berjalan', 404, 'Log sinkronisasi tidak ditemukan.');
        return ['data' => Bentuk::logSinkron($r, DB::select(Kueri::SINKRON_EP, ['id' => $id]), DB::select(Kueri::SINKRON_ATR, ['id' => $id]))];
    }

    // ---------------- Master tarif ----------------

    public function tarif()
    {
        return ['data' => array_map([Bentuk::class, 'tarifAdmin'], DB::select(Kueri::TARIF_ADMIN))];
    }

    private function dataTarif(Request $request): array
    {
        $d = $request->validate([
            'jenis' => ['required', Rule::in(['Kerja Sama', 'Reguler'])],
            'kelas' => ['required', 'string', 'max:120'],
            'jabatan' => ['required', Rule::in(Bentuk::JABATAN_OPSI)],
            'honor' => ['required', 'integer', 'min:1', 'max:100000000'],
            'mulai' => ['required', 'date_format:Y-m-d'],
            'sampai' => ['required', 'date_format:Y-m-d', 'after_or_equal:mulai'],
        ], [
            'honor.integer' => 'Honor harus berupa angka rupiah bulat.', 'honor.min' => 'Honor harus lebih dari 0.',
            'jabatan.in' => 'Jabatan/golongan tidak dikenal.', 'sampai.after_or_equal' => 'Tanggal berakhir harus sama dengan atau setelah tanggal mulai.',
        ]);
        $d['kelas'] = $d['jenis'] === 'Reguler' ? 'Reguler' : trim($d['kelas']);
        preg_match('/\b(I{1,3}|IV)\//', $d['jabatan'], $m);
        $d['gol'] = $m[1] ?? null;
        return $d;
    }

    private function simpanTarif(Request $request, ?int $id)
    {
        $d = $this->dataTarif($request);
        $lama = $id === null ? null : DB::selectOne(Kueri::TARIF_ADMIN_BY_ID, ['id' => $id]);
        abort_if($id !== null && $lama === null, 404, 'Tarif tidak ditemukan.');
        $bentrok = DB::selectOne(Kueri::TARIF_TUMPANG_TINDIH, ['jenis' => $d['jenis'], 'kelas' => $d['kelas'], 'jabatan' => $d['jabatan'],
            'sampai' => $d['sampai'], 'mulai' => $d['mulai'], 'id' => $id ?? 0]);
        abort_if($bentrok !== null, 422, 'Sudah ada tarif untuk kelas dan jabatan ini pada rentang tanggal tersebut.');
        $b = ['jenis' => $d['jenis'], 'gol' => $d['gol'], 'honor' => $d['honor'], 'mulai' => $d['mulai'], 'sampai' => $d['sampai'],
              'kelas' => $d['kelas'], 'jabatan' => $d['jabatan']];
        if ($id === null) {
            $baru = DB::selectOne(Kueri::TARIF_TAMBAH, $b)->id_tarif;
            $this->catat($request, 'Tambah tarif', "tarif #$baru: {$d['kelas']}, {$d['jabatan']}, Rp{$d['honor']}");
        } else {
            DB::selectOne(Kueri::TARIF_UBAH, $b + ['id' => $id]);
            $baru = $id;
            $this->catat($request, 'Ubah tarif', "tarif #$id: Rp{$lama->tarif_per_sks} menjadi Rp{$d['honor']}");
        }
        return ['data' => Bentuk::tarifAdmin(DB::selectOne(Kueri::TARIF_ADMIN_BY_ID, ['id' => $baru]))];
    }

    public function tarifTambah(Request $request)
    {
        return response()->json($this->simpanTarif($request, null), 201);
    }

    public function tarifUbah(Request $request, int $id)
    {
        return $this->simpanTarif($request, $id);
    }

    // ---------------- Dokumen Perdir ----------------

    public function perdir()
    {
        return ['data' => array_map([Bentuk::class, 'perdir'], DB::select(Kueri::PERDIR_SEMUA))];
    }

    public function perdirTambah(Request $request)
    {
        $d = $request->validate([
            'nomor' => ['required', 'string', 'max:120', 'unique:perdir_dokumen,nomor'],
            'judul' => ['required', 'string', 'max:300'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'berkas' => ['required', 'file', 'mimetypes:application/pdf', 'max:20480'],   // tipe diperiksa dari isi berkas, maks 20 MB
        ], [
            'nomor.unique' => 'Nomor peraturan sudah terdaftar.', 'berkas.mimetypes' => 'Berkas harus berupa PDF.',
            'berkas.max' => 'Ukuran berkas maksimal 20 MB.', 'berkas.required' => 'Pilih berkas PDF.',
        ]);
        $f = $request->file('berkas');
        $h = fopen($f->getRealPath(), 'rb');
        $kepala = fread($h, 5);
        fclose($h);
        abort_unless($kepala === '%PDF-', 422, 'Berkas harus berupa PDF.');

        $server = Str::uuid() . '.pdf';
        $asli = mb_substr(preg_replace('/[^\p{L}\p{N}._\- ]+/u', '_', basename($f->getClientOriginalName())), 0, 200);
        $f->storeAs('perdir', $server);   // disk 'local' = storage/app, di luar public/
        try {
            $baris = DB::selectOne(Kueri::PERDIR_TAMBAH, ['nomor' => trim($d['nomor']), 'judul' => trim($d['judul']), 'tanggal' => $d['tanggal'],
                'asli' => $asli, 'server' => $server]);
        } catch (\Throwable $e) {
            Storage::delete("perdir/$server");
            if ($e instanceof UniqueConstraintViolationException) {
                abort(422, 'Nomor peraturan sudah terdaftar.');
            }
            throw $e;
        }
        $this->catat($request, 'Unggah Perdir', $d['nomor']);
        return response()->json(['data' => Bentuk::perdir(DB::selectOne(Kueri::PERDIR_BY_ID, ['id' => $baris->id_dokumen]))], 201);
    }

    public function perdirStatus(Request $request, int $id)
    {
        $d = $request->validate(['berlaku' => ['required', 'boolean']]);
        abort_if(DB::selectOne(Kueri::PERDIR_BY_ID, ['id' => $id]) === null, 404, 'Dokumen tidak ditemukan.');
        $ya = filter_var($d['berlaku'], FILTER_VALIDATE_BOOLEAN);
        DB::update(Kueri::PERDIR_BERLAKU, ['berlaku' => $ya ? 'true' : 'false', 'id' => $id]);
        $baris = DB::selectOne(Kueri::PERDIR_BY_ID, ['id' => $id]);
        $this->catat($request, $ya ? 'Aktifkan Perdir' : 'Nonaktifkan Perdir', $baris->nomor);
        return ['data' => Bentuk::perdir($baris)];
    }

    public function perdirHapus(Request $request, int $id)
    {
        $baris = DB::selectOne(Kueri::PERDIR_BY_ID, ['id' => $id]);
        abort_if($baris === null, 404, 'Dokumen tidak ditemukan.');
        $dipakai = DB::selectOne(Kueri::PERDIR_DIPAKAI, ['id' => $id])->n;
        abort_if($dipakai > 0, 409, "Dokumen ini dipakai oleh $dipakai tarif. Nonaktifkan saja, jangan dihapus.");
        DB::delete(Kueri::PERDIR_HAPUS, ['id' => $id]);
        if (preg_match('/^[0-9a-f-]{36}\.pdf$/', (string) $baris->nama_file_server)) {
            Storage::delete('perdir/' . $baris->nama_file_server);
        }
        $this->catat($request, 'Hapus Perdir', $baris->nomor);
        return response()->noContent();
    }
}
