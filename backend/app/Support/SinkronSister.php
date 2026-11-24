<?php

namespace App\Support;

/**
 * Sinkronisasi SISTER sungguhan. Menggantikan SinkronSimulasi bila SINKRON_MODE=sister.
 * Murni PHP + PDO (di Laravel: DB::connection()->getPdo()), jadi bisa diuji tanpa Laravel.
 *
 * Empat langkah di halaman Admin Input dipetakan ke tahap berikut (kolom sinkronisasi.tahap):
 *   1 login dan kirim request   2 mengambil data dari SISTER   3 data diterima   4 menyimpan ke database
 *
 * Aturan keselamatan:
 * - Semua data diambil dulu ke memori. Penyimpanan baru dimulai setelah SEMUA endpoint berhasil, dalam SATU transaksi.
 *   Bila ada endpoint yang gagal, tidak ada data yang berubah di database.
 * - Hanya periode yang belum terkunci yang boleh disinkronkan (dicek oleh pemanggil).
 * - golongan dan id_jurusan dosen tidak ada di SISTER, jadi tidak pernah ditimpa.
 * - Perhitungan honor TIDAK dijalankan di sini. Sinkronisasi hanya memperbarui data sumber; hitung ulang tetap tindakan Keuangan.
 */
final class SinkronSister
{
    /** no => [nama, path]. Urutan = urutan dijalankan. */
    public const ENDPOINT = [
        1 => ['Jabatan Fungsional', '/referensi/jabatan_fungsional'],
        2 => ['Pegawai di Perguruan Tinggi', '/referensi/sdm'],
        3 => ['List Data Pengajaran', '/pengajaran'],
        4 => ['Detail Pengajaran', '/pengajaran/{id}'],
        5 => ['BKD Pengajaran', '/bkd/ajar'],
        6 => ['BKD Akhir', '/bkd/laporan_akhir_bkd'],
        7 => ['Bimbingan Mahasiswa', '/bimbingan_mahasiswa'],
        8 => ['Pengujian Mahasiswa', '/pengujian_mahasiswa'],
        9 => ['Detail Pengujian Mahasiswa', '/pengujian_mahasiswa/{id}'],
        10 => ['BKD Penunjang (Seminar Proposal)', '/bkd/tunjang'],
        11 => ['Tugas Tambahan', '/tugas_tambahan'],
        12 => ['Jabatan Fungsional Dosen', '/jabatan_fungsional'],   // pelengkap jabatan dosen yang BKD akhirnya tidak memuat id_jabfung
    ];

    /** Jenis kegiatan non-pengajaran yang diterima database (CHECK pada kegiatan_nonpengajaran.jenis). 'Lainnya' menampung jenis yang belum ada di SBM (jenis aslinya tetap terbaca di kolom kategori). */
    public const JENIS = ['Skripsi', 'Tesis', 'Tugas Akhir', 'Seminar Proposal', 'PKL/Magang', 'Pembimbing Akademik', 'Lainnya'];

    /** Menyamakan penulisan jenis SISTER dengan jenis database. Tidak ada yang dibuang: yang tidak dikenal menjadi 'Lainnya'. */
    public static function jenisKegiatan(?string $jenis): string
    {
        $j = mb_strtolower(trim((string) $jenis));
        foreach (self::JENIS as $x) {
            if ($j === mb_strtolower($x)) {
                return $x;
            }
        }
        return match (true) {
            (bool) preg_match('/(bimbing|pembimbing|dosen wali|penasihat|penasehat).*akademi[ks]|akademi[ks].*(bimbing|wali|penasihat|penasehat)/u', $j) => 'Pembimbing Akademik',
            (bool) preg_match('/magang|praktik|praktek|\bpkl\b|on job/u', $j) => 'PKL/Magang',
            default => 'Lainnya',
        };
    }

    private array $stmt = [];
    private int $noSekarang = 0;
    private ?string $idSinkron = null;
    private float $denyutTerakhir = 0;

    /** @param array{id_sdm?:string[],maks?:int,batas_detik?:int,semua_status?:bool,log?:callable} $opsi id_sdm: hanya dosen ini (uji coba); maks: batasi jumlah dosen; semua_status: sertakan dosen non-aktif */
    public function __construct(private \PDO $db, private SisterKlien $klien, private array $opsi = [])
    {
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    // ------------------------------------------------------------ kemajuan (untuk halaman)

    /** @return array{status:string,persen:int,langkah:string[]} */
    public static function kemajuan(string $status, int $tahap, int $epSelesai, int $epTotal): array
    {
        $langkah = [];
        for ($i = 1; $i <= 4; $i++) {
            $langkah[] = $status === 'berhasil' ? 'selesai'
                : ($i < $tahap ? 'selesai' : ($i === $tahap ? ($status === 'gagal' ? 'gagal' : 'berjalan') : 'menunggu'));
        }
        $persen = match (true) {
            $status === 'berhasil' => 100,
            $tahap <= 1 => 3,
            $tahap === 2 => 5 + (int) round(70 * min($epSelesai, max($epTotal, 1)) / max($epTotal, 1)),
            $tahap === 3 => 80,
            default => 90,
        };
        return ['status' => $status, 'persen' => $persen, 'langkah' => $langkah];
    }

    /** Menutup sinkronisasi yang terhenti (proses PHP mati di tengah jalan) supaya tombol tidak terkunci selamanya. */
    public static function tutupMacet(\PDO $db, string $id, string $pesan): void
    {
        $db->beginTransaction();
        try {
            $s = $db->prepare("UPDATE sinkronisasi SET status = 'gagal', selesai = now(), pesan_gagal = :p,
                durasi_detik = greatest(1, EXTRACT(EPOCH FROM (now() - mulai))::int) WHERE id_sinkron = CAST(:id AS uuid) AND status = 'berjalan'");
            $s->execute(['p' => $pesan, 'id' => $id]);
            if ($s->rowCount() === 1) {
                $ada = $db->prepare('SELECT no FROM sinkronisasi_endpoint WHERE id_sinkron = CAST(:id AS uuid)');
                $ada->execute(['id' => $id]);
                $sudah = array_map('intval', $ada->fetchAll(\PDO::FETCH_COLUMN));
                $tambah = $db->prepare(Kueri::SINKRON_EP_TAMBAH);
                foreach (self::ENDPOINT as $no => [$nama, $path]) {
                    if (! in_array($no, $sudah, true)) {
                        $tambah->execute(['id' => $id, 'no' => $no, 'nama' => $nama, 'path' => $path, 'status' => 'dilewati', 'detik' => null,
                            'rekord' => 0, 'ket' => 'Dilewati karena proses berhenti']);
                    }
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ------------------------------------------------------------ menjalankan

    /** Tidak pernah melempar: apa pun yang terjadi, baris sinkronisasi ditutup dengan status berhasil atau gagal. */
    public function jalankan(string $id, string $smt): void
    {
        $t0 = microtime(true);
        $data = null;
        $this->idSinkron = $id;
        try {
            $this->tahap($id, 1, count(self::ENDPOINT));
            $this->log('Login ke SISTER…');
            $this->klien->login();
            $this->tahap($id, 2);
            $data = $this->ambil($id, $smt, $t0);
            $this->tahap($id, 3);
            $this->tahap($id, 4);
            $this->log('Semua data diterima (' . $this->klien->panggilan . ' request). Menyimpan ke database…');
            $catatan = $this->simpan($smt, $data);
            foreach ($catatan as $no => $ket) {
                $this->q(Kueri::SINKRON_EP_KET, ['id' => $id, 'no' => $no, 'ket' => $ket]);
            }
            $this->selesai($id, 'berhasil', null, $t0);
            $this->log('SELESAI: berhasil dalam ' . round(microtime(true) - $t0) . ' detik.');
        } catch (\Throwable $e) {
            $pesan = $e instanceof SisterGalat ? $e->getMessage() : 'Gagal menyimpan atau memproses data: ' . mb_substr($e->getMessage(), 0, 300);
            $this->log('GAGAL: ' . $pesan);
            try {
                $this->gagalkan($id, $pesan, $t0);
            } catch (\Throwable $e2) {
                // Terakhir: biarkan penutup macet (tutupMacet) yang membereskan.
            }
        }
    }

    /** Catatan kemajuan untuk manusia. Penerimanya diberikan pemanggil lewat $opsi['log'] (command: layar + storage/logs/sinkron.log). */
    private function log(string $pesan): void
    {
        $this->denyut();
        if (is_callable($this->opsi['log'] ?? null)) {
            ($this->opsi['log'])($pesan);
        }
    }

    /** Tanda hidup ke database (paling sering tiap 10 detik): halaman memakainya untuk membedakan proses yang lama dari yang mati. */
    private function denyut(): void
    {
        $n = microtime(true);
        if ($this->idSinkron === null || $n - $this->denyutTerakhir < 10) {
            return;
        }
        $this->denyutTerakhir = $n;
        try {
            $this->q(Kueri::SINKRON_DENYUT, ['id' => $this->idSinkron]);
        } catch (\Throwable $e) {
            // denyut gagal tidak boleh menghentikan sinkronisasi
        }
    }

    private function q(string $sql, array $b = []): \PDOStatement
    {
        $s = $this->stmt[$sql] ??= $this->db->prepare($sql);
        $s->execute($b);
        return $s;
    }

    private function tahap(string $id, int $tahap, ?int $total = null): void
    {
        $this->q(Kueri::SINKRON_TAHAP, ['tahap' => $tahap, 'total' => $total, 'id' => $id]);
    }

    private function selesai(string $id, string $status, ?string $pesan, float $t0): void
    {
        $this->q(Kueri::SINKRON_SELESAI, ['status' => $status, 'durasi' => max(1, (int) round(microtime(true) - $t0)), 'pesan' => $pesan, 'id' => $id]);
    }

    private function gagalkan(string $id, string $pesan, float $t0): void
    {
        $sudah = array_map('intval', $this->q('SELECT no FROM sinkronisasi_endpoint WHERE id_sinkron = CAST(:id AS uuid)', ['id' => $id])->fetchAll(\PDO::FETCH_COLUMN));
        $sedangDiambil = $this->noSekarang > 0 && ! in_array($this->noSekarang, $sudah, true);
        foreach (self::ENDPOINT as $no => [$nama, $path]) {
            if (in_array($no, $sudah, true)) {
                continue;
            }
            $st = $sedangDiambil && $no === $this->noSekarang ? 'gagal' : 'dilewati';
            $this->q(Kueri::SINKRON_EP_TAMBAH, ['id' => $id, 'no' => $no, 'nama' => $nama, 'path' => $path, 'status' => $st,
                'detik' => null, 'rekord' => 0, 'ket' => $st === 'gagal' ? $pesan : 'Dilewati karena proses berhenti']);
        }
        // Penyimpanan atomik: pada kegagalan di tahap mana pun, tidak ada data sumber yang berubah.
        $this->selesai($id, 'gagal', 'Sinkronisasi berhenti' . ($sedangDiambil ? ' di endpoint ' . $this->noSekarang . ' (' . self::ENDPOINT[$this->noSekarang][0] . ')' : '')
            . '. ' . $pesan . ' Tidak ada data yang tersimpan.', $t0);
    }

    // ------------------------------------------------------------ mengambil data

    /** Satu endpoint selesai: tulis barisnya dan nama atributnya (diambil dari kunci rekord pertama). */
    private function catatEndpoint(string $id, int $no, float $detik, int $rekord, ?array $contoh, ?string $ket): void
    {
        [$nama, $path] = self::ENDPOINT[$no];
        $this->q(Kueri::SINKRON_EP_TAMBAH, ['id' => $id, 'no' => $no, 'nama' => $nama, 'path' => $path, 'status' => 'berhasil',
            'detik' => round($detik, 1), 'rekord' => $rekord, 'ket' => $ket]);
        $urut = 0;
        foreach (array_keys($contoh ?? []) as $k) {
            if (is_string($k)) {
                $this->q(Kueri::SINKRON_ATR_TAMBAH, ['id' => $id, 'no' => $no, 'nama' => $k, 'urut' => $urut++, 'status' => 'terambil']);
            }
        }
    }

    /** Menjalankan $kerja satu kali per endpoint, mencatat waktu dan item yang dilewati. $kerja mengembalikan [rekord, contoh]. */
    private function langkah(string $id, int $no, float $t0, callable $kerja): void
    {
        $this->noSekarang = $no;
        // Tidak ada batas total waktu: sinkronisasi yang masih mengirim denyut boleh berjalan selama diperlukan.
        $path = self::ENDPOINT[$no][1];
        $dilewatiAwal = $this->klien->dilewati[$path] ?? 0;
        $tolakAwal = $this->klien->ditolak[$path] ?? [];
        $mulai = microtime(true);
        $this->log(sprintf('Endpoint %d/%d %s: mulai', $no, count(self::ENDPOINT), self::ENDPOINT[$no][0]));
        [$rekord, $contoh, $tambahan] = $kerja() + [2 => null];
        $this->log(sprintf('Endpoint %d/%d %s: selesai, %d rekord, %.1f detik', $no, count(self::ENDPOINT), self::ENDPOINT[$no][0], $rekord, microtime(true) - $mulai));
        $lewat = ($this->klien->dilewati[$path] ?? 0) - $dilewatiAwal;
        $this->catatEndpoint($id, $no, microtime(true) - $mulai, $rekord, $contoh,
            implode('; ', array_filter([$tambahan, $lewat > 0 ? "$lewat item dilewati karena SISTER menolak (" . $this->ringkasTolak($path, $tolakAwal) . ')' : null])) ?: null);
        $this->noSekarang = 0;
    }

    /** "HTTP 404 x5, contoh pesan: ..." untuk penolakan 4xx sejak $awal. */
    private function ringkasTolak(string $path, array $awal): string
    {
        $h = [];
        foreach ($this->klien->ditolak[$path] ?? [] as $kode => $x) {
            $n = $x['n'] - ($awal[$kode]['n'] ?? 0);
            if ($n > 0) {
                $h[] = "HTTP $kode x$n" . (($x['contoh'] ?? '') !== '' ? ' "' . $x['contoh'] . '"' : '');
            }
        }
        return implode('; ', $h);
    }

    private function ambil(string $id, string $smt, float $t0): array
    {
        $k = $this->klien;
        $d = ['jab' => [], 'dosen' => [], 'ajar' => [], 'ajarDetail' => [], 'bkd' => [], 'bkdAkhir' => [], 'bimbingan' => [], 'bimbDetail' => [], 'uji' => [], 'ujiDetail' => [], 'tunjang' => [], 'tugas' => [], 'jabDos' => []];

        $this->langkah($id, 1, $t0, function () use ($k, &$d) {
            $d['jab'] = SisterKlien::daftar($k->get('/referensi/jabatan_fungsional'));
            return [count($d['jab']), $d['jab'][0] ?? null];
        });
        $this->langkah($id, 2, $t0, function () use ($k, &$d) {
            $semua = SisterKlien::daftar($k->get('/referensi/sdm'));
            // Biasanya hanya dosen Aktif. Untuk menarik semester lama, dosen yang kini non-aktif juga perlu ikut (opsi semua_status).
            $semuaStatus = ! empty($this->opsi['semua_status']);
            $dosen = array_values(array_filter($semua, fn ($s) => ($s['jenis_sdm'] ?? '') === 'Dosen' && ($semuaStatus || ($s['nama_status_aktif'] ?? '') === 'Aktif') && ! empty($s['id_sdm'])));
            if (! empty($this->opsi['id_sdm'])) {
                $dosen = array_values(array_filter($dosen, fn ($s) => in_array($s['id_sdm'], $this->opsi['id_sdm'], true)));
            }
            if (($this->opsi['maks'] ?? 0) > 0) {
                $dosen = array_slice($dosen, 0, (int) $this->opsi['maks']);
            }
            $d['dosen'] = $dosen;
            // Rincian seluruh pegawai SISTER, supaya jelas mengapa jumlah dosen yang masuk lebih kecil dari jumlah pegawai.
            $rinci = [];
            foreach ($semua as $s) {
                $kunci = trim((string) ($s['jenis_sdm'] ?? '(tanpa jenis)')) . ' ' . trim((string) ($s['nama_status_aktif'] ?? '(tanpa status)'));
                $rinci[$kunci] = ($rinci[$kunci] ?? 0) + 1;
            }
            arsort($rinci);
            $ket = count($semua) . ' pegawai di SISTER: ' . implode(', ', array_map(fn ($k, $n) => "$k $n", array_keys($rinci), $rinci))
                . '. Yang diproses: ' . (empty($this->opsi['semua_status']) ? 'jenis Dosen berstatus Aktif' : 'semua dosen (aktif dan non-aktif)') . ', ' . count($dosen) . '.';
            return [count($dosen), $semua[0] ?? null, $ket];
        });

        $perDosen = function (int $no, string $path, array $param, string $kunci) use ($id, $t0, $k, &$d) {
            $this->langkah($id, $no, $t0, function () use ($path, $param, $kunci, $k, &$d) {
                $n = 0;
                $contoh = null;
                $i = 0;
                $total = count($d['dosen']);
                foreach ($d['dosen'] as $s) {
                    if ($i % 25 === 0 && $i > 0) {
                        $this->log(sprintf('  %s: dosen %d/%d', $path, $i, $total));
                    }
                    $i++;
                    $baris = SisterKlien::daftar($k->get($path, ['id_sdm' => $s['id_sdm']] + $param));
                    $d[$kunci][$s['id_sdm']] = $baris;
                    $n += count($baris);
                    $contoh ??= $baris[0] ?? null;
                }
                return [$n, $contoh];
            });
        };

        $perDosen(3, '/pengajaran', ['id_semester' => $smt], 'ajar');
        $this->langkah($id, 4, $t0, function () use ($k, &$d) {
            $n = 0;
            $contoh = null;
            $i = 0;
            $total = array_sum(array_map('count', $d['ajar']));
            foreach ($d['ajar'] as $daftar) {
                foreach ($daftar as $p) {
                    if (empty($p['id'])) {
                        continue;
                    }
                    if ((++$i) % 25 === 0) {
                        $this->log(sprintf('  /pengajaran/{id}: kelas %d/%d', $i, $total));
                    }
                    $det = $k->get('/pengajaran/' . $p['id']);
                    if ($det !== null) {
                        $d['ajarDetail'][$p['id']] = $det;
                        $n++;
                        $contoh ??= $det;
                    }
                }
            }
            return [$n, $contoh];
        });
        $perDosen(5, '/bkd/ajar', ['id_smt' => $smt], 'bkd');
        $perDosen(6, '/bkd/laporan_akhir_bkd', [], 'bkdAkhir');
        $perDosen(7, '/bimbingan_mahasiswa', ['id_semester' => $smt], 'bimbingan');
        $perDosen(8, '/pengujian_mahasiswa', ['id_semester' => $smt], 'uji');
        $this->langkah($id, 9, $t0, function () use ($k, &$d) {
            $n = 0;
            $contoh = null;
            $i = 0;
            // Jumlah UNIK (satu pengujian bisa muncul di beberapa dosen dan detailnya diambil sekali), supaya penghitung log tidak berhenti di tengah.
            $idUji = [];
            foreach ($d['uji'] as $daftar) {
                foreach ($daftar as $u) {
                    if (! empty($u['id'])) {
                        $idUji[$u['id']] = true;
                    }
                }
            }
            $total = count($idUji);
            foreach ($d['uji'] as $daftar) {
                foreach ($daftar as $u) {
                    if (empty($u['id']) || isset($d['ujiDetail'][$u['id']])) {
                        continue;
                    }
                    if ((++$i) % 25 === 0) {
                        $this->log(sprintf('  /pengujian_mahasiswa/{id}: %d/%d', $i, $total));
                    }
                    $det = $k->get('/pengujian_mahasiswa/' . $u['id']);
                    if ($det !== null) {
                        $d['ujiDetail'][$u['id']] = $det;
                        $n++;
                        $contoh ??= $det;
                    }
                }
            }
            // Nama mahasiswa bimbingan hanya ada di detailnya; dipakai untuk mencocokkan status "Beban lebih" di BKD.
            $idBimb = [];
            foreach ($d['bimbingan'] as $daftar) {
                foreach ($daftar as $b) {
                    if (! empty($b['id'])) {
                        $idBimb[$b['id']] = true;
                    }
                }
            }
            $totalB = count($idBimb);
            $ib = 0;
            $bKey = '/bimbingan_mahasiswa/{id}';
            $tolakB = $this->klien->ditolak[$bKey] ?? [];
            $this->log("  /bimbingan_mahasiswa/{id}: mulai, $totalB bimbingan unik");
            foreach ($d['bimbingan'] as $daftar) {
                foreach ($daftar as $b) {
                    if (empty($b['id']) || isset($d['bimbDetail'][$b['id']])) {
                        continue;
                    }
                    if ((++$ib) % 25 === 0) {
                        $this->log(sprintf('  /bimbingan_mahasiswa/{id}: %d/%d', $ib, $totalB));
                    }
                    $det = $k->get('/bimbingan_mahasiswa/' . $b['id']);
                    if ($det !== null) {
                        $d['bimbDetail'][$b['id']] = $det;
                    }
                }
            }
            $tB = $this->ringkasTolak($bKey, $tolakB);
            return [$n, $contoh, $totalB . ' detail bimbingan diambil' . ($tB !== '' ? "; ditolak SISTER: $tB" : '')];
        });
        $perDosen(10, '/bkd/tunjang', ['id_smt' => $smt], 'tunjang');
        $perDosen(11, '/tugas_tambahan', [], 'tugas');   // tidak terikat semester: semua tugas tambahan dosen
        $perDosen(12, '/jabatan_fungsional', [], 'jabDos');   // riwayat jabatan fungsional per dosen
        return $d;
    }

    // ------------------------------------------------------------ menyimpan

    /** Id semester SISTER berurutan dari $dari sampai $sampai, mis. 20251..20262 -> [20251, 20252, 20261, 20262]. Format: tahun + 1 (Ganjil) atau 2 (Genap). */
    /**
     * Status BKD ("Beban lebih", "Selesai", ...) untuk kegiatan bimbingan/pengujian. SISTER hanya menyimpan statusnya di /bkd/ajar,
     * yang judulnya memuat nama mahasiswa ("Membimbing Skripsi (pembimbing utama) NAMA"). Dicocokkan lewat kategori (bila ada),
     * kata kunci (bila ada), dan nama mahasiswa. Tidak ada yang cocok, atau status yang cocok berbeda-beda: NULL (tidak ditebak).
     */
    public static function statusBkd(array $bkd, ?string $kategori, array $namaMhs, ?string $kata, bool $ringkasan = false): ?string
    {
        $norm = fn ($x) => mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $x)));
        $nama = array_filter(array_map($norm, $namaMhs), fn ($x) => $x !== '');
        $stat = [];
        foreach ($bkd as $b) {
            if (($b['unsur'] ?? 'ajar') !== 'ajar' || empty($b['stat_kegiatan']) || ! $nama) {
                continue;
            }
            $judul = $norm($b['judul_keg'] ?? '');
            if ($kategori !== null && $kategori !== '' && $norm($b['nm_kat'] ?? '') !== $norm($kategori)) {
                continue;
            }
            if ($kata !== null && ! preg_match('/' . $kata . '/iu', $judul)) {
                continue;
            }
            foreach ($nama as $n) {
                if (str_contains($judul, $n)) {
                    $stat[(string) $b['stat_kegiatan']] = true;
                    break;
                }
            }
        }
        if (! $stat && $ringkasan && $kategori !== null && $kategori !== '') {
            // Bimbingan akademik: SISTER hanya memberi satu baris BKD per kategori, judulnya sama dengan nm_kat (tanpa nama mahasiswa).
            // Dicocokkan lewat kategori yang sama persis; bila statusnya berbeda-beda: NULL (tidak ditebak).
            foreach ($bkd as $b) {
                if (($b['unsur'] ?? 'ajar') === 'ajar' && ! empty($b['stat_kegiatan']) && $norm($b['nm_kat'] ?? '') === $norm($kategori) && $norm($b['judul_keg'] ?? '') === $norm($kategori)) {
                    $stat[(string) $b['stat_kegiatan']] = true;
                }
            }
        }
        return count($stat) === 1 ? (string) array_key_first($stat) : null;
    }

    public static function daftarSemester(string $dari, string $sampai): array
    {
        foreach ([$dari, $sampai] as $x) {
            if (! preg_match('/^(19|20)\d{2}[12]$/', $x)) {
                throw new \InvalidArgumentException("Id semester tidak sah: $x (contoh yang benar: 20251 atau 20252).");
            }
        }
        if (strcmp($dari, $sampai) > 0) {
            throw new \InvalidArgumentException("Semester awal ($dari) lebih besar dari semester akhir ($sampai).");
        }
        $hasil = [];
        for ($y = (int) substr($dari, 0, 4); $y <= (int) substr($sampai, 0, 4); $y++) {
            foreach ([1, 2] as $n) {
                $id = $y . $n;
                if (strcmp($id, $dari) >= 0 && strcmp($id, $sampai) <= 0) {
                    $hasil[] = $id;
                }
            }
        }
        return $hasil;
    }

    /** Label periode dari id semester, mengikuti data yang ada: 20252 = "Genap 2025/2026", 20261 = "Ganjil 2026/2027". @return array{label:string,semester:string,tahun_ajaran:string} */
    public static function labelPeriode(string $id): array
    {
        $y = (int) substr($id, 0, 4);
        $semester = substr($id, 4, 1) === '1' ? 'Ganjil' : 'Genap';
        $ta = $y . '/' . ($y + 1);
        return ['label' => "$semester $ta", 'semester' => $semester, 'tahun_ajaran' => $ta];
    }

    /** UUID deterministik dari teks, supaya sinkronisasi ulang menghasilkan id yang sama untuk data yang tidak punya id di SISTER. */
    public static function uuidDari(string $teks): string
    {
        $h = md5($teks);
        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
    }

    /** Kelas Kerja Sama menurut tabel pola_kelas_kerja_sama (akhiran, awalan, mengandung). Tanpa pola = Reguler. */
    /**
     * Ringkasan detail bimbingan/pengujian dari SISTER untuk rincian kegiatan. Mahasiswa yang sama dihitung SATU kali:
     * SISTER dapat mengembalikan baris mahasiswa yang berulang (id_anggota_mahasiswa sama), dan jumlah ini menjadi pengali honor.
     * @return array{jml:int, detail:?array}
     */
    public static function ringkasDetail(?array $det, string $dosenId): array
    {
        if (empty($det)) {
            return ['jml' => 1, 'detail' => null];
        }
        $mhs = [];
        foreach ($det['mahasiswa'] ?? [] as $m) {
            $kunci = (string) ($m['id_anggota_mahasiswa'] ?? '') ?: ((string) ($m['id_registrasi_mahasiswa'] ?? '') ?: ((string) ($m['nomor_induk'] ?? '') ?: mb_strtoupper(trim((string) ($m['nama'] ?? '')))));
            if ($kunci === '' || isset($mhs[$kunci])) {
                continue;
            }
            $mhs[$kunci] = ['nama' => self::teks($m['nama'] ?? null), 'nim' => self::teks($m['nomor_induk'] ?? null), 'peran' => self::teks($m['peran'] ?? null)];
        }
        $pemb = [];
        foreach ($det['dosen'] ?? [] as $x) {
            $pemb[] = ['nama' => self::teks($x['nama'] ?? null), 'peran' => self::teks($x['kategori_kegiatan'] ?? null),
                'urutan' => isset($x['urutan']) ? (int) $x['urutan'] : null, 'saya' => ($x['id_sdm'] ?? '') === $dosenId];
        }
        usort($pemb, fn ($a, $b) => ($a['urutan'] ?? 99) <=> ($b['urutan'] ?? 99));
        $ya = fn ($v) => $v === null ? null : (($v === true || $v === 1 || $v === '1') ? 'Ya' : 'Tidak');
        return ['jml' => max(1, count($mhs)), 'detail' => [
            'tglMulai' => self::teks($det['tanggal_mulai'] ?? null), 'tglSelesai' => self::teks($det['tanggal_selesai'] ?? null),
            'sk' => self::teks($det['sk_penugasan'] ?? null), 'tglSk' => self::teks($det['tanggal_sk_penugasan'] ?? null),
            'keterangan' => self::teks($det['keterangan'] ?? null), 'komunal' => $ya($det['komunal'] ?? null), 'flagship' => $ya($det['flagship'] ?? null),
            'mahasiswa' => array_values($mhs), 'pembimbing' => $pemb,
        ]];
    }

    public static function jenisKelas(string $kelas, array $pola): string
    {
        $k = mb_strtoupper(trim($kelas));
        foreach ($pola as $p) {
            $x = mb_strtoupper(trim((string) $p->pola));
            if ($x === '') {
                continue;
            }
            $cocok = match ($p->tipe) {
                'akhiran' => str_ends_with($k, $x),
                'awalan' => str_starts_with($k, $x),
                default => str_contains($k, $x),
            };
            if ($cocok) {
                return 'Kerja Sama';
            }
        }
        return 'Reguler';
    }

    private static function angka($v): ?string
    {
        return ($v === null || $v === '' || ! is_numeric($v)) ? null : (string) $v;
    }

    private static function teks($v): ?string
    {
        return ($v === null || $v === '') ? null : (string) $v;
    }

    /**
     * Semua penulisan database dalam satu transaksi. Mengembalikan catatan tambahan per nomor endpoint (untuk kolom keterangan log).
     * @return array<int,string>
     */
    private function simpan(string $smt, array $d): array
    {
        $catat = [];
        $pola = $this->q(Kueri::SY_POLA)->fetchAll(\PDO::FETCH_OBJ);
        $this->db->beginTransaction();
        try {
            foreach ($d['jab'] as $j) {
                if (isset($j['id'], $j['nama'])) {
                    $this->q(Kueri::SY_JABFUNG, ['id' => (string) $j['id'], 'nama' => (string) $j['nama']]);
                }
            }
            // Jabatan fungsional dosen = BKD akhir terbaru (semester mana pun) yang berisi id_jabfung. SISTER tidak memberi jabatan langsung di /referensi/sdm.
            $jabDosen = [];
            foreach ($d['bkdAkhir'] as $idSdm => $baris) {
                usort($baris, fn ($a, $b) => strcmp((string) ($b['id_smt'] ?? ''), (string) ($a['id_smt'] ?? '')));
                foreach ($baris as $b) {
                    if (! empty($b['id_jabfung'])) {
                        $jabDosen[$idSdm] = (string) $b['id_jabfung'];
                        break;
                    }
                }
            }
            // Dosen yang BKD akhirnya tidak memuat id_jabfung: pakai riwayat jabatan fungsional terbaru dari /jabatan_fungsional (dicocokkan lewat nama).
            $idJabNama = [];
            foreach ($d['jab'] as $j) {
                if (isset($j['id'], $j['nama'])) {
                    $idJabNama[(string) HitungHonor::jabfungDasar((string) $j['nama'])] = (string) $j['id'];
                }
            }
            $jabDariRiwayat = 0;
            foreach ($d['dosen'] as $s) {
                if (isset($jabDosen[$s['id_sdm']])) {
                    continue;
                }
                $riw = $d['jabDos'][$s['id_sdm']] ?? [];
                usort($riw, fn ($a, $b) => strcmp((string) ($b['tanggal_mulai'] ?? ''), (string) ($a['tanggal_mulai'] ?? '')));
                foreach ($riw as $r) {
                    $idj = $idJabNama[(string) HitungHonor::jabfungDasar((string) ($r['jabatan_fungsional'] ?? ''))] ?? null;
                    if ($idj !== null) {
                        $jabDosen[$s['id_sdm']] = $idj;
                        $jabDariRiwayat++;
                        break;
                    }
                }
            }
            if ($jabDariRiwayat > 0) {
                $catat[12] = "$jabDariRiwayat dosen dilengkapi jabatan fungsionalnya dari riwayat (BKD akhir tidak memuat id_jabfung)";
            }
            foreach ($d['dosen'] as $s) {
                $this->q(Kueri::SY_DOSEN, ['id' => $s['id_sdm'], 'nama' => (string) ($s['nama_sdm'] ?? ''), 'nidn' => self::teks($s['nidn'] ?? null),
                    'nip' => self::teks($s['nip'] ?? null), 'nuptk' => self::teks($s['nuptk'] ?? null), 'jenis' => (string) ($s['jenis_sdm'] ?? 'Dosen'),
                    'aktif' => (string) ($s['nama_status_aktif'] ?? 'Aktif'), 'pegawai' => self::teks($s['nama_status_pegawai'] ?? null),
                    'jab' => $jabDosen[$s['id_sdm']] ?? null, 'waktu' => self::teks($s['waktu_data_update'] ?? null)]);
            }

            // Tugas tambahan (menentukan SKS wajib): diganti seluruhnya per dosen. Jenis yang belum ada di pemetaan_tugas_tambahan dilaporkan, tidak ditebak.
            $peta = array_flip(array_map('strval', $this->q(Kueri::SY_PETA)->fetchAll(\PDO::FETCH_COLUMN)));
            $belumPeta = [];
            foreach ($d['dosen'] as $s) {
                $this->q(Kueri::SY_TUGAS_HAPUS, ['dosen' => $s['id_sdm']]);
                foreach ($d['tugas'][$s['id_sdm']] ?? [] as $t) {
                    if (empty($t['jenis_tugas'])) {
                        continue;
                    }
                    $this->q(Kueri::SY_TUGAS_TAMBAH, ['id' => ! empty($t['id']) ? $t['id'] : self::uuidDari('tugas|' . $s['id_sdm'] . '|' . $t['jenis_tugas'] . '|' . ($t['tanggal_mulai_tugas'] ?? '')),
                        'dosen' => $s['id_sdm'], 'jenis' => (string) $t['jenis_tugas'], 'unit' => self::teks($t['unit_kerja'] ?? null), 'kat' => self::angka($t['id_kategori_kegiatan'] ?? null),
                        'mulai' => self::teks($t['tanggal_mulai_tugas'] ?? null), 'selesai' => self::teks($t['tanggal_selesai_tugas'] ?? null)]);
                    if (! isset($peta[mb_strtolower(trim((string) $t['jenis_tugas']))])) {
                        $belumPeta[$t['jenis_tugas']] = ($belumPeta[$t['jenis_tugas']] ?? 0) + 1;
                    }
                }
            }
            if ($belumPeta) {
                $catat[11] = 'jenis tugas tambahan belum dipetakan ke kelompok SKS wajib (isi tabel pemetaan_tugas_tambahan): '
                    . implode(', ', array_map(fn ($k, $n) => "$k ($n dosen)", array_keys($belumPeta), $belumPeta));
            }

            $lewatAjar = 0;
            foreach ($d['dosen'] as $s) {
                $dosen = $s['id_sdm'];
                foreach ($d['ajar'][$dosen] ?? [] as $p) {
                    $det = $d['ajarDetail'][$p['id'] ?? ''] ?? [];
                    $mk = $p['mata_kuliah'] ?? ($det['mata_kuliah'] ?? null);
                    $kelas = $p['kelas'] ?? ($det['kelas'] ?? null);
                    $sks = self::angka($p['sks'] ?? ($det['sks'] ?? null));
                    if (empty($p['id']) || $mk === null || $kelas === null || $sks === null) {
                        $lewatAjar++;
                        continue;
                    }
                    $this->q(Kueri::SY_PENGAJARAN, ['id' => $p['id'], 'dosen' => $dosen, 'smt' => $smt, 'idkelas' => self::teks($det['id_kelas'] ?? null),
                        'kode' => self::teks($det['kode_mata_kuliah'] ?? ($p['kode_mata_kuliah'] ?? null)), 'mk' => $mk, 'kelas' => $kelas,
                        'jenis' => self::jenisKelas($kelas, $pola), 'prodi' => self::teks($det['nama_program_studi'] ?? null),
                        'jenjang' => self::teks($det['jenjang_pendidikan'] ?? null), 'sks' => $sks,
                        'sksmk' => self::angka($det['sks_mata_kuliah'] ?? ($p['sks_mata_kuliah'] ?? null)),
                        'tmr' => self::angka($det['tatap_muka_rencana'] ?? null), 'tmrl' => self::angka($det['tatap_muka_realisasi'] ?? null),
                        'mhs' => self::angka($p['jumlah_mahasiswa'] ?? ($det['jumlah_mahasiswa'] ?? null))]);
                }
                $this->q(Kueri::SY_JURUSAN, ['dosen' => $dosen]);
                // BKD pengajaran: ganti seluruhnya untuk dosen dan semester ini supaya yang sudah dihapus di SISTER ikut hilang.
                $this->q(Kueri::SY_BKD_HAPUS, ['dosen' => $dosen, 'smt' => $smt]);
                $dobel = [];
                foreach ($d['bkd'][$dosen] ?? [] as $b) {
                    $kunci = self::uuidDari(implode('|', [$dosen, $smt, $b['id_katgiat'] ?? '', $b['judul_keg'] ?? '', $b['nm_kat'] ?? '', $b['beban_sks'] ?? '']));
                    $dobel[$kunci] = ($dobel[$kunci] ?? 0) + 1;
                    $idBkd = $dobel[$kunci] > 1 ? self::uuidDari($kunci . '#' . $dobel[$kunci]) : $kunci;
                    $this->q(Kueri::SY_BKD_TAMBAH, ['id' => $idBkd, 'dosen' => $dosen, 'smt' => $smt, 'katgiat' => self::angka($b['id_katgiat'] ?? null),
                        'unsur' => self::teks($b['unsur'] ?? null), 'judul' => self::teks($b['judul_keg'] ?? null), 'kat' => self::teks($b['nm_kat'] ?? null),
                        'beban' => self::angka($b['beban_sks'] ?? null), 'nilai' => self::angka($b['nilai'] ?? null), 'stat' => self::teks($b['stat_kegiatan'] ?? null)]);
                }
                foreach ($d['bkdAkhir'][$dosen] ?? [] as $b) {
                    if ((string) ($b['id_smt'] ?? '') !== $smt) {
                        continue;   // hanya periode yang disinkronkan; periode lain mungkin tidak ada di tabel periode
                    }
                    $this->q(Kueri::SY_BKDAKHIR, ['id' => self::uuidDari("bkdakhir|$dosen|$smt"), 'dosen' => $dosen, 'smt' => $smt,
                        'reg' => self::teks($b['id_reg_ptk'] ?? null), 'kinerja' => self::angka($b['sks_kinerja_ajar'] ?? null),
                        'lebih' => self::angka($b['sks_lebih_ajar'] ?? null), 'jab' => self::teks($b['id_jabfung'] ?? null),
                        'stat' => self::teks($b['stat_kewajiban'] ?? null), 'simpulan' => self::teks($b['simpulan_asesor'] ?? null)]);
                }
            }
            if ($lewatAjar > 0) {
                $catat[3] = "$lewatAjar baris pengajaran tidak disimpan karena id, mata kuliah, kelas, atau SKS kosong";
            }

            $lewatJenis = [];
            $direset = 0;
            foreach ($d['dosen'] as $s) {
                $dosen = $s['id_sdm'];
                $baru = [];   // kunci unik => baris
                $tambah = function (string $sumber, string $idSumber, string $judul, ?string $jenis, string $kategori, ?string $prodi, ?string $pt, ?string $stat, array $rd = ['jml' => 1, 'detail' => null]) use (&$baru, &$lewatJenis) {
                    $jenisOk = self::jenisKegiatan($jenis);
                    if ($jenisOk === 'Lainnya') {
                        // Tidak dibuang: disimpan sebagai 'Lainnya' (belum ada tarif SBM-nya); jenis aslinya ikut di kolom kategori.
                        $asli = trim((string) $jenis) !== '' ? trim((string) $jenis) : '(tanpa jenis)';
                        $lewatJenis[$asli] = ($lewatJenis[$asli] ?? 0) + 1;
                        $kategori = $asli . ' / ' . $kategori;
                    }
                    $baru[$idSumber . '|' . $kategori] = compact('sumber', 'idSumber', 'judul', 'jenisOk', 'kategori', 'prodi', 'pt', 'stat') + ['rd' => $rd];
                };
                foreach ($d['bimbingan'][$dosen] ?? [] as $b) {
                    if (! empty($b['id'])) {
                        $tambah('sister_bimbingan', $b['id'], (string) ($b['judul'] ?? ''), $b['jenis_bimbingan'] ?? null,
                            (string) ($b['nm_kat'] ?? 'Pembimbing'), self::teks($b['program_studi'] ?? null), self::teks($b['nama_perguruan_tinggi'] ?? null),
                            self::statusBkd($d['bkd'][$dosen] ?? [], (string) ($b['nm_kat'] ?? ''), array_column($d['bimbDetail'][$b['id']]['mahasiswa'] ?? [], 'nama'), null, self::jenisKegiatan($b['jenis_bimbingan'] ?? null) === 'Pembimbing Akademik'),
                            self::ringkasDetail($d['bimbDetail'][$b['id']] ?? null, $dosen));
                    }
                }
                foreach ($d['uji'][$dosen] ?? [] as $u) {
                    if (empty($u['id'])) {
                        continue;
                    }
                    $kat = 'Penguji';   // kategori per dosen ada di detail (dosen[].kategori_kegiatan)
                    foreach ($d['ujiDetail'][$u['id']]['dosen'] ?? [] as $x) {
                        if (($x['id_sdm'] ?? '') === $dosen && ! empty($x['kategori_kegiatan'])) {
                            $kat = (string) $x['kategori_kegiatan'];
                        }
                    }
                    $tambah('sister_pengujian', $u['id'], (string) ($u['judul'] ?? ''), $u['jenis_pengujian'] ?? null, $kat,
                        self::teks($u['program_studi'] ?? null), self::teks($u['nama_perguruan_tinggi'] ?? null),
                        self::statusBkd($d['bkd'][$dosen] ?? [], null, array_column($d['ujiDetail'][$u['id']]['mahasiswa'] ?? [], 'nama'), 'menguji|penguji'),
                        self::ringkasDetail($d['ujiDetail'][$u['id']] ?? null, $dosen));
                }
                foreach ($d['tunjang'][$dosen] ?? [] as $t) {
                    // Filter dari catatan: hanya kegiatan penunjang yang judulnya Seminar Proposal. Tidak ada id di /bkd/tunjang, jadi id dibuat dari isinya.
                    if (! preg_match('/seminar\s+proposal/i', (string) ($t['judul_keg'] ?? ''))) {
                        continue;
                    }
                    $tambah('sister_tunjang', self::uuidDari('tunjang|' . ($t['id_katgiat'] ?? '') . '|' . ($t['judul_keg'] ?? '')), (string) $t['judul_keg'],
                        'Seminar Proposal', (string) ($t['nm_kat'] ?? 'Penunjang'), null, null, self::teks($t['stat_kegiatan'] ?? null));
                }
                $tanda = $this->q(Kueri::SY_NP_TANDA, ['dosen' => $dosen, 'smt' => $smt])->fetchColumn();
                $this->q(Kueri::SY_NP_HAPUS, ['dosen' => $dosen, 'smt' => $smt]);
                $urut = (int) $this->q('SELECT coalesce(max(urutan), 0) FROM kegiatan_nonpengajaran WHERE id_dosen = CAST(:d AS uuid) AND id_smt = CAST(:s AS char(5))', ['d' => $dosen, 's' => $smt])->fetchColumn();
                foreach ($baru as $r) {
                    $this->q(Kueri::SY_NP_TAMBAH, ['id' => self::uuidDari("np|$dosen|$smt|{$r['idSumber']}|{$r['kategori']}"), 'dosen' => $dosen, 'smt' => $smt,
                        'urutan' => ++$urut, 'judul' => $r['judul'], 'jenis' => $r['jenisOk'], 'kategori' => $r['kategori'], 'prodi' => $r['prodi'], 'pt' => $r['pt'],
                        'sumber_id' => $r['idSumber'], 'sumber' => $r['sumber'], 'stat' => $r['stat'], 'mhs' => $r['rd']['jml'],
                        'detail' => $r['rd']['detail'] === null ? null : json_encode($r['rd']['detail'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                }
                $tandaBaru = $this->q(Kueri::SY_NP_TANDA, ['dosen' => $dosen, 'smt' => $smt])->fetchColumn();
                if ($tanda !== $tandaBaru) {
                    $direset += $this->q(Kueri::SY_NP_RESET, ['dosen' => $dosen, 'smt' => $smt])->rowCount();
                }
            }
            $ket = [];
            if ($lewatJenis) {
                $ket[] = array_sum($lewatJenis) . ' kegiatan disimpan sebagai "Lainnya" (jenis belum ada di SBM: ' . implode(', ', array_map(fn ($k, $n) => "$k: $n", array_keys($lewatJenis), $lewatJenis)) . ')';
            }
            if ($direset > 0) {
                $ket[] = "$direset verifikasi dikembalikan ke menunggu karena data kegiatan berubah";
            }
            if ($ket) {
                $catat[7] = implode('; ', $ket);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        return $catat;
    }
}
