<?php

namespace App\Support;

/**
 * Honor kegiatan non-pengajaran per kegiatan, menurut SBM Internal PNJ No. 23/2026 (bagian 3.B) dan Kepdir 638/2026.
 *
 *  - Tarif: tabel tarif_nonpengajaran (kode tarif x golongan). Golongan IV memakai kolom tarif_iv, selain itu tarif_iii.
 *    Golongan belum diketahui: tarif golongan III dan kegiatan ditandai.
 *  - Honor = tarif x jumlah mahasiswa x proporsi. Pembimbing Skripsi/Tugas Akhir yang dibimbing lebih dari satu dosen:
 *    pembimbing 1 (utama) 60%, pembimbing 2 (pendamping) 40%. Pembimbing Akademik: tarif per mahasiswa per semester.
 *  - Hanya status BKD "Beban lebih" yang dibayar (Kepdir 638/2026, catatan Bagian C). Status lain, atau status yang belum
 *    ditemukan di SISTER: Rp0 dengan catatan, tidak ditebak.
 *  - Jenis tanpa tarif di SBM ("Lainnya", atau kategori yang tidak dikenali): honor dibiarkan kosong (NULL) dengan catatan.
 *  - Kegiatan milik dosen yang sudah diverifikasi Keuangan tidak diubah. Periode terkunci tidak dihitung.
 */
class HitungNonPengajaran
{
    public const PERIODE = "SELECT id_smt, label, terkunci FROM periode WHERE id_smt = :smt";
    public const KEGIATAN = "SELECT k.id_kegiatan, k.id_dosen, k.id_sumber, k.jenis, k.kategori, k.judul, k.jumlah_mhs, k.stat_bkd, g.golongan,
            (coalesce(v.status_verifikasi, 'menunggu') = 'terverifikasi') AS terkunci
        FROM kegiatan_nonpengajaran k
        LEFT JOIN v_dosen_golongan g ON g.id_dosen = k.id_dosen
        LEFT JOIN verifikasi_nonpengajaran v ON v.id_dosen = k.id_dosen AND v.id_smt = k.id_smt
        WHERE k.id_smt = :smt";
    public const TARIF = "SELECT kode, tarif_iv, tarif_iii FROM tarif_nonpengajaran";
    public const SIMPAN = "UPDATE kegiatan_nonpengajaran SET honor_bruto = :honor, kode_tarif = :kode, catatan = :catatan WHERE id_kegiatan = CAST(:id AS uuid)";

    private static function n(?string $s): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $s)));
    }

    /** Kode tarif untuk satu kegiatan, atau null bila SBM tidak punya tarifnya. */
    public static function kodeTarif(?string $jenis, ?string $kategori, ?string $judul = null): ?string
    {
        $kat = self::n($kategori);
        $uji = (bool) preg_match('/penguji|menguji|ujian/u', $kat);
        $pend = str_contains($kat, 'pendamping');
        $proposal = str_contains($kat, 'proposal');
        $tesis = str_contains($kat, 'tesis');
        switch ($jenis) {
            case 'Pembimbing Akademik':
                return 'pembimbing_akademik';
            case 'PKL/Magang':
                return 'pkl_pembimbing';
            case 'Tesis':
                if ($uji) {
                    return $proposal ? 'tesis_penguji_proposal' : 'tesis_penguji_hasil';
                }
                return $pend ? 'tesis_pembimbing_pendamping' : (str_contains($kat, 'pembimbing') || str_contains($kat, 'utama') ? 'tesis_pembimbing_utama' : null);
            case 'Seminar Proposal':
                return $tesis ? 'tesis_penguji_proposal' : 'skripsi_penguji_proposal';
            case 'Skripsi':
            case 'Tugas Akhir':
                if ($uji) {
                    return $proposal ? 'skripsi_penguji_proposal' : 'skripsi_penguji';
                }
                // SISTER kadang memberi kategori umum "Membimbing dan ikut membimbing dalam menghasilkan disertasi, tesis, skripsi dan laporan akhir studi ...":
                // untuk skripsi/tugas akhir tarifnya satu (skripsi_pembimbing, dibagi 60/40 bila ada lebih dari satu dosen), jadi cukup kata "bimbing".
                return str_contains($kat, 'bimbing') ? 'skripsi_pembimbing' : null;
        }
        return null;
    }

    /** @return array{0:?int,1:?string} [honor|null, catatan|null] */
    public static function hitung(object|array $k, array $tarif, int $pembimbingSkripsi = 1): array
    {
        $k = (object) $k;
        $kode = self::kodeTarif($k->jenis, $k->kategori, $k->judul ?? null);
        if ($kode === null || ! isset($tarif[$kode])) {
            return [null, 'belum ada tarif SBM untuk jenis/kategori ini (' . ($k->jenis === 'Lainnya' ? 'jenis Lainnya' : $k->kategori) . ')'];
        }
        $cat = [];
        $stat = self::n($k->stat_bkd ?? '');
        if ($stat !== 'beban lebih') {
            $alasan = $stat === '' ? 'status BKD belum ditemukan di SISTER' : 'status BKD "' . $k->stat_bkd . '" (bukan Beban lebih)';
            return [0, "tidak dibayar: $alasan"];
        }
        $iv = ($k->golongan ?? null) === 'IV';
        if (($k->golongan ?? null) === null) {
            $cat[] = 'golongan belum diketahui, tarif golongan III dipakai sementara';
        }
        $t = (int) ($iv ? $tarif[$kode]['iv'] : $tarif[$kode]['iii']);
        $mhs = max(1, (int) ($k->jumlah_mhs ?? 1));
        $bagi = 1.0;
        if ($kode === 'skripsi_pembimbing' && $pembimbingSkripsi > 1) {
            $bagi = str_contains(self::n($k->kategori), 'pendamping') ? 0.4 : 0.6;
            $cat[] = 'dibimbing ' . $pembimbingSkripsi . ' dosen: ' . ($bagi * 100) . '% dari tarif';
        }
        if ($mhs > 5 && in_array($kode, ['skripsi_pembimbing', 'tesis_pembimbing_utama', 'tesis_pembimbing_pendamping'], true)) {
            $cat[] = "jumlah mahasiswa tidak lazim ($mhs) untuk satu pembimbingan, cek rincian di SISTER";
        }
        return [(int) round($t * $mhs * $bagi), $cat ? implode('; ', $cat) : null];
    }

    public static function hitungPeriode(callable $baca, callable $tulis, string $idSmt, string $idDosen = ''): array
    {
        $per = $baca(self::PERIODE, ['smt' => $idSmt])[0] ?? null;
        if ($per === null) {
            throw new \DomainException('Periode tidak ditemukan.', 404);
        }
        if ($per->terkunci) {
            throw new \DomainException('Periode ini sudah dikunci dan tidak dapat dihitung ulang.', 409);
        }
        $tarif = [];
        foreach ($baca(self::TARIF, []) as $t) {
            $tarif[$t->kode] = ['iv' => (int) $t->tarif_iv, 'iii' => (int) $t->tarif_iii];
        }
        $rows = $baca(self::KEGIATAN, ['smt' => $idSmt]);
        // Jumlah dosen pembimbing per skripsi/TA (id_sumber sama), termasuk dosen yang sudah terverifikasi.
        $pemb = [];
        foreach ($rows as $r) {
            if (self::kodeTarif($r->jenis, $r->kategori) === 'skripsi_pembimbing' && $r->id_sumber !== null) {
                $pemb[$r->id_sumber][$r->id_dosen] = true;
            }
        }
        $h = ['periode' => $per->label, 'dihitung' => 0, 'dilewati' => 0, 'bruto' => 0, 'tanpa_tarif' => 0, 'tidak_dibayar' => 0, 'ditandai' => 0];
        foreach ($rows as $r) {
            if ($idDosen !== '' && (string) $r->id_dosen !== $idDosen) {
                continue;   // hitung ulang satu dosen (setelah koreksi manual)
            }
            if ($r->terkunci) {
                $h['dilewati']++;
                continue;
            }
            [$honor, $catatan] = self::hitung($r, $tarif, count($pemb[$r->id_sumber] ?? []) ?: 1);
            $tulis(self::SIMPAN, ['id' => $r->id_kegiatan, 'honor' => $honor, 'kode' => self::kodeTarif($r->jenis, $r->kategori, $r->judul), 'catatan' => $catatan]);
            $h['dihitung']++;
            $h['bruto'] += (int) $honor;
            $h['tanpa_tarif'] += $honor === null ? 1 : 0;
            $h['tidak_dibayar'] += $honor === 0 ? 1 : 0;
            $h['ditandai'] += $catatan !== null ? 1 : 0;
        }
        return $h;
    }
}
