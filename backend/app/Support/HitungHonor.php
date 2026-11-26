<?php

namespace App\Support;

/**
 * Perhitungan honor kelebihan SKS per periode. PHP murni: akses database lewat dua fungsi yang diberikan pemanggil
 * ($baca untuk SELECT atau INSERT ... RETURNING, $tulis untuk UPDATE/DELETE/INSERT), jadi sama persis dipakai di Laravel
 * (DB::select / DB::statement) dan di uji/uji_hitung.php (PDO). Transaksi diatur pemanggil.
 *
 * Aturan (Perdir 11/2025 dan Kepdir 638/2026; nilai dapat diganti dari tabel pengaturan / pengaturan_pajak / master_tarif):
 *  - SKS wajib menurut jabatan: dosen biasa (DS) 10; dosen dengan tugas tambahan (DT) kelompok 1 = 4, kelompok 2 = 5, kelompok 3 = 6 (pengaturan sks_wajib_ds, sks_wajib_dt1..3).
 *    Kelompok diambil dari tugas tambahan SISTER yang berlaku pada tanggal acuan periode, lewat tabel pemetaan_tugas_tambahan. Tanpa tugas tambahan = DS.
 *    Tugas yang belum dipetakan dihitung sebagai DS; beberapa tugas dengan kelompok berbeda memakai kelompok jabatan tertinggi. Keduanya TETAP dihitung dan
 *    diberi catatan "perlu pengecekan" (perhitungan.catatan), tidak dilewati.
 *  - Perdir 11/2025 Pasal 11 ayat 4: pembayaran 100% dari tarif tertinggi. SKS wajib dipenuhi dari kelas bertarif TERENDAH dulu, sisa SKS (lebih)
 *    dibayar dari kelas bertarif tertinggi dulu (kelas Kerja Sama biasanya lebih tinggi, jadi didahulukan).
 *  - SKS lebih: `batas_sks_100` pertama dibayar 100%, `batas_sks_50` berikutnya 50%, sisanya 0%.
 *  - Tarif: baris master_tarif menurut jenis kelas, jabatan fungsional dan golongan. Kelas Kerja Sama dicari menurut mitra (dari pola kode kelas,
 *    tabel pola_kelas_kerja_sama) lalu program studi; bila prodi tidak punya baris sendiri dipakai tarif mitra itu secara umum. Pada tiap tingkat harus
 *    ada SATU nilai honor; bila berbeda-beda (ambigu) atau tidak ada, dipakai tarif umum (jabatan kosong) bila ada, kalau tidak dosen itu dilaporkan
 *    (tanpa_tarif), tidak ditebak. Golongan belum diketahui: semua golongan dipertimbangkan, harus tetap satu nilai. Bila tidak ada tarif yang pasti (ambigu, atau jabatan fungsional
 *    kosong), dipakai tarif TERENDAH yang memenuhi syarat (jabatan kosong = Staff Pengajar) dan dosen diberi catatan "tarif sementara".
 *  - Pajak: pengaturan_pajak menurut golongan. Golongan = dosen.golongan bila diisi, kalau tidak turunan dari jabatan fungsional (view v_dosen_golongan,
 *    tabel pemetaan_jabfung_golongan). Golongan tak dikenal (mis. Tenaga Pengajar) SEMENTARA memakai III sampai Keuangan menentukan.
 *  - Tanggal acuan tarif: Ganjil = 1 September tahun pertama, Genap = 1 Maret tahun kedua (kalender akademik belum tersedia).
 * Semua angka dihitung dalam bilangan bulat (SKS dalam perseratus, pajak dalam sepersepuluhribu) agar pembulatan tepat.
 */
final class HitungHonor
{
    /** Dosen dan keadaan perhitungannya di periode ini (status null = belum pernah dihitung). */
    public const DOSEN_PERIODE = "SELECT d.id_dosen, d.nama_sdm, g.golongan, jf.nama_jabfung,
        p.status_verifikasi, p.sks_wajib::text AS sks_wajib, p.sumber_sks_wajib
        FROM dosen d LEFT JOIN v_dosen_golongan g USING (id_dosen) LEFT JOIN jabatan_fungsional jf USING (id_jabfung)
        LEFT JOIN perhitungan p ON p.id_dosen = d.id_dosen AND p.id_smt = :smt
        WHERE d.id_dosen IN (SELECT id_dosen FROM pengajaran WHERE id_smt = :smt2) ORDER BY d.nama_sdm, d.id_dosen";
    public const TUGAS = "SELECT t.id_dosen, t.jenis_tugas, k.kelompok AS kelompok_manual FROM tugas_tambahan_dosen t
        LEFT JOIN tugas_tambahan_kelompok k ON k.id_tugas = t.id_tugas
        WHERE t.id_tugas NOT IN (SELECT id_tugas FROM tugas_tambahan_diabaikan) AND (t.tanggal_mulai IS NULL OR t.tanggal_mulai <= CAST(:tgl AS date)) AND (t.tanggal_selesai IS NULL OR t.tanggal_selesai >= CAST(:tgl2 AS date))";
    public const PEMETAAN = "SELECT lower(jenis_tugas) AS jenis, kelompok FROM pemetaan_tugas_tambahan";
    public const KELAS = "SELECT pg.id_pengajaran, pg.sks::text AS sks, pg.jenis_kelas, pg.program_studi,
        (SELECT pk.mitra FROM pola_kelas_kerja_sama pk WHERE pg.jenis_kelas = 'Kerja Sama' AND CASE pk.tipe
            WHEN 'akhiran' THEN upper(pg.kelas) LIKE '%' || upper(pk.pola)
            WHEN 'awalan' THEN upper(pg.kelas) LIKE upper(pk.pola) || '%'
            ELSE upper(pg.kelas) LIKE '%' || upper(pk.pola) || '%' END ORDER BY length(pk.pola) DESC, pk.id_pola LIMIT 1) AS mitra,
        (SELECT k->>'jenis' FROM academia_hasil ah, jsonb_array_elements(ah.data->'kelas') k
            WHERE pg.jenis_kelas = 'Kerja Sama' AND ah.id_dosen = pg.id_dosen AND ah.id_smt = pg.id_smt
              AND lower(trim(k->>'kelas')) = lower(trim(pg.kelas)) AND lower(trim(k->>'mk')) = lower(trim(pg.mata_kuliah))
              AND coalesce(k->>'jenis', '') <> '' LIMIT 1) AS nama_jenis
        FROM pengajaran pg WHERE pg.id_dosen = :id AND pg.id_smt = :smt
        ORDER BY (pg.jenis_kelas = 'Kerja Sama'), pg.mata_kuliah COLLATE \"C\", pg.kelas COLLATE \"C\", pg.id_pengajaran";
    public const TARIF = "SELECT id_tarif, jenis_kelas, nama_kelas, mitra, jabatan, tarif_per_sks, berlaku_mulai, berlaku_sampai FROM master_tarif WHERE aktif";
    public const PAJAK = "SELECT golongan, (tarif_pph * 10000)::int AS bp FROM pengaturan_pajak";
    public const ATURAN = "SELECT kunci, nilai FROM pengaturan";
    public const PERIODE = "SELECT id_smt, label, semester, tahun_ajaran, terkunci FROM periode WHERE id_smt = :smt";
    public const SIMPAN = "INSERT INTO perhitungan (id_perhitungan, id_dosen, id_smt, sks_wajib, sumber_sks_wajib, sks_terlaksana, sks_lebih,
        sks_100, sks_50, sks_0, bruto, pajak, netto, status_verifikasi, dihitung_pada, catatan)
        VALUES (gen_random_uuid(), :id, :smt, CAST(:wajib AS numeric), :sumber, CAST(:terlaksana AS numeric), CAST(:lebih AS numeric),
        CAST(:s100 AS numeric), CAST(:s50 AS numeric), CAST(:s0 AS numeric), :bruto, :pajak, :netto, 'draf', now(), :catatan)
        ON CONFLICT (id_dosen, id_smt) DO UPDATE SET sks_wajib = EXCLUDED.sks_wajib, sumber_sks_wajib = EXCLUDED.sumber_sks_wajib,
        sks_terlaksana = EXCLUDED.sks_terlaksana, sks_lebih = EXCLUDED.sks_lebih, sks_100 = EXCLUDED.sks_100, sks_50 = EXCLUDED.sks_50,
        sks_0 = EXCLUDED.sks_0, bruto = EXCLUDED.bruto, pajak = EXCLUDED.pajak, netto = EXCLUDED.netto, dihitung_pada = now(), catatan = EXCLUDED.catatan
        WHERE perhitungan.status_verifikasi = 'draf' RETURNING id_perhitungan";
    public const HAPUS_DETAIL = "DELETE FROM detail_perhitungan WHERE id_perhitungan = :id";
    public const TAMBAH_DETAIL = "INSERT INTO detail_perhitungan (id_detail, id_perhitungan, id_pengajaran, urutan, sks_kelas, sks_wajib_terpakai,
        sks_100, sks_50, sks_0, jenis_kelas, id_tarif, tarif_per_sks, tarif_pph, bruto, pajak, netto)
        VALUES (gen_random_uuid(), :ph, :pg, :urutan, CAST(:sks AS numeric), CAST(:wajib AS numeric), CAST(:s100 AS numeric), CAST(:s50 AS numeric),
        CAST(:s0 AS numeric), :jenis, :tarif_id, :tarif, CAST(:pph AS numeric), :bruto, :pajak, :netto)";

    // ---------- Bilangan bulat ----------
    public static function perseratus($sks): int
    {
        return (int) round(((float) $sks) * 100);
    }

    public static function desimal(int $h): string
    {
        return sprintf('%s%d.%02d', $h < 0 ? '-' : '', intdiv(abs($h), 100), abs($h) % 100);
    }

    public static function pphDesimal(int $bp): string
    {
        return sprintf('%d.%04d', intdiv($bp, 10000), $bp % 10000);
    }

    public static function tanggalAcuan(string $semester, string $tahunAjaran): string
    {
        [$a, $b] = array_map('intval', explode('/', $tahunAjaran) + [1 => 0]);
        return $semester === 'Ganjil' ? sprintf('%04d-09-01', $a) : sprintf('%04d-03-01', $b ?: $a + 1);
    }

    // ---------- Tarif ----------
    private static function huruf(?string $x): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $x)));
    }

    /** Nama jabatan fungsional SISTER tanpa angka kreditnya: "Lektor Kepala (400.00)" menjadi "lektor kepala". Tenaga Pengajar = "Staff Pengajar" di master tarif. */
    public static function jabfungDasar(?string $nama): ?string
    {
        if ($nama === null || trim($nama) === '') {
            return null;
        }
        $d = self::huruf(preg_replace('/\s*\(.*$/', '', $nama));
        // Penamaan SISTER berbeda dari master tarif: Tenaga Pengajar = Staff Pengajar, Profesor = Guru Besar.
        return ['tenaga pengajar' => 'staff pengajar', 'profesor' => 'guru besar'][$d] ?? $d;
    }

    /**
     * Memecah teks jabatan master tarif: "Lektor Kepala IV/a-Pembina" => ['lektor kepala', 'IV']; "Lektor ---" => ['lektor', null] (semua golongan);
     * "Lektor XI-PPPK" => ['lektor', 'PPPK'] (tidak pernah dicocokkan). @return array{0:string,1:?string}|null
     */
    public static function uraiJabatan(string $jabatan): ?array
    {
        if (! preg_match('/^(.+?)\s+(---|([IVX]+)\/\S|[IVX]+-PPPK)/u', trim($jabatan), $m)) {
            return null;
        }
        return [self::huruf($m[1]), $m[2] === '---' ? null : (($m[3] ?? '') !== '' ? $m[3] : 'PPPK')];
    }

    /** Kunci prodi dari nama kelas tarif: "Teknik Multimedia Digital - MSU" => "teknik multimedia digital"; "MSU" (tanpa prodi) => null. */
    private static function kunciProdi(?string $namaKelas, string $mitra): ?string
    {
        $k = self::huruf(preg_split('/\s*[-~]\s*/u', (string) $namaKelas)[0] ?? '');
        $k = self::huruf(preg_replace('/\bkerja\s?sama\b/iu', '', $k));
        return $k === '' || $k === self::huruf($mitra) ? null : $k;
    }

    /** Mitra sebuah baris tarif: kolom mitra, atau (baris yang diinput manual) kata terakhir nama kelas. */
    private static function mitraBaris(object $t): ?string
    {
        if (! empty($t->mitra)) {
            return mb_strtoupper(trim($t->mitra));
        }
        $bagian = preg_split('/\s*[-~]\s*/u', (string) $t->nama_kelas) ?: [''];
        $akhir = trim(preg_replace('/^kerja\s?sama\s+/iu', '', (string) end($bagian)));
        return $akhir === '' ? null : mb_strtoupper($akhir);
    }

    /** Satu nilai honor? Kembalikan baris dengan id terkecil; kosong atau beragam => null (ambigu tidak ditebak). */
    private static function tunggal(array $baris): ?array
    {
        if (! $baris || count(array_unique(array_map(fn ($t) => (int) $t->tarif_per_sks, $baris))) !== 1) {
            return null;
        }
        usort($baris, fn ($a, $b) => $a->id_tarif <=> $b->id_tarif);
        return ['id' => (int) $baris[0]->id_tarif, 'honor' => (int) $baris[0]->tarif_per_sks, 'sumber' => 'jabatan'];
    }

    /** @return array{id:int, honor:int, sumber:string}|null  $gol = III/IV atau null bila belum diketahui */
    public static function pilihTarif(array $tarif, string $jenis, ?string $jabfung, ?string $gol, string $tanggal, ?string $mitra = null, ?string $prodi = null, bool $longgar = false, ?string $namaJenis = null): ?array
    {
        $berlaku = fn ($t) => $t->jenis_kelas === $jenis
            && ($t->berlaku_mulai === null || $t->berlaku_mulai <= $tanggal) && ($t->berlaku_sampai === null || $t->berlaku_sampai >= $tanggal);
        $dasar = self::jabfungDasar($jabfung);
        $umum = $cocok = [];
        foreach ($tarif as $t) {
            if (! $berlaku($t)) {
                continue;
            }
            if ($t->jabatan === null) {
                $umum[] = $t;
                continue;
            }
            $u = self::uraiJabatan($t->jabatan);
            if ($dasar === null || $u === null || ($dasar !== '*' && $u[0] !== $dasar) || $u[1] === 'PPPK' || ($gol !== null && $u[1] !== null && $u[1] !== $gol)) {
                continue;
            }
            $cocok[] = $t;
        }
        $hasil = null;
        $kandidat = [];
        if ($jenis === 'Kerja Sama') {
            // Nama jenis kelas dari Academia (mis. "... PT SBI Cilacap" / "... PT SBI Narogong") sama dengan nama kelas di master tarif: itu pembedanya.
            $nj = self::huruf($namaJenis);
            $persis = $nj === '' ? [] : array_values(array_filter($cocok, fn ($t) => self::huruf($t->nama_kelas) === $nj));
            if ($persis) {
                $kandidat = $persis;
                $hasil = self::tunggal($persis);
            } elseif ($mitra !== null && $mitra !== '') {
                $m = mb_strtoupper(trim($mitra));
                $milik = array_values(array_filter($cocok, fn ($t) => self::mitraBaris($t) === $m));
                $p = self::huruf($prodi);
                $prodiIni = $p === '' ? [] : array_values(array_filter($milik, function ($t) use ($m, $p) {
                    $k = self::kunciProdi($t->nama_kelas, $m);
                    return $k !== null && (str_contains($p, $k) || str_contains($k, $p));
                }));
                // Prodi punya baris sendiri: hanya itu yang dipakai (beragam = ambigu, tidak melebar). Kalau tidak, tarif mitra secara umum.
                $kandidat = $prodiIni ?: $milik;
                $hasil = self::tunggal($kandidat);
            }
        } else {
            $kandidat = $cocok;
            $hasil = self::tunggal($cocok);
        }
        if ($hasil !== null) {
            return $hasil;
        }
        if ($umum) {
            usort($umum, fn ($a, $b) => strcmp((string) $b->berlaku_mulai, (string) $a->berlaku_mulai) ?: $a->id_tarif <=> $b->id_tarif);
            return ['id' => (int) $umum[0]->id_tarif, 'honor' => (int) $umum[0]->tarif_per_sks, 'sumber' => 'umum'];
        }
        if ($longgar && $kandidat) {
            usort($kandidat, fn ($a, $b) => (int) $a->tarif_per_sks <=> (int) $b->tarif_per_sks ?: $a->id_tarif <=> $b->id_tarif);
            return ['id' => (int) $kandidat[0]->id_tarif, 'honor' => (int) $kandidat[0]->tarif_per_sks, 'sumber' => 'sementara'];
        }
        return null;
    }

    // ---------- Inti hitung (tanpa database) ----------
    /**
     * @param array $kelas  [['id'=>..., 'sks'=>perseratus, 'jenis'=>'Reguler'|'Kerja Sama'], ...] sudah berurutan
     * @param callable $tarifUntuk fn(string $jenis, array $kelas): array{id:int, honor:int}   ($kelas juga membawa 'prodi' dan 'mitra')
     * @return array{detail: array[], total: array}
     */
    public static function hitung(array $kelas, int $wajibH, int $batas100H, int $batas50H, int $pphBp, callable $tarifUntuk): array
    {
        foreach ($kelas as $i => $k) {
            $kelas[$i]['_t'] = $tarifUntuk($k['jenis'], $k);
            $kelas[$i]['_i'] = $i;
        }
        // Tarif tertinggi lebih dulu (seri: urutan asal). SKS wajib dipenuhi dari ujung terendah, SKS lebih dibayar dari ujung tertinggi.
        usort($kelas, fn ($a, $b) => $b['_t']['honor'] <=> $a['_t']['honor'] ?: $a['_i'] <=> $b['_i']);
        $wajibPakai = [];
        $sisaWajib = $wajibH;
        for ($j = count($kelas) - 1; $j >= 0; $j--) {
            $wajibPakai[$j] = min($kelas[$j]['sks'], $sisaWajib);
            $sisaWajib -= $wajibPakai[$j];
        }
        $detail = [];
        $tot = ['sks' => 0, 'wajib' => 0, 's100' => 0, 's50' => 0, 's0' => 0, 'bruto' => 0, 'pajak' => 0];
        foreach ($kelas as $i => $k) {
            $pakai = $wajibPakai[$i];
            $lebih = $k['sks'] - $pakai;
            $a100 = min($lebih, $batas100H);
            $batas100H -= $a100;
            $lebih -= $a100;
            $a50 = min($lebih, $batas50H);
            $batas50H -= $a50;
            $a0 = $lebih - $a50;
            $t = $k['_t'];
            $bruto = intdiv($t['honor'] * 2 * $a100 + $t['honor'] * $a50 + 100, 200);     // (honor*a100 + honor*0,5*a50)/100, dibulatkan ke atas pada .5
            $pajak = intdiv($bruto * $pphBp + 5000, 10000);
            $detail[] = ['id_pengajaran' => $k['id'], 'urutan' => $i + 1, 'sks' => $k['sks'], 'wajib' => $pakai, 's100' => $a100, 's50' => $a50, 's0' => $a0,
                'jenis' => $k['jenis'], 'tarif_id' => $t['id'], 'tarif' => $t['honor'], 'pph_bp' => $pphBp, 'bruto' => $bruto, 'pajak' => $pajak, 'netto' => $bruto - $pajak];
            foreach (['sks' => $k['sks'], 'wajib' => $pakai, 's100' => $a100, 's50' => $a50, 's0' => $a0, 'bruto' => $bruto, 'pajak' => $pajak] as $kk => $v) {
                $tot[$kk] += $v;
            }
        }
        $tot['lebih'] = $tot['sks'] - $tot['wajib'];
        $tot['netto'] = $tot['bruto'] - $tot['pajak'];
        return ['detail' => $detail, 'total' => $tot];
    }

    /**
     * Kelompok SKS wajib dari daftar jenis tugas tambahan (huruf kecil) dosen yang berlaku pada periode. TIDAK pernah menolak: yang tidak jelas
     * dihitung sebagai DS (belum dipetakan) atau kelompok jabatan tertinggi (beda kelompok), disertai catatan untuk dicek.
     * @param string[] $jenis  @param array<string,int> $peta jenis (huruf kecil) => kelompok 1..3
     * @return array{0:string,1:?string} [kunci wajib 'ds'|'dt1'|'dt2'|'dt3', catatan atau null]
     */
    public static function kelompokWajib(array $jenis, array $peta): array
    {
        if ($jenis === []) {
            return ['ds', null];
        }
        $belum = array_values(array_unique(array_filter($jenis, fn ($j) => ! isset($peta[$j]))));
        $kel = array_values(array_unique(array_map(fn ($j) => $peta[$j], array_filter($jenis, fn ($j) => isset($peta[$j])))));
        $ket = [];
        if ($belum) {
            $ket[] = 'tugas tambahan belum dipetakan' . ($kel ? ' (diabaikan)' : ', dihitung sebagai DS sementara') . ': ' . implode(', ', $belum);
        }
        if (! $kel) {
            return ['ds', implode('; ', $ket)];
        }
        if (count($kel) > 1) {
            sort($kel);
            $ket[] = 'tugas tambahan dari kelompok berbeda (' . implode(', ', $kel) . '), dipakai kelompok ' . $kel[0] . ' (jabatan tertinggi), perlu pengecekan';
        }
        return ['dt' . min($kel), $ket ? implode('; ', $ket) : null];
    }

    // ---------- Satu periode ----------
    /**
     * @param callable $baca  fn(string $sql, array $params): array  (baris berupa objek)
     * @param callable $tulis fn(string $sql, array $params): void
     * @return array{periode:string, dihitung:int, dilewati:array, bruto:int, ditandai:int, tanpa_tarif:string[], perlu_ditentukan:string[]}  (perlu_ditentukan kini selalu kosong)
     */
    public static function hitungPeriode(callable $baca, callable $tulis, string $idSmt, string $idDosen = ''): array
    {
        $per = $baca(self::PERIODE, ['smt' => $idSmt])[0] ?? null;
        if ($per === null) {
            throw new \DomainException('Periode tidak ditemukan.', 404);
        }
        if ($per->terkunci) {
            throw new \DomainException('Periode ini sudah dikunci dan tidak dapat dihitung ulang.', 409);
        }
        $aturan = array_column(array_map(fn ($a) => (array) $a, $baca(self::ATURAN, [])), 'nilai', 'kunci');
        $pajak = array_column(array_map(fn ($a) => (array) $a, $baca(self::PAJAK, [])), 'bp', 'golongan');
        $tarif = $baca(self::TARIF, []);
        $tanggal = self::tanggalAcuan($per->semester, $per->tahun_ajaran);
        $wajibAturan = ['ds' => self::perseratus($aturan['sks_wajib_ds'] ?? 10), 'dt1' => self::perseratus($aturan['sks_wajib_dt1'] ?? 4),
            'dt2' => self::perseratus($aturan['sks_wajib_dt2'] ?? 5), 'dt3' => self::perseratus($aturan['sks_wajib_dt3'] ?? 6)];
        $peta = array_column(array_map(fn ($a) => (array) $a, $baca(self::PEMETAAN, [])), 'kelompok', 'jenis');
        $tugas = [];
        foreach ($baca(self::TUGAS, ['tgl' => $tanggal, 'tgl2' => $tanggal]) as $t) {
            $j = mb_strtolower(trim($t->jenis_tugas));
            if ($t->kelompok_manual !== null) {
                // Kelompok ditentukan manual (koreksi): didahulukan atas pemetaan jenis, hanya untuk tugas ini.
                $j .= ' [kelompok ' . $t->kelompok_manual . ']';
                $peta[$j] = (int) $t->kelompok_manual;
            }
            $tugas[$t->id_dosen][] = $j;
        }
        $b100 = self::perseratus($aturan['batas_sks_100'] ?? 6);
        $b50 = self::perseratus($aturan['batas_sks_50'] ?? 4);

        $hasil = ['periode' => $per->label, 'dihitung' => 0, 'dilewati' => ['disesuaikan' => 0, 'terverifikasi' => 0, 'terkunci' => 0], 'bruto' => 0, 'ditandai' => 0, 'tanpa_tarif' => [], 'perlu_ditentukan' => []];
        foreach ($baca(self::DOSEN_PERIODE, ['smt' => $idSmt, 'smt2' => $idSmt]) as $d) {
            if ($idDosen !== '' && (string) $d->id_dosen !== $idDosen) {
                continue;   // hitung ulang satu dosen (setelah koreksi manual)
            }
            if ($d->status_verifikasi !== null && $d->status_verifikasi !== 'draf') {
                $hasil['dilewati'][$d->status_verifikasi]++;
                continue;
            }
            $flags = [];
            $tanpaTarif = [];
            $manual = $d->status_verifikasi === 'draf' && $d->sumber_sks_wajib === 'manual';
            if ($manual) {
                $wajibH = self::perseratus($d->sks_wajib);
                $sumberWajib = 'manual';
            } else {
                [$kunci, $catatWajib] = self::kelompokWajib($tugas[$d->id_dosen] ?? [], $peta);
                if ($catatWajib !== null) {
                    $flags[] = $catatWajib;
                }
                $wajibH = $wajibAturan[$kunci];
                $sumberWajib = $kunci === 'ds' ? 'default' : 'tugas_tambahan';
            }
            $kelas = array_map(fn ($k) => ['id' => $k->id_pengajaran, 'sks' => self::perseratus($k->sks), 'jenis' => $k->jenis_kelas, 'prodi' => $k->program_studi, 'mitra' => $k->mitra, 'nama_jenis' => $k->nama_jenis],
                $baca(self::KELAS, ['id' => $d->id_dosen, 'smt' => $idSmt]));
            $bp = (int) ($pajak[$d->golongan] ?? $pajak['III'] ?? 0);
            if ($d->golongan === null) {
                $flags[] = 'golongan belum diketahui, pajak sementara memakai golongan III (perlu pengecekan)';
            }
            if ($d->nama_jabfung === null) {
                $flags[] = 'jabatan fungsional belum diketahui (tidak diberikan SISTER), tarif sementara memakai Staff Pengajar (perlu pengecekan)';
            }
            try {
                $r = self::hitung($kelas, $wajibH, $b100, $b50, $bp, function (string $jenis, array $k) use ($tarif, $d, $tanggal, &$flags, &$tanpaTarif) {
                    $jab = $d->nama_jabfung ?? 'Tenaga Pengajar';
                    $t = self::pilihTarif($tarif, $jenis, $jab, $d->golongan, $tanggal, $k['mitra'] ?? null, $k['prodi'] ?? null, false, $k['nama_jenis'] ?? null);
                    if ($t === null) {
                        $t = self::pilihTarif($tarif, $jenis, $jab, $d->golongan, $tanggal, $k['mitra'] ?? null, $k['prodi'] ?? null, true, $k['nama_jenis'] ?? null);
                        if ($t !== null) {
                            $flags[] = 'tarif sementara (tarif terendah, karena tarif pasti tidak bisa dipilih) untuk kelas ' . $jenis . ($jenis === 'Kerja Sama' ? ' mitra ' . ($k['mitra'] ?? '-') . ' prodi ' . ($k['prodi'] ?? '-') : '') . ', perlu pengecekan';
                        }
                    }
                    if ($t === null && $d->nama_jabfung === null) {
                        // Jabatan kosong dan tarif Staff Pengajar tidak ada untuk kelas ini: pakai tarif TERENDAH dari jabatan mana pun.
                        $t = self::pilihTarif($tarif, $jenis, '*', null, $tanggal, $k['mitra'] ?? null, $k['prodi'] ?? null, true, $k['nama_jenis'] ?? null);
                        if ($t !== null) {
                            $flags[] = 'tarif sementara (terendah dari semua jabatan) untuk kelas ' . $jenis . ($jenis === 'Kerja Sama' ? ' mitra ' . ($k['mitra'] ?? '-') : '') . ', perlu pengecekan';
                        }
                    }
                    if ($t === null) {
                        // Tarif tidak ditemukan sama sekali: kelas tetap dicatat dengan honor Rp0 (ditangguhkan), tidak ditebak, dan dosen tetap tersimpan.
                        $alasan = $jenis . ($jenis === 'Kerja Sama' ? ', mitra ' . ($k['mitra'] ?? 'belum dikenali dari kode kelas') . ', prodi ' . ($k['prodi'] ?? '-') : '')
                            . ', jabatan ' . ($d->nama_jabfung ?? 'kosong') . ' gol ' . ($d->golongan ?? '?');
                        $flags[] = "tarif tidak ditemukan ($alasan): honor kelas ini Rp0 (ditangguhkan), perlu pengecekan";
                        $tanpaTarif[] = "{$d->nama_sdm} (tarif $alasan)";
                        return ['id' => null, 'honor' => 0, 'sumber' => 'tidak ada'];
                    }
                    return $t;
                });
            } catch (\RuntimeException $e) {
                $hasil['tanpa_tarif'][] = "{$d->nama_sdm} ({$e->getMessage()})";
                continue;
            }
            foreach (array_unique($tanpaTarif) as $x) {
                $hasil['tanpa_tarif'][] = $x;
            }
            $t = $r['total'];
            $simpan = $baca(self::SIMPAN, ['id' => $d->id_dosen, 'smt' => $idSmt, 'wajib' => self::desimal($wajibH), 'sumber' => $sumberWajib,
                'terlaksana' => self::desimal($t['sks']), 'lebih' => self::desimal($t['lebih']), 's100' => self::desimal($t['s100']),
                's50' => self::desimal($t['s50']), 's0' => self::desimal($t['s0']), 'bruto' => $t['bruto'], 'pajak' => $t['pajak'], 'netto' => $t['netto'],
                'catatan' => $flags ? implode('; ', array_unique($flags)) : null]);
            if ($simpan === []) {
                continue;   // status berubah menjadi bukan draf di tengah jalan: jangan ditimpa
            }
            $idPh = $simpan[0]->id_perhitungan;
            $tulis(self::HAPUS_DETAIL, ['id' => $idPh]);
            foreach ($r['detail'] as $x) {
                $tulis(self::TAMBAH_DETAIL, ['ph' => $idPh, 'pg' => $x['id_pengajaran'], 'urutan' => $x['urutan'], 'sks' => self::desimal($x['sks']),
                    'wajib' => self::desimal($x['wajib']), 's100' => self::desimal($x['s100']), 's50' => self::desimal($x['s50']), 's0' => self::desimal($x['s0']),
                    'jenis' => $x['jenis'], 'tarif_id' => $x['tarif_id'], 'tarif' => $x['tarif'], 'pph' => self::pphDesimal($x['pph_bp']),
                    'bruto' => $x['bruto'], 'pajak' => $x['pajak'], 'netto' => $x['netto']]);
            }
            $hasil['dihitung']++;
            if ($flags) {
                $hasil['ditandai']++;
            }
            $hasil['bruto'] += $t['bruto'];
        }
        return $hasil;
    }
}
