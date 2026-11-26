<?php

namespace App\Support;

/**
 * Data perkuliahan dari Open API Academia PNJ: pengambilan berhalaman, pemetaan kolom, rincian per minggu,
 * dan pencocokan dengan data SISTER (tabel pengajaran). PHP murni (tanpa Laravel) supaya bisa diuji sendiri.
 *
 * Dokumen OpenAPI Academia belum menjelaskan bentuk respons, jadi nama kolom dicari lewat daftar KANDIDAT
 * (tanpa membedakan huruf besar/kecil). Bila ada kolom yang tidak dikenali, hasilnya diberi catatan jelas
 * (bukan ditebak), dan contoh respons mentah bisa dilihat untuk menambah kandidat di sini.
 */
final class Academia
{
    public const KANDIDAT = [
        'id_kelas' => ['id_kelas_kuliah', 'id_kelas', 'kelas_kuliah_id', 'id_kls', 'id_kelas_perkuliahan'],
        'kode_mk' => ['kode_mata_kuliah', 'kode_matkul', 'kode_mk', 'kode_matakuliah', 'kd_mk', 'kode'],
        'nama_mk' => ['nama_matkul', 'nama_mata_kuliah', 'nama_matkul', 'nama_mk', 'mata_kuliah', 'matkul', 'nm_mk', 'nama_matakuliah'],
        'kelas' => ['nama_kelas_kuliah', 'nama_kelas', 'kelas', 'nm_kls', 'nama_kls'],
        'sks' => ['sks_dosen', 'sks_distribusi', 'distribusi_sks', 'sks', 'jumlah_sks', 'sks_substansi', 'bobot_sks'],
        'pertemuan_rencana' => ['jumlah_pertemuan_rencana', 'jumlah_rencana_pertemuan', 'rencana_pertemuan', 'jumlah_pertemuan', 'pertemuan_rencana', 'total_pertemuan'],
        'pertemuan_realisasi' => ['jumlah_pertemuan_realisasi', 'jumlah_realisasi_pertemuan', 'realisasi_pertemuan', 'pertemuan_realisasi', 'jumlah_realisasi'],
        'pertemuan_ke' => ['pertemuan_ke', 'no_pertemuan', 'pertemuan', 'urutan'],
        'minggu' => ['minggu_ke', 'pekan_ke', 'minggu', 'pekan'],
        'tanggal' => ['tanggal', 'tanggal_pertemuan', 'tanggal_rencana', 'tgl_rencana', 'tgl_pertemuan', 'tanggal_kuliah', 'tgl'],
        'realisasi_tanggal' => ['tanggal_realisasi', 'tgl_realisasi', 'realisasi_tanggal', 'tanggal_terlaksana'],
        'realisasi' => ['realisasi', 'is_realisasi', 'status_realisasi', 'terealisasi', 'terlaksana', 'sudah_terlaksana', 'status_pertemuan'],
        'id_matkul' => ['id_matkul', 'id_mata_kuliah'],
        'id_prodi' => ['id_program_studi', 'id_prodi'],
        'wm' => ['wm', 'waktu_mengajar'],
        'lebih100' => ['kelebihan_sks_100', 'sks_100'],
        'lebih50' => ['kelebihan_sks_50', 'sks_50'],
        'lebih0' => ['kelebihan_tidak_dibayar', 'kelebihan_sks_0', 'sks_0'],
        'prodi' => ['nama_program_studi', 'program_studi'],
        'jenis_kelas' => ['nama_jenis_kelas', 'jenis_kelas'],
        'jml_dosen' => ['jumlah_dosen'],
        'dosen' => ['id_dosen', 'id_sdm', 'dosen_id', 'id_dosen_pengajar', 'dosen'],
    ];

    private const YA = ['1', 'true', 'ya', 'y', 'yes', 'terlaksana', 'terealisasi', 'selesai', 'sudah', 'done', 'hadir', 'berlangsung'];

    // ---------- Respons ----------

    /** Mengambil daftar baris dari bentuk respons yang lazim: [..], {data:[..]}, {data:{items:[..]}}, {result:{rows:[..]}}. */
    public static function daftar($json): array
    {
        if (! is_array($json)) {
            return [];
        }
        if (self::adalahDaftar($json)) {
            return $json;
        }
        foreach (['data', 'items', 'rows', 'result', 'results', 'content', 'records'] as $k) {
            if (array_key_exists($k, $json)) {
                $d = self::daftar($json[$k]);
                if ($d !== [] || (is_array($json[$k]) && self::adalahDaftar($json[$k]))) {
                    return $d;
                }
            }
        }
        return [];
    }

    private static function adalahDaftar(array $a): bool
    {
        return $a === [] || (array_is_list($a) && is_array($a[0] ?? null));
    }

    /** Jumlah halaman dari metadata bila ada (total_pages/last_page/...), selain itu null. */
    public static function totalHalaman($json): ?int
    {
        if (! is_array($json)) {
            return null;
        }
        foreach (['meta', 'pagination', 'paging', 'page_info'] as $w) {
            $m = $json[$w] ?? null;
            if (is_array($m)) {
                foreach (['total_pages', 'totalPages', 'last_page', 'lastPage', 'pages'] as $k) {
                    if (isset($m[$k]) && is_numeric($m[$k])) {
                        return (int) $m[$k];
                    }
                }
            }
        }
        foreach (['total_pages', 'totalPages', 'last_page', 'lastPage'] as $k) {
            if (isset($json[$k]) && is_numeric($json[$k])) {
                return (int) $json[$k];
            }
        }
        return null;
    }

    /**
     * Mengambil semua halaman sebuah endpoint.
     * @param callable(string,array):array{status:int,json:mixed} $get
     * @return array{baris:array, contoh:?array, galat:?array}
     */
    public static function ambilSemua(callable $get, string $path, array $query, int $limit = 100, int $maksHalaman = 60): array
    {
        $semua = [];
        $contoh = null;
        $sidik = null;
        for ($h = 1; $h <= $maksHalaman; $h++) {
            $r = $get($path, $query + ['page' => $h, 'limit' => $limit]);
            if ($r['status'] < 200 || $r['status'] >= 300) {
                return ['baris' => $semua, 'contoh' => $contoh, 'galat' => ['status' => $r['status'], 'path' => $path, 'halaman' => $h]];
            }
            $baris = self::daftar($r['json']);
            if ($h === 1) {
                $contoh = ['bentuk' => is_array($r['json']) ? array_keys($r['json']) : gettype($r['json']), 'baris' => array_slice($baris, 0, 2)];
            }
            if ($baris === []) {
                break;
            }
            $s = md5(json_encode($baris[0]));
            if ($s === $sidik) {
                break; // API mengabaikan parameter page: jangan berulang tanpa akhir
            }
            $sidik = $s;
            array_push($semua, ...$baris);
            $total = self::totalHalaman($r['json']);
            if (count($baris) < $limit || ($total !== null && $h >= $total)) {
                break;
            }
        }
        return ['baris' => $semua, 'contoh' => $contoh, 'galat' => null];
    }

    // ---------- Pemetaan kolom ----------

    /** Nilai pertama yang ada dari daftar kandidat; kunci dicocokkan tanpa membedakan huruf besar/kecil. */
    public static function nilai(array $baris, string $logis)
    {
        static $cache = [];
        $kunci = array_change_key_case($baris, CASE_LOWER);
        foreach (self::KANDIDAT[$logis] as $k) {
            if (array_key_exists($k, $kunci) && $kunci[$k] !== null && $kunci[$k] !== '') {
                return $kunci[$k];
            }
        }
        return null;
    }

    public static function adaKolom(array $baris, string $logis): bool
    {
        $kunci = array_change_key_case($baris, CASE_LOWER);
        foreach (self::KANDIDAT[$logis] as $k) {
            if (array_key_exists($k, $kunci)) {
                return true;
            }
        }
        return false;
    }

    private static function angkaAtauNull($v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    /** Memberi id_kelas pada kelas distribusi (yang tak memuatnya) dari daftar kelas-kuliah: cocok pada prodi + id_matkul + nama kelas. */
    public static function lengkapiIdKelas(array $kelas, array $kelasKuliah): array
    {
        $peta = [];
        foreach ($kelasKuliah as $b) {
            if (is_array($b) && ! empty($b['id'])) {
                $peta[strtolower(self::teks($b['id_program_studi'] ?? '')) . '|' . strtolower(self::teks($b['id_matkul'] ?? '')) . '|' . self::norm(self::teks($b['nama_kelas'] ?? ''))] = (string) $b['id'];
            }
        }
        foreach ($kelas as &$k) {
            if ($k['id_kelas'] === '') {
                $k['id_kelas'] = $peta[strtolower($k['id_prodi']) . '|' . strtolower($k['id_matkul']) . '|' . self::norm($k['kelas'])] ?? '';
            }
        }
        return $kelas;
    }

    private static function teks($v): string
    {
        return is_scalar($v) ? trim((string) $v) : '';
    }

    public static function norm(?string $s): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $s));
    }

    private static function tanggal($v): ?\DateTimeImmutable
    {
        $s = self::teks($v);
        if ($s === '' || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
            return null;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? new \DateTimeImmutable("$m[1]-$m[2]-$m[3]") : null;
    }

    // ---------- Personalia & kalender (AcademiaV2) ----------

    /** id_sdm dari hasil /personalia/public/pegawai?nip=… (NIP harus persis sama). */
    public static function idSdm(array $baris, string $nip): ?string
    {
        foreach ($baris as $b) {
            if (is_array($b) && self::norm((string) ($b['nip'] ?? '')) === self::norm($nip) && ! empty($b['id_sdm'])) {
                return (string) $b['id_sdm'];
            }
        }
        return null;
    }

    /** Tahun ajaran V2 (UUID) untuk id_smt seperti 20252: cocok pada kode, atau tahun + semester (1 ganjil, 2 genap). */
    public static function cariTahunAjaran(array $baris, string $idSmt): ?string
    {
        $thn = (int) substr($idSmt, 0, 4);
        $smt = substr($idSmt, 4, 1);
        foreach ($baris as $b) {
            if (is_array($b) && isset($b['kode']) && (string) $b['kode'] === $idSmt && ! empty($b['id'])) {
                return (string) $b['id'];
            }
        }
        foreach ($baris as $b) {
            if (! is_array($b) || empty($b['id']) || (int) ($b['tahun'] ?? 0) !== $thn) {
                continue;
            }
            $sem = strtolower((string) ($b['semester'] ?? ''));
            if (($smt === '1' && str_contains($sem, 'ganjil')) || ($smt === '2' && str_contains($sem, 'genap'))) {
                return (string) $b['id'];
            }
        }
        return null;
    }

    /** @return list<array{no:?int,dari:\DateTimeImmutable,sampai:\DateTimeImmutable,kategori:string}> */
    public static function kalender(array $baris): array
    {
        $k = [];
        foreach ($baris as $b) {
            $d = is_array($b) ? self::tanggal($b['tgl_mulai'] ?? null) : null;
            $a = is_array($b) ? self::tanggal($b['tgl_selesai'] ?? null) : null;
            if ($d && $a) {
                $k[] = ['no' => isset($b['minggu_ke']) && is_numeric($b['minggu_ke']) ? (int) $b['minggu_ke'] : null, 'dari' => $d, 'sampai' => $a, 'kategori' => self::teks($b['nama_kategori'] ?? '')];
            }
        }
        usort($k, fn ($x, $y) => $x['dari'] <=> $y['dari']);
        return $k;
    }

    // ---------- Kelas dosen (distribusi SKS) ----------

    /** @return list<array{id_kelas:string,kode_mk:string,mk:string,kelas:string,sks:?float,rencana:?int,realisasi:?int}> */
    public static function kelasDosen(array $baris): array
    {
        $hasil = [];
        foreach ($baris as $b) {
            if (! is_array($b)) {
                continue;
            }
            $sks = self::nilai($b, 'sks');
            $ren = self::nilai($b, 'pertemuan_rencana');
            $rea = self::nilai($b, 'pertemuan_realisasi');
            $hasil[] = [
                'id_kelas' => self::teks(self::nilai($b, 'id_kelas')),
                'kode_mk' => self::teks(self::nilai($b, 'kode_mk')),
                'mk' => self::teks(self::nilai($b, 'nama_mk')),
                'kelas' => self::teks(self::nilai($b, 'kelas')),
                'id_matkul' => self::teks(self::nilai($b, 'id_matkul')),
                'prodi' => self::teks(self::nilai($b, 'prodi')),
                'jenis' => self::teks(self::nilai($b, 'jenis_kelas')),
                'jml_dosen' => is_numeric(self::nilai($b, 'jml_dosen')) ? (int) self::nilai($b, 'jml_dosen') : null,
                'id_prodi' => self::teks(self::nilai($b, 'id_prodi')),
                'wm' => is_numeric(self::nilai($b, 'wm')) ? (float) self::nilai($b, 'wm') : null,
                'lebih' => [self::angkaAtauNull(self::nilai($b, 'lebih100')), self::angkaAtauNull(self::nilai($b, 'lebih50')), self::angkaAtauNull(self::nilai($b, 'lebih0'))],
                'sks' => is_numeric($sks) ? (float) $sks : null,
                'rencana' => is_numeric($ren) ? (int) $ren : null,
                'realisasi' => is_numeric($rea) ? (int) $rea : null,
            ];
        }
        return $hasil;
    }

    // ---------- Pertemuan & minggu ----------

    /** Apakah sebuah baris jadwal sudah terealisasi: true/false, atau null bila tak ada kolom realisasi sama sekali. */
    public static function terealisasi(array $b): ?bool
    {
        if (self::adaKolom($b, 'realisasi_tanggal')) {
            return self::teks(self::nilai($b, 'realisasi_tanggal')) !== '';
        }
        if (self::adaKolom($b, 'realisasi')) {
            $v = self::nilai($b, 'realisasi');
            if (is_bool($v)) {
                return $v;
            }
            if (is_numeric($v)) {
                return (float) $v > 0;
            }
            return in_array(strtolower(self::teks($v)), self::YA, true);
        }
        return null;
    }

    /**
     * Menyusun pertemuan per minggu.
     * @param list<array> $kelas    hasil kelasDosen()
     * @param array<string,list<array>> $jadwal  id_kelas => baris rencana-jadwal
     * @return array{minggu:list<array>, kelas:list<array>, catatan:list<string>}
     */
    public static function susunMinggu(array $kelas, array $jadwal, string $idDosen, array $kalender = [], string $nip = ''): array
    {
        $catatan = [];
        $pertemuan = []; // satu entri per pertemuan
        $adaDosen = false;
        $adaRealisasi = false;
        $adaTanggal = false;
        $adaMinggu = false;
        $dilewati = 0;
        $statusJadwal = [];
        foreach ($kelas as $k) {
            foreach ($jadwal[$k['id_kelas']] ?? [] as $b) {
                if (! is_array($b)) {
                    continue;
                }
                // Bentuk sebenarnya rencana-jadwal: tiap pertemuan membawa daftar `pengajar` [{id_dosen, nip_dosen, nama_dosen}] (team teaching: beberapa dosen).
                if (isset($b['pengajar']) && is_array($b['pengajar'])) {
                    $adaDosen = true;
                    $milik = false;
                    foreach ($b['pengajar'] as $pj) {
                        if (is_array($pj) && ((self::teks($pj['id_dosen'] ?? $pj['id_sdm'] ?? '') !== '' && strcasecmp(self::teks($pj['id_dosen'] ?? $pj['id_sdm'] ?? ''), $idDosen) === 0)
                            || ($nip !== '' && self::norm((string) ($pj['nip_dosen'] ?? $pj['nip'] ?? '')) === self::norm($nip)))) {
                            $milik = true;
                            break;
                        }
                    }
                    if (! $milik) {
                        $dilewati++;
                        continue;
                    }
                }
                $d = self::teks(self::nilai($b, 'dosen'));
                if (! isset($b['pengajar']) && self::adaKolom($b, 'dosen') && $d !== '') {
                    $adaDosen = true;
                    if (strcasecmp($d, $idDosen) !== 0) {
                        $dilewati++;
                        continue; // pertemuan dosen lain pada kelas yang sama
                    }
                }
                $tgl = self::tanggal(self::nilai($b, 'tanggal'));
                $mg = self::nilai($b, 'minggu');
                $real = self::terealisasi($b);
                $adaRealisasi = $adaRealisasi || $real !== null;
                $adaTanggal = $adaTanggal || $tgl !== null;
                $adaMinggu = $adaMinggu || is_numeric($mg);
                $stat = self::teks($b['status'] ?? '');
                if ($stat !== '') {
                    $statusJadwal[mb_strtolower($stat)] = ($statusJadwal[mb_strtolower($stat)] ?? 0) + 1;
                }
                $pertemuan[] = ['kelas' => $k, 'ke' => self::nilai($b, 'pertemuan_ke'), 'tgl' => $tgl, 'minggu' => is_numeric($mg) ? (int) $mg : null, 'real' => $real,
                    'sks_per' => isset($b['sks']) && is_numeric($b['sks']) ? (float) $b['sks'] : null];
            }
        }
        if ($pertemuan !== [] && ! $adaDosen) {
            $catatan[] = 'Jadwal akademik tidak memuat dosen per pertemuan, jadi semua pertemuan kelas dihitung untuk dosen ini. Periksa kelas yang diajar bersama dosen lain.';
        }
        if ($dilewati > 0) {
            $catatan[] = "$dilewati pertemuan milik dosen lain pada kelas yang sama tidak dihitung.";
        }
        if ($statusJadwal) {
            ksort($statusJadwal);
            $catatan[] = 'Status pertemuan pada jadwal akademik: ' . implode(', ', array_map(fn ($k, $v) => "$k ($v)", array_keys($statusJadwal), $statusJadwal)) . '.';
        }
        if ($pertemuan !== [] && ! $adaRealisasi) {
            $catatan[] = 'Kolom realisasi pertemuan tidak ditemukan pada respons data akademik, sehingga jumlah realisasi tidak dapat dihitung. Lihat contoh respons untuk menambah nama kolom.';
        }
        if ($pertemuan !== [] && ! $adaTanggal && ! $adaMinggu) {
            $catatan[] = 'Jadwal akademik tidak memuat tanggal maupun nomor minggu, jadi pertemuan tidak dapat dikelompokkan per minggu.';
        }

        // Nomor minggu: dari kolom minggu bila ada, selain itu dari tanggal (Senin pertama = minggu 1).
        $awal = null;
        foreach ($pertemuan as $p) {
            if ($p['tgl'] && ($awal === null || $p['tgl'] < $awal)) {
                $awal = $p['tgl'];
            }
        }
        $senin = $awal ? $awal->modify('monday this week') : null;
        $minggu = [];
        foreach ($pertemuan as $p) {
            $no = $p['minggu'];
            $kal = null;
            if ($p['tgl']) {
                foreach ($kalender as $w) {
                    if ($w['no'] !== null && $p['tgl'] >= $w['dari'] && $p['tgl'] <= $w['sampai']) {
                        $kal = $w;
                        $no = $w['no'];
                        break;
                    }
                }
            }
            if ($no === null && $p['tgl'] && $kalender) {
                // Di luar rentang kalender: lanjutkan hitungan dari minggu kalender terdekat sebelumnya.
                $acuan = null;
                foreach ($kalender as $w) {
                    if ($w['no'] !== null && $w['dari'] <= $p['tgl']) {
                        $acuan = $w;
                    }
                }
                if ($acuan) {
                    $no = $acuan['no'] + intdiv($acuan['dari']->diff($p['tgl'])->days, 7);
                }
            }
            if ($no === null && $p['tgl'] && $senin) {
                $no = intdiv($senin->diff($p['tgl'])->days, 7) + 1;
            }
            if ($no === null) {
                continue;
            }
            $m = &$minggu[$no];
            $m ??= ['no' => $no, 'dari' => null, 'sampai' => null, 'kelas' => [], 'kategori' => ''];
            if ($kal) {
                $m['dari'] = $kal['dari'];
                $m['sampai'] = $kal['sampai'];
                $m['kategori'] = $kal['kategori'];
            } elseif ($p['tgl'] && $m['kategori'] === '') {
                $m['dari'] = $m['dari'] === null || $p['tgl'] < $m['dari'] ? $p['tgl'] : $m['dari'];
                $m['sampai'] = $m['sampai'] === null || $p['tgl'] > $m['sampai'] ? $p['tgl'] : $m['sampai'];
            }
            $kk = &$m['kelas'][$p['kelas']['id_kelas']];
            $kk ??= ['id_kelas' => $p['kelas']['id_kelas'], 'mk' => $p['kelas']['mk'], 'kelas' => $p['kelas']['kelas'], 'sks' => $p['kelas']['sks'], 'rencana' => 0, 'realisasi' => 0, 'pertemuan' => [], 'sks_pertemuan' => []];
            $kk['rencana']++;
            if ($p['sks_per'] !== null) {
                $kk['sks_pertemuan'][] = $p['sks_per'];
            }
            if ($p['real']) {
                $kk['realisasi']++;
            }
            if ($p['ke'] !== null) {
                $kk['pertemuan'][] = (int) $p['ke'];
            }
            unset($m, $kk);
        }
        // Minggu kalender yang tidak punya pertemuan (UTS, UAS, libur, dll) tetap ditampilkan.
        $kosong = [];
        foreach ($kalender as $w) {
            if ($w['no'] !== null && ! isset($minggu[$w['no']])) {
                $kosong[$w['no']] = ['no' => $w['no'], 'dari' => $w['dari'], 'sampai' => $w['sampai'], 'kelas' => [], 'kategori' => $w['kategori']];
            }
        }
        $minggu += $kosong;
        ksort($minggu);
        $keluar = [];
        foreach ($minggu as $m) {
            $rencana = array_sum(array_column($m['kelas'], 'rencana'));
            $real = $adaRealisasi ? array_sum(array_column($m['kelas'], 'realisasi')) : null;
            $keluar[] = [
                'no' => $m['no'], 'label' => 'Minggu ke-' . $m['no'],
                'dari' => $m['dari']?->format('Y-m-d'), 'sampai' => $m['sampai']?->format('Y-m-d'),
                'kelas' => array_values($m['kelas']), 'kategori' => $m['kategori'],
                'rencana' => $rencana, 'realisasi' => $real,
                'sks' => round(array_sum(array_map(fn ($c) => (float) ($c['sks'] ?? 0), $m['kelas'])), 2),
            ];
        }

        // Total per kelas dari jadwal.
        $perKelas = [];
        foreach ($kelas as $k) {
            $ps = array_filter($pertemuan, fn ($p) => $p['kelas']['id_kelas'] === $k['id_kelas']);
            $perKelas[$k['id_kelas']] = ! isset($jadwal[$k['id_kelas']]) ? ['rencana' => null, 'realisasi' => null] : ['rencana' => count($ps), 'realisasi' => $adaRealisasi ? count(array_filter($ps, fn ($p) => $p['real'])) : null];
        }
        $kelasOut = array_map(function ($k) use ($perKelas) {
            $j = $perKelas[$k['id_kelas']] ?? ['rencana' => null, 'realisasi' => null];
            return $k + ['jadwal_rencana' => $j['rencana'], 'jadwal_realisasi' => $j['realisasi']];
        }, $kelas);
        return ['minggu' => $keluar, 'kelas' => $kelasOut, 'catatan' => $catatan];
    }

    // ---------- Honor per minggu ----------

    /**
     * Honor per minggu = honor mingguan tiap kelas (dari perhitungan honor kita, tarif x SKS lebih) untuk kelas yang punya pertemuan pada minggu itu.
     * Total semester = jumlah semua minggu. "real" memakai minggu yang ada pertemuan terealisasinya, "rencana" memakai minggu yang punya pertemuan terjadwal.
     * @param list<object|array> $hitung baris v_detail_honor_kelas: mk, kelas, bruto, pajak, netto
     */
    public static function honorMinggu(array $minggu, array $hitung): array
    {
        $h = [];
        foreach ($hitung as $r) {
            $r = (array) $r;
            $h[self::norm($r['mk'] ?? '') . '|' . self::norm($r['kelas'] ?? '')] = [(int) ($r['bruto'] ?? 0), (int) ($r['pajak'] ?? 0), (int) ($r['netto'] ?? 0)];
        }
        $adaReal = false;
        foreach ($minggu as $m) {
            $adaReal = $adaReal || $m['realisasi'] !== null;
        }
        $total = ['real' => [0, 0, 0], 'rencana' => [0, 0, 0], 'minggu_real' => 0, 'minggu_rencana' => 0];
        $tanpa = [];
        foreach ($minggu as &$m) {
            $pl = [0, 0, 0];
            $re = [0, 0, 0];
            foreach ($m['kelas'] as $k) {
                $kunci = self::norm($k['mk']) . '|' . self::norm($k['kelas']);
                if (! isset($h[$kunci])) {
                    $tanpa[$k['mk'] . ' (' . $k['kelas'] . ')'] = true;
                    continue;
                }
                if ($k['rencana'] > 0) {
                    foreach ([0, 1, 2] as $i) {
                        $pl[$i] += $h[$kunci][$i];
                    }
                }
                if ($adaReal && ($k['realisasi'] ?? 0) > 0) {
                    foreach ([0, 1, 2] as $i) {
                        $re[$i] += $h[$kunci][$i];
                    }
                }
            }
            $m['honor'] = ['rencana' => ['bruto' => $pl[0], 'pajak' => $pl[1], 'netto' => $pl[2]], 'real' => $adaReal ? ['bruto' => $re[0], 'pajak' => $re[1], 'netto' => $re[2]] : null];
            foreach ([0, 1, 2] as $i) {
                $total['rencana'][$i] += $pl[$i];
                $total['real'][$i] += $re[$i];
            }
            $total['minggu_rencana'] += $pl[0] > 0 ? 1 : 0;
            $total['minggu_real'] += $re[0] > 0 ? 1 : 0;
        }
        unset($m);
        $f = fn ($a) => ['bruto' => $a[0], 'pajak' => $a[1], 'netto' => $a[2]];
        return ['minggu' => $minggu, 'total' => ['real' => $adaReal ? $f($total['real']) : null, 'rencana' => $f($total['rencana']),
            'minggu_real' => $adaReal ? $total['minggu_real'] : null, 'minggu_rencana' => $total['minggu_rencana']], 'tanpa_honor' => array_keys($tanpa)];
    }

    // ---------- Pencocokan dengan SISTER ----------

    /**
     * @param list<array> $kelas    kelas dari Academia (kelasDosen + jadwal_*)
     * @param list<object|array> $sister  baris tabel pengajaran: id_kelas, kode_matakuliah, mata_kuliah, kelas, sks, tatap_muka_rencana, tatap_muka_realisasi
     * @return list<array>
     */
    public static function banding(array $kelas, array $sister, array $hitung = []): array
    {
        $sis = array_map(fn ($r) => (array) $r, $sister);
        $hit = [];
        foreach ($hitung as $h) {
            $h = (array) $h;
            $hit[self::norm($h['mk'] ?? '') . '|' . self::norm($h['kelas'] ?? '')] = [(float) ($h['sks_100'] ?? 0), (float) ($h['sks_50'] ?? 0), (float) ($h['sks_0'] ?? 0)];
        }
        $fmt = fn ($x) => rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.');
        $dipakai = [];
        $hasil = [];
        foreach ($kelas as $k) {
            $cocok = null;
            foreach ($sis as $i => $s) {
                if (isset($dipakai[$i])) {
                    continue;
                }
                $byId = $k['id_kelas'] !== '' && strcasecmp((string) ($s['id_kelas'] ?? ''), $k['id_kelas']) === 0;
                $byKode = $k['kode_mk'] !== '' && self::norm($k['kode_mk']) === self::norm($s['kode_matakuliah'] ?? '') && self::norm($k['kelas']) === self::norm($s['kelas'] ?? '');
                $byNama = $k['mk'] !== '' && self::norm($k['mk']) === self::norm($s['mata_kuliah'] ?? '') && self::norm($k['kelas']) === self::norm($s['kelas'] ?? '');
                if ($byId || $byKode || $byNama) {
                    $cocok = $i;
                    break;
                }
            }
            $a = ['sks' => $k['sks'], 'rencana' => $k['rencana'] ?? $k['jadwal_rencana'] ?? null, 'realisasi' => $k['realisasi'] ?? $k['jadwal_realisasi'] ?? null];
            if ($cocok === null) {
                $hasil[] = ['info' => ['prodi' => $k['prodi'] ?? '', 'jenis' => $k['jenis'] ?? '', 'jml_dosen' => $k['jml_dosen'] ?? null], 'lebih' => ['academia' => $k['lebih'] ?? null, 'kita' => null], 'mk' => $k['mk'], 'kelas' => $k['kelas'], 'academia' => $a, 'sister' => null, 'status' => 'hanya_academia', 'selisih' => ['Kelas ada di data akademik tetapi tidak ditemukan di SISTER.']];
                continue;
            }
            $dipakai[$cocok] = true;
            $s = $sis[$cocok];
            $si = ['sks' => isset($s['sks']) ? (float) $s['sks'] : null, 'rencana' => isset($s['tatap_muka_rencana']) ? (int) $s['tatap_muka_rencana'] : null, 'realisasi' => isset($s['tatap_muka_realisasi']) ? (int) $s['tatap_muka_realisasi'] : null];
            $beda = [];
            // Kelas team teaching: SISTER mencatat SKS bagian dosen ini (SKS kelas dibagi jumlah dosen); itu yang dibayar, jadi itu pula yang dibandingkan.
            $jd = (int) ($k['jml_dosen'] ?? 0);
            $sksBagian = $a['sks'] !== null && $jd > 1 ? $a['sks'] / $jd : $a['sks'];
            if ($sksBagian !== null && $si['sks'] !== null && abs($sksBagian - $si['sks']) > 0.01) {
                $beda[] = 'SKS: akademik ' . rtrim(rtrim(number_format($a['sks'], 2, '.', ''), '0'), '.') . ($jd > 1 ? " dibagi $jd dosen = " . rtrim(rtrim(number_format($sksBagian, 2, '.', ''), '0'), '.') : '') . ', SISTER ' . rtrim(rtrim(number_format($si['sks'], 2, '.', ''), '0'), '.');
            }
            if ($a['rencana'] !== null && $si['rencana'] !== null && $a['rencana'] !== $si['rencana']) {
                $beda[] = "Pertemuan rencana: akademik {$a['rencana']}, SISTER {$si['rencana']}";
            }
            if ($a['realisasi'] !== null && $si['realisasi'] !== null && $a['realisasi'] !== $si['realisasi']) {
                $beda[] = "Pertemuan realisasi: akademik {$a['realisasi']}, SISTER {$si['realisasi']}";
            }
            $kita = $hit[self::norm($k['mk'] !== '' ? $k['mk'] : ($s['mata_kuliah'] ?? '')) . '|' . self::norm($k['kelas'])] ?? null;
            $le = ['academia' => $k['lebih'] ?? null, 'kita' => $kita];
            // Pembagian kelebihan SKS per kelas versi akademik hanya ditampilkan (kolom Kelebihan SKS), tidak menjadikan status "berbeda": yang dibayar mengikuti Perdir dan tarif Integratia.
            $hasil[] = ['info' => ['prodi' => $k['prodi'] ?? '', 'jenis' => $k['jenis'] ?? '', 'jml_dosen' => $k['jml_dosen'] ?? null], 'lebih' => $le, 'mk' => $k['mk'] !== '' ? $k['mk'] : ($s['mata_kuliah'] ?? ''), 'kelas' => $k['kelas'] !== '' ? $k['kelas'] : ($s['kelas'] ?? ''), 'academia' => $a, 'sister' => $si, 'status' => $beda === [] ? 'cocok' : 'selisih', 'selisih' => $beda];
        }
        foreach ($sis as $i => $s) {
            if (! isset($dipakai[$i])) {
                $hasil[] = ['mk' => $s['mata_kuliah'] ?? '', 'kelas' => $s['kelas'] ?? '', 'academia' => null,
                    'sister' => ['sks' => isset($s['sks']) ? (float) $s['sks'] : null, 'rencana' => isset($s['tatap_muka_rencana']) ? (int) $s['tatap_muka_rencana'] : null, 'realisasi' => isset($s['tatap_muka_realisasi']) ? (int) $s['tatap_muka_realisasi'] : null],
                    'status' => 'hanya_sister', 'selisih' => ['Kelas ada di SISTER tetapi tidak ditemukan di data akademik.']];
            }
        }
        return $hasil;
    }
}
