<?php

namespace App\Support;

/**
 * Perhitungan honor kelebihan SKS per periode. PHP murni: akses database lewat dua fungsi yang diberikan pemanggil
 * ($baca untuk SELECT atau INSERT ... RETURNING, $tulis untuk UPDATE/DELETE/INSERT), jadi sama persis dipakai di Laravel
 * (DB::select / DB::statement) dan di uji/uji_hitung.php (PDO). Transaksi diatur pemanggil.
 *
 * Aturan (ASUMSI, semuanya dapat diganti dari tabel pengaturan / pengaturan_pajak / master_tarif):
 *  - SKS wajib dipenuhi lebih dulu oleh kelas reguler, sisanya (termasuk kelas kerja sama) menjadi SKS lebih.
 *  - SKS lebih: `batas_sks_100` pertama dibayar 100%, `batas_sks_50` berikutnya 50%, sisanya 0%.
 *  - Tarif: baris master_tarif per jabatan (jabatan fungsional + golongan) bila di tanggal acuan hanya ada SATU nilai honor untuk
 *    jenis kelas itu; bila tidak ada atau ambigu, dipakai tarif umum (jabatan kosong). Kelas kerja sama belum dibedakan per mitra.
 *  - Pajak: pengaturan_pajak menurut golongan (golongan tak dikenal memakai III).
 *  - Tanggal acuan tarif: Ganjil = 1 September tahun pertama, Genap = 1 Maret tahun kedua (kalender akademik belum tersedia).
 * Semua angka dihitung dalam bilangan bulat (SKS dalam perseratus, pajak dalam sepersepuluhribu) agar pembulatan tepat.
 */
final class HitungHonor
{
    /** Dosen dan keadaan perhitungannya di periode ini (status null = belum pernah dihitung). */
    public const DOSEN_PERIODE = "SELECT d.id_dosen, d.nama_sdm, d.golongan, jf.nama_jabfung,
        p.status_verifikasi, p.sks_wajib::text AS sks_wajib, p.sumber_sks_wajib
        FROM dosen d LEFT JOIN jabatan_fungsional jf USING (id_jabfung)
        LEFT JOIN perhitungan p ON p.id_dosen = d.id_dosen AND p.id_smt = :smt
        WHERE d.id_dosen IN (SELECT id_dosen FROM pengajaran WHERE id_smt = :smt2) ORDER BY d.nama_sdm, d.id_dosen";
    public const KELAS = "SELECT id_pengajaran, sks::text AS sks, jenis_kelas FROM pengajaran WHERE id_dosen = :id AND id_smt = :smt
        ORDER BY (jenis_kelas = 'Kerja Sama'), mata_kuliah COLLATE \"C\", kelas COLLATE \"C\", id_pengajaran";
    public const TARIF = "SELECT id_tarif, jenis_kelas, jabatan, tarif_per_sks, berlaku_mulai, berlaku_sampai FROM master_tarif WHERE aktif";
    public const PAJAK = "SELECT golongan, (tarif_pph * 10000)::int AS bp FROM pengaturan_pajak";
    public const ATURAN = "SELECT kunci, nilai FROM pengaturan";
    public const PERIODE = "SELECT id_smt, label, semester, tahun_ajaran, terkunci FROM periode WHERE id_smt = :smt";
    public const SIMPAN = "INSERT INTO perhitungan (id_perhitungan, id_dosen, id_smt, sks_wajib, sumber_sks_wajib, sks_terlaksana, sks_lebih,
        sks_100, sks_50, sks_0, bruto, pajak, netto, status_verifikasi, dihitung_pada)
        VALUES (gen_random_uuid(), :id, :smt, CAST(:wajib AS numeric), :sumber, CAST(:terlaksana AS numeric), CAST(:lebih AS numeric),
        CAST(:s100 AS numeric), CAST(:s50 AS numeric), CAST(:s0 AS numeric), :bruto, :pajak, :netto, 'draf', now())
        ON CONFLICT (id_dosen, id_smt) DO UPDATE SET sks_wajib = EXCLUDED.sks_wajib, sumber_sks_wajib = EXCLUDED.sumber_sks_wajib,
        sks_terlaksana = EXCLUDED.sks_terlaksana, sks_lebih = EXCLUDED.sks_lebih, sks_100 = EXCLUDED.sks_100, sks_50 = EXCLUDED.sks_50,
        sks_0 = EXCLUDED.sks_0, bruto = EXCLUDED.bruto, pajak = EXCLUDED.pajak, netto = EXCLUDED.netto, dihitung_pada = now()
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
    /** @return array{id:int, honor:int, sumber:string}|null */
    public static function pilihTarif(array $tarif, string $jenis, ?string $jabfung, ?string $gol, string $tanggal): ?array
    {
        $berlaku = fn ($t) => $t->jenis_kelas === $jenis
            && ($t->berlaku_mulai === null || $t->berlaku_mulai <= $tanggal) && ($t->berlaku_sampai === null || $t->berlaku_sampai >= $tanggal);
        $umum = $khusus = [];
        foreach ($tarif as $t) {
            if (! $berlaku($t)) {
                continue;
            }
            if ($t->jabatan === null) {
                $umum[] = $t;
            } elseif ($jabfung && $gol && stripos($t->jabatan, "$jabfung $gol/") === 0) {
                $khusus[] = $t;
            }
        }
        if ($khusus && count(array_unique(array_map(fn ($t) => (int) $t->tarif_per_sks, $khusus))) === 1) {
            usort($khusus, fn ($a, $b) => $a->id_tarif <=> $b->id_tarif);
            return ['id' => (int) $khusus[0]->id_tarif, 'honor' => (int) $khusus[0]->tarif_per_sks, 'sumber' => 'jabatan'];
        }
        if ($umum) {
            usort($umum, fn ($a, $b) => strcmp((string) $b->berlaku_mulai, (string) $a->berlaku_mulai) ?: $a->id_tarif <=> $b->id_tarif);
            return ['id' => (int) $umum[0]->id_tarif, 'honor' => (int) $umum[0]->tarif_per_sks, 'sumber' => 'umum'];
        }
        return null;
    }

    // ---------- Inti hitung (tanpa database) ----------
    /**
     * @param array $kelas  [['id'=>..., 'sks'=>perseratus, 'jenis'=>'Reguler'|'Kerja Sama'], ...] sudah berurutan
     * @param callable $tarifUntuk fn(string $jenis): array{id:int, honor:int}
     * @return array{detail: array[], total: array}
     */
    public static function hitung(array $kelas, int $wajibH, int $batas100H, int $batas50H, int $pphBp, callable $tarifUntuk): array
    {
        $sisaWajib = $wajibH;
        $detail = [];
        $tot = ['sks' => 0, 'wajib' => 0, 's100' => 0, 's50' => 0, 's0' => 0, 'bruto' => 0, 'pajak' => 0];
        foreach ($kelas as $i => $k) {
            $pakai = min($k['sks'], $sisaWajib);
            $sisaWajib -= $pakai;
            $lebih = $k['sks'] - $pakai;
            $a100 = min($lebih, $batas100H);
            $batas100H -= $a100;
            $lebih -= $a100;
            $a50 = min($lebih, $batas50H);
            $batas50H -= $a50;
            $a0 = $lebih - $a50;
            $t = $tarifUntuk($k['jenis']);
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

    // ---------- Satu periode ----------
    /**
     * @param callable $baca  fn(string $sql, array $params): array  (baris berupa objek)
     * @param callable $tulis fn(string $sql, array $params): void
     * @return array{periode:string, dihitung:int, dilewati:array, bruto:int, tanpa_tarif:string[]}
     */
    public static function hitungPeriode(callable $baca, callable $tulis, string $idSmt): array
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
        $wajibDefault = self::perseratus($aturan['sks_wajib_default'] ?? 12);
        $b100 = self::perseratus($aturan['batas_sks_100'] ?? 6);
        $b50 = self::perseratus($aturan['batas_sks_50'] ?? 4);

        $hasil = ['periode' => $per->label, 'dihitung' => 0, 'dilewati' => ['disesuaikan' => 0, 'terverifikasi' => 0, 'terkunci' => 0], 'bruto' => 0, 'tanpa_tarif' => []];
        foreach ($baca(self::DOSEN_PERIODE, ['smt' => $idSmt, 'smt2' => $idSmt]) as $d) {
            if ($d->status_verifikasi !== null && $d->status_verifikasi !== 'draf') {
                $hasil['dilewati'][$d->status_verifikasi]++;
                continue;
            }
            $manual = $d->status_verifikasi === 'draf' && $d->sumber_sks_wajib === 'manual';
            $wajibH = $manual ? self::perseratus($d->sks_wajib) : $wajibDefault;
            $kelas = array_map(fn ($k) => ['id' => $k->id_pengajaran, 'sks' => self::perseratus($k->sks), 'jenis' => $k->jenis_kelas],
                $baca(self::KELAS, ['id' => $d->id_dosen, 'smt' => $idSmt]));
            $bp = (int) ($pajak[$d->golongan] ?? $pajak['III'] ?? 0);
            try {
                $r = self::hitung($kelas, $wajibH, $b100, $b50, $bp, function (string $jenis) use ($tarif, $d, $tanggal) {
                    $t = self::pilihTarif($tarif, $jenis, $d->nama_jabfung, $d->golongan, $tanggal);
                    if ($t === null) {
                        throw new \RuntimeException($jenis);
                    }
                    return $t;
                });
            } catch (\RuntimeException $e) {
                $hasil['tanpa_tarif'][] = "{$d->nama_sdm} (tarif {$e->getMessage()})";
                continue;
            }
            $t = $r['total'];
            $simpan = $baca(self::SIMPAN, ['id' => $d->id_dosen, 'smt' => $idSmt, 'wajib' => self::desimal($wajibH), 'sumber' => $manual ? 'manual' : 'default',
                'terlaksana' => self::desimal($t['sks']), 'lebih' => self::desimal($t['lebih']), 's100' => self::desimal($t['s100']),
                's50' => self::desimal($t['s50']), 's0' => self::desimal($t['s0']), 'bruto' => $t['bruto'], 'pajak' => $t['pajak'], 'netto' => $t['netto']]);
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
            $hasil['bruto'] += $t['bruto'];
        }
        return $hasil;
    }
}
