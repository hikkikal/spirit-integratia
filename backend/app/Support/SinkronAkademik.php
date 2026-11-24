<?php

namespace App\Support;

/**
 * Menarik data akademik (Personalia + Academia + AcademiaV2) untuk SEMUA dosen yang punya pengajaran pada satu periode,
 * lalu menyimpan hasil per dosen ke tabel academia_hasil (dilihat halaman Rincian Dosen tanpa memanggil API lagi).
 * Dijalankan sesudah sinkronisasi SISTER berhasil. Tidak pernah melempar, dan tidak pernah mengubah hasil sinkronisasi SISTER.
 * Penyimpanan atomik: bila gagal di tengah jalan, hasil lama tetap utuh.
 *
 * Alur: NIP -> id_sdm (Personalia) -> kelas dosen (distribusi-sks-dosen) -> id kelas (kelas-kuliah, per prodi) -> jadwal per kelas
 *       (rencana-jadwal, sekali per kelas) -> kalender akademik (AcademiaV2) -> rincian per minggu (Academia::susunMinggu).
 */
final class SinkronAkademik
{
    /** no => [nama, path]; melanjutkan penomoran endpoint SISTER (1-12). */
    public const ENDPOINT = [
        13 => ['Pegawai (Personalia)', '/personalia/public/pegawai'],
        14 => ['Distribusi SKS Dosen (Academia)', '/academia/public/distribusi-sks-dosen'],
        15 => ['Kelas Kuliah (Academia)', '/academia/public/kelas-kuliah'],
        16 => ['Rencana Jadwal (Academia)', '/academia/public/rencana-jadwal'],
        17 => ['Kalender Akademik (AcademiaV2)', '/academiav2/public/kalender-akademik'],
    ];

    private array $catatEp = [];
    private array $stmt = [];

    /** @param callable(string,array):array{status:int,json:mixed} $get */
    public function __construct(private \PDO $db, private $get, private array $opsi = [])
    {
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    private function log(string $m): void
    {
        if (is_callable($this->opsi['log'] ?? null)) {
            ($this->opsi['log'])($m);
        }
    }

    private function q(string $sql, array $b = []): \PDOStatement
    {
        $s = $this->stmt[$sql] ??= $this->db->prepare($sql);
        $s->execute($b);
        return $s;
    }

    private function catat(?string $id, int $no, string $status, ?float $detik, int $rekord, ?string $ket): void
    {
        if ($id === null) {
            return;
        }
        [$nama, $path] = self::ENDPOINT[$no];
        $this->q('INSERT INTO sinkronisasi_endpoint (id_sinkron, no, nama, path, status, detik, rekord, keterangan) VALUES (CAST(:id AS uuid), :no, :nama, :path, :st, :dt, :rk, :ket)
                  ON CONFLICT (id_sinkron, no) DO UPDATE SET status = EXCLUDED.status, detik = EXCLUDED.detik, rekord = EXCLUDED.rekord, keterangan = EXCLUDED.keterangan',
            ['id' => $id, 'no' => $no, 'nama' => $nama, 'path' => $path, 'st' => $status, 'dt' => $detik === null ? null : round($detik, 1), 'rk' => $rekord, 'ket' => $ket]);
    }

    /** Menandai endpoint dari $dari sampai akhir sebagai dilewati. */
    private function lewati(?string $id, int $dari, string $alasan): void
    {
        foreach (array_keys(self::ENDPOINT) as $no) {
            if ($no >= $dari && ! isset($this->catatEp[$no])) {
                $this->catat($id, $no, 'dilewati', null, 0, $alasan);
            }
        }
    }

    /** @return array{ok:bool,dosen:int,tersimpan:int,pesan:?string} */
    public function jalankan(string $smt, ?string $idSinkron = null): array
    {
        $t0 = microtime(true);
        try {
            return $this->kerja($smt, $idSinkron, $t0);
        } catch (AkademikGalat $e) {
            $this->log('Data akademik: GAGAL: ' . $e->getMessage());
            $this->tandaiGagal($idSinkron, $e->getMessage());
            return ['ok' => false, 'dosen' => 0, 'tersimpan' => 0, 'pesan' => $e->getMessage()];
        } catch (\Throwable $e) {
            $p = 'Gagal memproses data akademik: ' . mb_substr($e->getMessage(), 0, 250);
            $this->log('Data akademik: GAGAL: ' . $p);
            $this->tandaiGagal($idSinkron, $p);
            return ['ok' => false, 'dosen' => 0, 'tersimpan' => 0, 'pesan' => $p];
        }
    }

    private function tandaiGagal(?string $id, string $pesan): void
    {
        $ada = $this->catatEp;
        $sekarang = null;
        foreach (array_keys(self::ENDPOINT) as $no) {
            if (! isset($ada[$no])) {
                $sekarang ??= $no;
                $this->catat($id, $no, $no === $sekarang ? 'gagal' : 'dilewati', null, 0, $no === $sekarang ? $pesan : 'Dilewati karena tahap sebelumnya gagal');
            }
        }
    }

    private function selesaiEp(?string $id, int $no, float $mulai, int $rekord, ?string $ket = null): void
    {
        $this->catatEp[$no] = true;
        $this->catat($id, $no, 'berhasil', microtime(true) - $mulai, $rekord, $ket);
        $this->log(sprintf('Data akademik: %s selesai, %d rekord, %.1f detik', self::ENDPOINT[$no][0], $rekord, microtime(true) - $mulai));
    }

    /** Galat 401/403 = kredensial; lainnya = item dilewati. */
    private function periksa(?array $galat): void
    {
        if ($galat === null) {
            return;
        }
        if (in_array($galat['status'], [401, 403], true)) {
            throw new AkademikGalat('Layanan akademik menolak token (HTTP ' . $galat['status'] . ' pada ' . $galat['path'] . '). Pastikan token atau layanan SSO memiliki scope publicacademia.read dan publicpersonalia.read.');
        }
    }

    private function kerja(string $smt, ?string $id, float $t0): array
    {
        $p = $this->q('SELECT label FROM periode WHERE id_smt = :s', ['s' => $smt])->fetch(\PDO::FETCH_ASSOC);
        $label = $p['label'] ?? $smt;
        $dosen = $this->q('SELECT DISTINCT d.id_dosen, d.nip FROM pengajaran p JOIN dosen d ON d.id_dosen = p.id_dosen WHERE p.id_smt = :s ORDER BY d.id_dosen', ['s' => $smt])->fetchAll(\PDO::FETCH_ASSOC);
        $this->log('Data akademik: ' . count($dosen) . " dosen pada periode $label.");
        if ($dosen === []) {
            $this->lewati($id, 13, 'Tidak ada dosen dengan pengajaran pada periode ini');
            return ['ok' => true, 'dosen' => 0, 'tersimpan' => 0, 'pesan' => null];
        }
        $get = $this->get;
        $contoh = [];

        // 13) NIP -> id_sdm
        $m = microtime(true);
        $peg = Academia::ambilSemua($get, '/personalia/public/pegawai', ['kategori_pegawai' => 'pendidik'], 100, 100);
        $this->periksa($peg['galat']);
        if ($peg['galat'] !== null) {
            throw new AkademikGalat('Daftar pegawai gagal diambil (HTTP ' . $peg['galat']['status'] . ').');
        }
        $sdm = [];
        foreach ($peg['baris'] as $b) {
            if (is_array($b) && ! empty($b['id_sdm']) && ! empty($b['nip'])) {
                $sdm[Academia::norm((string) $b['nip'])] = (string) $b['id_sdm'];
            }
        }
        $contoh['personalia'] = array_map(fn ($b) => ['id_sdm' => $b['id_sdm'] ?? null], array_slice($peg['baris'], 0, 2));
        $this->selesaiEp($id, 13, $m, count($peg['baris']));

        // 14) distribusi SKS per dosen
        $m = microtime(true);
        $perDosen = [];
        $tanpaSdm = [];
        $dilewati = 0;
        foreach ($dosen as $d) {
            $idSdm = $sdm[Academia::norm((string) ($d['nip'] ?? ''))] ?? null;
            if (! $idSdm) {
                $tanpaSdm[$d['id_dosen']] = trim((string) ($d['nip'] ?? '')) === '' ? 'Dosen ini belum memiliki NIP di SISTER, sehingga tidak dapat dicocokkan dengan data pegawai.' : 'NIP ' . $d['nip'] . ' tidak ditemukan di data pegawai PNJ.';
                continue;
            }
            $r = Academia::ambilSemua($get, '/academia/public/distribusi-sks-dosen', ['dosen' => $idSdm, 'id_tahun_akademik' => $smt], 100, 20);
            $this->periksa($r['galat']);
            if ($r['galat'] !== null) {
                $dilewati++;
                $tanpaSdm[$d['id_dosen']] = 'Distribusi SKS gagal diambil (HTTP ' . $r['galat']['status'] . ').';
                continue;
            }
            $contoh['distribusi'] ??= $r['contoh'];
            $perDosen[$d['id_dosen']] = ['sdm' => $idSdm, 'kelas' => Academia::kelasDosen($r['baris'])];
        }
        $totalKelas = array_sum(array_map(fn ($x) => count($x['kelas']), $perDosen));
        $this->selesaiEp($id, 14, $m, $totalKelas, implode('; ', array_filter([count($tanpaSdm) ? count($tanpaSdm) . ' dosen tidak dapat diproses (tanpa NIP atau tidak ada di data pegawai)' : null])) ?: null);

        // 15) id kelas dari kelas-kuliah per program studi
        $m = microtime(true);
        $prodi = [];
        foreach ($perDosen as $x) {
            foreach ($x['kelas'] as $k) {
                if ($k['id_prodi'] !== '') {
                    $prodi[$k['id_prodi']] = true;
                }
            }
        }
        $kk = [];
        foreach (array_keys($prodi) as $pr) {
            $r = Academia::ambilSemua($get, '/academia/public/kelas-kuliah', ['id_tahun_akademik' => $smt, 'id_program_studi' => $pr], 100, 60);
            $this->periksa($r['galat']);
            array_push($kk, ...$r['baris']);
            $contoh['kelas_kuliah'] ??= array_slice($r['baris'], 0, 2);
        }
        foreach ($perDosen as $dId => $x) {
            $perDosen[$dId]['kelas'] = Academia::lengkapiIdKelas($x['kelas'], $kk);
        }
        $this->selesaiEp($id, 15, $m, count($kk), count($prodi) . ' program studi');

        // 16) jadwal per kelas (sekali per kelas)
        $m = microtime(true);
        $jadwal = [];
        $gagalJadwal = 0;
        foreach ($perDosen as $x) {
            foreach ($x['kelas'] as $k) {
                if ($k['id_kelas'] === '' || isset($jadwal[$k['id_kelas']])) {
                    continue;
                }
                $r = Academia::ambilSemua($get, '/academia/public/rencana-jadwal', ['id_kelas_kuliah' => $k['id_kelas']], 100, 20);
                $this->periksa($r['galat']);
                if ($r['galat'] !== null) {
                    $gagalJadwal++;
                    continue;
                }
                $jadwal[$k['id_kelas']] = $r['baris'];
                $contoh['jadwal'] ??= $r['contoh'];
            }
        }
        $this->selesaiEp($id, 16, $m, array_sum(array_map('count', $jadwal)), $gagalJadwal > 0 ? "$gagalJadwal kelas dilewati karena jadwalnya tidak dapat diambil" : count($jadwal) . ' kelas');

        // 17) kalender akademik
        $m = microtime(true);
        $kalender = [];
        $catatanKal = null;
        $ta = Academia::ambilSemua($get, '/academiav2/public/tahun-ajaran', ['tahun' => (int) substr($smt, 0, 4)], 50, 3);
        $this->periksa($ta['galat']);
        $idTa = Academia::cariTahunAjaran($ta['baris'], $smt);
        $contoh['tahun_ajaran'] = ['jumlah' => count($ta['baris']), 'contoh' => array_slice($ta['baris'], 0, 3)];
        if ($idTa) {
            $kl = Academia::ambilSemua($get, '/academiav2/public/kalender-akademik', ['id_tahun_ajaran' => $idTa], 50, 10);
            $this->periksa($kl['galat']);
            $kalender = Academia::kalender($kl['baris']);
            $contoh['kalender'] = $kl['contoh'];
        } else {
            $catatanKal = 'Tahun ajaran ' . $smt . ' tidak ditemukan di data kalender akademik; nomor minggu dihitung dari tanggal pertemuan';
        }
        $this->selesaiEp($id, 17, $m, count($kalender), $catatanKal);

        // Simpan semua sekaligus (atomik)
        $diambil = date('c');
        $simpan = 0;
        $this->db->beginTransaction();
        try {
            $first = true;
            foreach ($dosen as $d) {
                $dId = $d['id_dosen'];
                if (isset($perDosen[$dId])) {
                    $x = $perDosen[$dId];
                    $susun = Academia::susunMinggu($x['kelas'], $jadwal, $x['sdm'], $kalender);
                    $cat = $susun['catatan'];
                    if ($x['kelas'] === []) {
                        $cat[] = 'Data akademik tidak memuat distribusi SKS untuk dosen ini pada periode ' . $label . '.';
                    } elseif (array_filter($x['kelas'], fn ($k) => $k['id_kelas'] === '')) {
                        $cat[] = 'Sebagian kelas tidak ditemukan padanannya di data kelas akademik (prodi, mata kuliah, nama kelas), sehingga jadwal pertemuannya tidak dapat diambil.';
                    }
                    if ($catatanKal) {
                        $cat[] = $catatanKal . '.';
                    }
                    $hasil = ['diambil' => $diambil, 'periode' => $label, 'minggu' => $susun['minggu'], 'kelas' => $susun['kelas'], 'catatan' => $cat, 'contoh' => $first ? $contoh : null];
                } else {
                    $hasil = ['diambil' => $diambil, 'periode' => $label, 'minggu' => [], 'kelas' => [], 'catatan' => [$tanpaSdm[$dId] ?? 'Data akademik tidak tersedia untuk dosen ini.'], 'contoh' => $first ? $contoh : null];
                }
                $first = false;
                $this->q('INSERT INTO academia_hasil (id_dosen, id_smt, data, diambil) VALUES (CAST(:d AS uuid), :s, CAST(:j AS jsonb), now())
                          ON CONFLICT (id_dosen, id_smt) DO UPDATE SET data = EXCLUDED.data, diambil = now()', ['d' => $dId, 's' => $smt, 'j' => json_encode($hasil)]);
                $simpan++;
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->log(sprintf('Data akademik: %d dosen tersimpan dalam %d detik.', $simpan, round(microtime(true) - $t0)));
        return ['ok' => true, 'dosen' => count($dosen), 'tersimpan' => $simpan, 'pesan' => null];
    }
}
