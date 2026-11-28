<?php

namespace App\Http\Controllers;

use App\Support\Bentuk;
use App\Support\Kueri;
use App\Support\SinkronSimulasi;
use App\Support\SinkronSister;
use App\Support\SisterKlien;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Admin Input: sinkronisasi SISTER (sungguhan bila SISTER_BASE_URL diisi, selain itu tiruan), master tarif, dokumen Perdir. Rute dibatasi peran:admin. */
class AdminInputController extends Controller
{
    use Catat;

    // ---------------- Sinkronisasi ----------------

    /**
     * Sinkronisasi tiruan: menutup yang waktunya sudah habis (juga yang ditinggalkan karena tab ditutup).
     * Sinkronisasi sungguhan: berjalan sendiri di latar belakang; di sini hanya yang terhenti (proses mati) yang ditutup sebagai gagal,
     * supaya tombol tidak terkunci selamanya.
     */
    private function selesaikan(): void
    {
        foreach (DB::select(Kueri::SINKRON_BERJALAN) as $r) {
            if ($r->mode === 'sister') {
                // Mati = tidak ada denyut (log kemajuan) selama $batas menit, BUKAN total waktu: sinkronisasi sehat boleh berjalan berjam-jam.
                $batas = (int) config('sister.batas_diam_menit', 10);
                if ((float) $r->diam > $batas * 60) {
                    SinkronSister::tutupMacet(DB::connection()->getPdo(), $r->id_sinkron,
                        "Proses sinkronisasi berhenti merespons (tidak ada kemajuan selama lebih dari $batas menit; komputer mati atau proses dimatikan). Tidak ada data yang tersimpan.");
                }
                continue;
            }
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
        // 'berjalan' = id sinkronisasi yang sedang jalan (atau null), supaya halaman yang dibuka ulang bisa melanjutkan pemantauan.
        return ['data' => Bentuk::koneksi(DB::selectOne(Kueri::SINKRON_KONEKSI)) + ['berjalan' => DB::selectOne(Kueri::SINKRON_BERJALAN)->id_sinkron ?? null]];
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
        $nyata = config('sister.mode') === 'sister';
        if ($nyata) {
            // Sinkronisasi sungguhan hanya untuk satu periode yang belum terkunci: yang dipilih, atau yang terbaru.
            // 'periode' boleh id semester (mis. 20252) atau label (mis. "Genap 2025/2026", yang dipakai dropdown di frontend).
            $in = $request->filled('periode') ? (string) $request->input('periode') : '';
            if ($in !== '' && ! preg_match('/^\d{5}$/', $in)) {
                $in = DB::selectOne(Kueri::PERIODE_BY_LABEL, ['label' => $in])->id_smt ?? '';
                abort_if($in === '', 422, 'Periode tidak ditemukan.');
            }
            $smt = $in !== '' ? DB::selectOne(Kueri::PERIODE_SINKRON, ['id' => $in]) : DB::selectOne(Kueri::PERIODE_SINKRON_AWAL);
            // Semester lama (SISTER mulai 20211) belum tentu ada di tabel periode: dibuat otomatis dari id-nya.
            if ($smt === null && preg_match('/^20[2-9]\d[12]$/', $in) === 1 && (int) $in >= 20211) {
                $l = SinkronSister::labelPeriode($in);
                DB::insert('INSERT INTO periode (id_smt, label, semester, tahun_ajaran) VALUES (?, ?, ?, ?) ON CONFLICT DO NOTHING', [$in, $l['label'], $l['semester'], $l['tahun_ajaran']]);
                $smt = DB::selectOne(Kueri::PERIODE_SINKRON, ['id' => $in]);
            }
            abort_if($smt === null, 422, 'Periode tidak ditemukan, atau semua periode sudah dikunci.');
            abort_if((bool) $smt->terkunci, 422, 'Periode ' . $smt->label . ' sudah dikunci dan tidak bisa disinkronkan.');
        } else {
            $smt = DB::selectOne(Kueri::PERIODE_SEMUA);
        }
        if ($nyata) {
            $pasca = DB::selectOne("SELECT s.id_sinkron FROM sinkronisasi s WHERE s.mode = 'sister' AND s.status = 'berhasil' AND s.selesai > now() - interval '30 minutes'
                AND NOT EXISTS (SELECT 1 FROM sinkronisasi_endpoint e WHERE e.id_sinkron = s.id_sinkron AND e.no = 18) LIMIT 1");
            abort_if($pasca !== null, 409, 'Sinkronisasi sedang berjalan. Tunggu sampai selesai.');
        }
        $id = (string) Str::uuid();
        // Simulasi gagal hanya untuk pengembangan lokal (Shift + klik di frontend), dan hanya pada mode tiruan.
        $sim = ! $nyata && app()->environment('local') && $request->boolean('simulasiGagal');
        try {
            DB::insert(Kueri::SINKRON_BARU, ['id' => $id, 'smt' => $smt->id_smt ?? null, 'oleh' => $request->user()->id_user, 'sim' => $sim ? 'true' : 'false',
                'mode' => $nyata ? 'sister' : 'tiruan']);
        } catch (UniqueConstraintViolationException $e) {
            abort(409, 'Sinkronisasi sedang berjalan. Tunggu sampai selesai.');
        }
        $this->catat($request, 'Sinkronisasi SISTER', 'semester ' . ($smt->id_smt ?? '-'));
        if ($nyata) {
            $this->jalankanSister($id, $smt->id_smt);
        }
        return response()->json(['data' => ['id' => $id]], 201);
    }

    /**
     * Menjalankan sinkronisasi di PROSES TERPISAH (php artisan sinkron:sister --id-sinkron=...) lalu langsung kembali,
     * jadi tombol dijawab seketika dan halaman memantau lewat GET /sinkron/{id}/status.
     * Sebelumnya memakai dispatch()->afterResponse(): di `php artisan serve` (server bawaan PHP, satu proses) respons baru benar-benar
     * dilepas setelah seluruh sinkronisasi selesai, dan selama itu semua request lain (termasuk pemantauan status) antre, sehingga terasa macet.
     * Proses terpisah tidak punya masalah itu, di Laragon/Windows maupun Nginx + php-fpm.
     */
    private function jalankanSister(string $id, string $smt): void
    {
        $args = [PHP_BINARY, base_path('artisan'), 'sinkron:sister', $smt, '--id-sinkron=' . $id];
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Konsol TERPISAH dan tersembunyi (bukan `start /B`): dengan /B proses ikut konsol `artisan serve`, jadi Ctrl+C atau menutup
                // jendela server ikut membunuh sinkronisasi yang sedang berjalan.
                $q = fn (string $x) => "'" . str_replace("'", "''", $x) . "'";
                // Keluaran dan galat proses (mis. kehabisan memori) dicatat di storage/logs supaya penyebab proses mati bisa dilihat.
                $ps = 'Start-Process -WindowStyle Hidden -FilePath ' . $q($args[0]) . ' -WorkingDirectory ' . $q(base_path())
                    . ' -RedirectStandardOutput ' . $q(storage_path('logs/sinkron_keluar.log')) . ' -RedirectStandardError ' . $q(storage_path('logs/sinkron_galat.log'))
                    . ' -ArgumentList ' . implode(',', array_map($q, array_slice($args, 1)));
                pclose(popen('powershell -NoProfile -NonInteractive -Command "' . str_replace('"', '\\"', $ps) . '" > NUL 2>&1', 'r'));
            } else {
                exec(implode(' ', array_map('escapeshellarg', $args)) . ' > ' . escapeshellarg(storage_path('logs/sinkron_keluar.log')) . ' 2>&1 &');
            }
        } catch (\Throwable $e) {
            SinkronSister::tutupMacet(DB::connection()->getPdo(), $id, 'Tidak bisa memulai proses sinkronisasi di latar belakang: ' . mb_substr($e->getMessage(), 0, 200));
        }
    }

    public function status(string $id)
    {
        $this->selesaikan();
        $r = DB::selectOne(Kueri::SINKRON_BY_ID, ['id' => $id]);
        abort_if($r === null, 404, 'Sinkronisasi tidak ditemukan.');
        if ($r->status === 'berjalan') {
            $b = DB::selectOne(Kueri::SINKRON_BERJALAN);   // hanya satu yang berjalan
            $k = $b->mode === 'sister'
                ? SinkronSister::kemajuan('berjalan', (int) $b->tahap, (int) $b->ep_selesai, (int) ($b->endpoint_total ?? count(SinkronSister::ENDPOINT)))
                : SinkronSimulasi::kemajuan((float) $b->lewat, (bool) $b->simulasi_gagal);
            // Fase SISTER mengisi 80% pertama; sisanya data akademik dan hitung honor.
            $k['persen'] = (int) round($k['persen'] * 0.8);
            $k['langkah'] = array_merge($k['langkah'], ['menunggu', 'menunggu']);
            return ['data' => $k + ['logId' => null]];
        }
        if ($r->mode === 'sister') {
            $ep = DB::select(Kueri::SINKRON_EP, ['id' => $id]);
            $ok = count(array_filter($ep, fn ($e) => $e->status === 'berhasil' && $e->no <= count(SinkronSister::ENDPOINT)));
            $k = SinkronSister::kemajuan($r->status, (int) $r->tahap, $ok, count(SinkronSister::ENDPOINT));
            if ($r->status === 'berhasil') {
                $k = $this->denganPasca($k, $ep, $this->pascaBerjalan($id));
            } else {
                $k['langkah'] = array_merge($k['langkah'], ['menunggu', 'menunggu']);
            }
            return ['data' => $k + ['logId' => $r->id_sinkron]];
        }
        $k = SinkronSimulasi::kemajuanSelesai($r->status);
        $k['langkah'] = array_merge($k['langkah'], array_fill(0, 2, $r->status === 'berhasil' ? 'selesai' : 'menunggu'));
        return ['data' => $k + ['logId' => $r->id_sinkron]];
    }

    /**
     * Sesudah SISTER berhasil, proses yang sama masih menarik data akademik dan menghitung honor (endpoint 14-18).
     * Dianggap masih berjalan selama endpoint 18 belum tercatat dan SISTER selesai belum lebih dari 30 menit lalu
     * (batas supaya proses yang mati di tengah tidak membuat tombol menunggu selamanya).
     */
    private function pascaBerjalan(string $id): bool
    {
        $x = DB::selectOne("SELECT 1 AS ada FROM sinkronisasi s WHERE s.id_sinkron = :id AND s.mode = 'sister' AND s.status = 'berhasil'
            AND s.selesai > now() - interval '30 minutes'
            AND NOT EXISTS (SELECT 1 FROM sinkronisasi_endpoint e WHERE e.id_sinkron = s.id_sinkron AND e.no = 18)", ['id' => $id]);
        return $x !== null;
    }

    /** Menambah langkah 5 (data akademik, endpoint 14-17) dan 6 (hitung honor, endpoint 18) ke kemajuan SISTER yang sudah berhasil. */
    private function denganPasca(array $k, array $ep, bool $berjalan): array
    {
        $per = fn (int $dari, int $sampai) => array_values(array_filter($ep, fn ($e) => $e->no >= $dari && $e->no <= $sampai));
        $akad = $per(14, 17);
        $hitung = $per(18, 18);
        if ($berjalan) {
            $akadSelesai = count($akad) >= 5;
            $k['status'] = 'berjalan';
            $k['persen'] = $akadSelesai ? 92 : 85;
            $k['langkah'] = array_merge($k['langkah'], [$akadSelesai ? 'selesai' : 'berjalan', $akadSelesai ? 'berjalan' : 'menunggu']);
            return $k;
        }
        $semuaLewat = count($akad) > 0 && count(array_filter($akad, fn ($e) => $e->status === 'dilewati')) === count($akad);
        $adaGagal = count(array_filter($akad, fn ($e) => $e->status === 'gagal')) > 0;
        $k['langkah'] = array_merge($k['langkah'], [
            count($akad) === 0 ? 'menunggu' : ($adaGagal ? 'gagal' : ($semuaLewat ? 'dilewati' : 'selesai')),
            count($hitung) === 0 ? 'menunggu' : ($hitung[0]->status === 'gagal' ? 'gagal' : 'selesai'),
        ]);
        return $k;
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

    // ---------------- Tarif honor non-pengajaran (SBM Internal 23/2026) ----------------
    // Barisnya tetap (kode tarif dipakai penghitung); Admin Input hanya mengubah nominalnya. Berlaku pada "Hitung ulang" berikutnya.

    public function tarifNp()
    {
        return ['data' => array_map([Bentuk::class, 'tarifNp'], DB::select(Kueri::TARIF_NP))];
    }

    public function tarifNpUbah(Request $request, string $kode)
    {
        $lama = DB::selectOne(Kueri::TARIF_NP_BY_KODE, ['kode' => $kode]);
        abort_if($lama === null, 404, 'Tarif tidak ditemukan.');
        $d = $request->validate([
            'golIV' => ['required', 'integer', 'min:1', 'max:100000000'],
            'golIII' => ['required', 'integer', 'min:1', 'max:100000000'],
            'keterangan' => ['nullable', 'string', 'max:200'],
        ], [], ['golIV' => 'Tarif golongan IV', 'golIII' => 'Tarif golongan III']);
        abort_if($d['golIII'] > $d['golIV'], 422, 'Tarif golongan III tidak boleh lebih besar dari golongan IV.');
        DB::update(Kueri::TARIF_NP_UBAH, ['iv' => $d['golIV'], 'iii' => $d['golIII'], 'ket' => $d['keterangan'] ?? null, 'kode' => $kode]);
        $this->catat($request, 'Ubah tarif non-pengajaran', "{$lama->nama}: IV Rp{$lama->tarif_iv} menjadi Rp{$d['golIV']}, III Rp{$lama->tarif_iii} menjadi Rp{$d['golIII']}");
        return ['data' => Bentuk::tarifNp(DB::selectOne(Kueri::TARIF_NP_BY_KODE, ['kode' => $kode]))];
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
