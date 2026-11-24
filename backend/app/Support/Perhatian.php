<?php

namespace App\Support;

/**
 * Data yang ditandai (catatan) untuk dicek Keuangan. Satu daftar dipakai bersama oleh dashboard (jumlah), daftar dosen (filter
 * lewat tautan ?cek=kunci), dan penanda per baris, supaya angkanya selalu sama.
 *  - sumber 'p': kolom perhitungan.catatan (pengajaran), dicocokkan lewat potongan teks.
 *  - sumber 'n': kegiatan_nonpengajaran (non-pengajaran).
 */
class Perhatian
{
    /** kunci => [sumber, label, potongan teks catatan (p) / syarat SQL pada kegiatan (n), info saja?] */
    public const ITEM = [
        'p_tarif' => ['p', 'Pengajaran: tarif tidak ditemukan, honor kelas Rp0 (ditangguhkan)', 'ditangguhkan', false],
        'p_sementara' => ['p', 'Pengajaran: memakai tarif sementara', 'tarif sementara', false],
        'p_tugas' => ['p', 'Pengajaran: tugas tambahan belum dipetakan (dihitung sebagai Dosen Biasa)', 'tugas tambahan belum dipetakan', false],
        'p_golongan' => ['p', 'Pengajaran: golongan belum diketahui (pajak golongan III sementara)', 'golongan belum diketahui', false],
        'p_jabatan' => ['p', 'Pengajaran: jabatan fungsional belum diketahui', 'jabatan fungsional belum diketahui', false],
        'np_tarif' => ['n', 'Non-pengajaran: kegiatan belum punya tarif SBM (kategori tidak dikenal)', "k.honor_bruto IS NULL AND k.jenis <> 'Lainnya'", false],
        'np_golongan' => ['n', 'Non-pengajaran: golongan belum diketahui (tarif golongan III sementara)', "k.catatan ILIKE '%golongan belum diketahui%'", false],
        'np_mhs' => ['n', 'Non-pengajaran: jumlah mahasiswa tidak lazim', "k.catatan ILIKE '%tidak lazim%'", false],
        'np_lainnya' => ['n', 'Non-pengajaran: kegiatan jenis "Lainnya" (memang tidak punya tarif SBM, hanya info)', "k.jenis = 'Lainnya'", true],
    ];

    /** Kunci yang berlaku untuk satu teks catatan pengajaran. */
    public static function flagPengajaran(?string $catatan): array
    {
        if ($catatan === null || $catatan === '') {
            return [];
        }
        $out = [];
        foreach (self::ITEM as $kunci => [$sumber, , $cari]) {
            if ($sumber === 'p' && stripos($catatan, $cari) !== false) {
                $out[] = $kunci;
            }
        }
        return $out;
    }

    /** Potongan SQL untuk kolom flag pada daftar non-pengajaran (alias k = kegiatan_nonpengajaran). */
    public static function sqlFlagNp(): string
    {
        $bagian = [];
        foreach (self::ITEM as $kunci => [$sumber, , $syarat]) {
            if ($sumber === 'n') {
                $bagian[] = "CASE WHEN bool_or($syarat) THEN '$kunci' END";
            }
        }
        return 'array_to_string(array_remove(ARRAY[' . implode(', ', $bagian) . "], NULL), ',')";
    }

    /** @param callable $baca fn(string $sql, array $bind): array  @return list<array{kunci:string,label:string,jumlah:int,info:bool,tujuan:string}> */
    public static function hitung(callable $baca, string $smt): array
    {
        $out = [];
        foreach (self::ITEM as $kunci => [$sumber, $label, $cari, $info]) {
            $n = $sumber === 'p'
                ? $baca('SELECT count(*) AS n FROM perhitungan WHERE id_smt = :smt AND catatan ILIKE :cari', ['smt' => $smt, 'cari' => '%' . $cari . '%'])
                : $baca("SELECT count(DISTINCT k.id_dosen) AS n FROM kegiatan_nonpengajaran k WHERE k.id_smt = :smt AND ($cari)", ['smt' => $smt]);
            $jumlah = (int) ($n[0]->n ?? 0);
            if ($jumlah > 0) {
                $out[] = ['kunci' => $kunci, 'label' => $label, 'jumlah' => $jumlah, 'info' => $info, 'tujuan' => $sumber === 'p' ? 'data-honor' : 'non-pengajaran'];
            }
        }
        return $out;
    }
}
