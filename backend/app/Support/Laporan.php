<?php

namespace App\Support;

/**
 * Data Laporan & Ekspor per dosen: pengajaran, non-pengajaran, dan gabungan (bruto, pajak, neto).
 * Satu sumber untuk pratinjau layar, Excel, dan PDF, jadi angka ketiganya selalu sama.
 * Status: 'terverifikasi' (bawaan, yang layak dibayar), 'menunggu', atau 'semua'.
 */
class Laporan
{
    public const JENIS = ['pengajaran' => 'Pengajaran', 'nonpengajaran' => 'Non-Pengajaran', 'gabungan' => 'Gabungan'];
    public const STATUS = ['terverifikasi' => 'Terverifikasi', 'menunggu' => 'Menunggu verifikasi', 'semua' => 'Semua status'];

    private const PENGAJARAN_OK = "status_verifikasi IN ('terverifikasi','terkunci')";

    /** @return array{kolom:array,baris:array,total:array} */
    public static function susun(callable $baca, string $jenis, string $idSmt, string $jurusan, string $status): array
    {
        $jur = trim($jurusan);
        $b = ['smt' => $idSmt, 'jur1' => $jur, 'jur2' => $jur];
        $uang = fn (string $k, string $j, int $l = 15) => ['k' => $k, 'judul' => $j, 'tipe' => 'uang', 'lebar' => $l];
        $dasar = [
            ['k' => 'no', 'judul' => 'No', 'tipe' => 'angka', 'lebar' => 4],
            ['k' => 'nama', 'judul' => 'Nama dosen', 'tipe' => 'teks', 'lebar' => 30],
            ['k' => 'jabatan', 'judul' => 'Jabatan', 'tipe' => 'teks', 'lebar' => 16],
            ['k' => 'golongan', 'judul' => 'Gol.', 'tipe' => 'teks', 'lebar' => 6],
            ['k' => 'jurusan', 'judul' => 'Jurusan', 'tipe' => 'teks', 'lebar' => 22],
        ];
        $akhir = [['k' => 'status', 'judul' => 'Status', 'tipe' => 'teks', 'lebar' => 14]];

        if ($jenis === 'pengajaran') {
            $ok = self::PENGAJARAN_OK;
            $kondisi = ['terverifikasi' => "AND $ok", 'menunggu' => "AND NOT ($ok)", 'semua' => ''][$status];
            $rows = $baca("SELECT nama, jabatan, golongan, jurusan, sks_wajib, sks_terlaksana, sks_lebih, bruto, pajak, netto,
                                  CASE WHEN $ok THEN 'Terverifikasi' ELSE 'Menunggu' END AS status
                           FROM v_data_honor WHERE id_smt = :smt AND (:jur1 = '' OR jurusan = :jur2) $kondisi ORDER BY nama", $b);
            $kolom = array_merge($dasar, [
                ['k' => 'sks_wajib', 'judul' => 'SKS wajib', 'tipe' => 'desimal', 'lebar' => 9],
                ['k' => 'sks_terlaksana', 'judul' => 'SKS terlaksana', 'tipe' => 'desimal', 'lebar' => 10],
                ['k' => 'sks_lebih', 'judul' => 'SKS lebih', 'tipe' => 'desimal', 'lebar' => 9],
                $uang('bruto', 'Honor bruto'), $uang('pajak', 'Pajak'), $uang('netto', 'Honor neto'),
            ], $akhir);
            $jumlah = ['sks_wajib', 'sks_terlaksana', 'sks_lebih', 'bruto', 'pajak', 'netto'];
        } elseif ($jenis === 'nonpengajaran') {
            $kondisi = ['terverifikasi' => "AND status_verifikasi = 'terverifikasi'", 'menunggu' => "AND status_verifikasi <> 'terverifikasi'", 'semua' => ''][$status];
            $rows = $baca("SELECT nama, jabatan, golongan, jurusan, jumlah_kegiatan, bruto, pajak, netto,
                                  CASE WHEN status_verifikasi = 'terverifikasi' THEN 'Terverifikasi' ELSE 'Menunggu' END AS status
                           FROM v_np_honor WHERE id_smt = :smt AND (:jur1 = '' OR jurusan = :jur2) $kondisi ORDER BY nama", $b);
            $kolom = array_merge($dasar, [
                ['k' => 'jumlah_kegiatan', 'judul' => 'Kegiatan', 'tipe' => 'angka', 'lebar' => 9],
                $uang('bruto', 'Honor bruto'), $uang('pajak', 'Pajak'), $uang('netto', 'Honor neto'),
            ], $akhir);
            $jumlah = ['jumlah_kegiatan', 'bruto', 'pajak', 'netto'];
        } else {
            // Gabungan: satu baris per dosen yang punya honor pengajaran dan/atau non-pengajaran.
            // Terverifikasi = setiap bagian yang ada sudah terverifikasi.
            $ok = self::PENGAJARAN_OK;
            $kondisi = ['terverifikasi' => 'AND g.lengkap', 'menunggu' => 'AND NOT g.lengkap', 'semua' => ''][$status];
            $rows = $baca("SELECT * FROM (
                             SELECT coalesce(p.nama, n.nama) AS nama, coalesce(p.jabatan, n.jabatan) AS jabatan,
                                    coalesce(p.golongan, n.golongan) AS golongan, coalesce(p.jurusan, n.jurusan) AS jurusan,
                                    coalesce(p.bruto, 0)::bigint AS p_bruto, coalesce(n.bruto, 0)::bigint AS n_bruto,
                                    (coalesce(p.bruto, 0) + coalesce(n.bruto, 0))::bigint AS bruto,
                                    (coalesce(p.pajak, 0) + coalesce(n.pajak, 0))::bigint AS pajak,
                                    (coalesce(p.netto, 0) + coalesce(n.netto, 0))::bigint AS netto,
                                    (coalesce(p.status_verifikasi IN ('terverifikasi','terkunci'), true) AND coalesce(n.status_verifikasi = 'terverifikasi', true)) AS lengkap
                             FROM (SELECT * FROM v_data_honor WHERE id_smt = :smt) p
                             FULL JOIN (SELECT * FROM v_np_honor WHERE id_smt = :smt2) n ON n.id_dosen = p.id_dosen
                           ) g
                           WHERE (:jur1 = '' OR g.jurusan = :jur2) $kondisi ORDER BY g.nama", $b + ['smt2' => $idSmt]);
            foreach ($rows as $r) {
                $r->status = $r->lengkap ? 'Terverifikasi' : 'Menunggu';
            }
            $kolom = array_merge($dasar, [
                $uang('p_bruto', 'Bruto pengajaran'), $uang('n_bruto', 'Bruto non-pengajaran'),
                $uang('bruto', 'Total bruto'), $uang('pajak', 'Pajak'), $uang('netto', 'Total neto'),
            ], $akhir);
            $jumlah = ['p_bruto', 'n_bruto', 'bruto', 'pajak', 'netto'];
        }

        $baris = [];
        $total = array_fill_keys($jumlah, 0);
        foreach ($rows as $i => $r) {
            $x = ['no' => $i + 1];
            foreach ($kolom as $k) {
                if ($k['k'] !== 'no') {
                    $v = $r->{$k['k']} ?? null;
                    $x[$k['k']] = in_array($k['tipe'], ['uang', 'angka', 'desimal'], true) && $v !== null ? 0 + $v : $v;
                }
            }
            foreach ($jumlah as $k) {
                $total[$k] += $x[$k] ?? 0;
            }
            $baris[] = $x;
        }
        // Hindari galat floating point pada SKS.
        foreach ($total as $k => $v) {
            $total[$k] = round($v, 2);
        }
        $total['nama'] = 'Total ('.count($baris).' dosen)';
        return ['kolom' => $kolom, 'baris' => $baris, 'total' => $total];
    }
}
