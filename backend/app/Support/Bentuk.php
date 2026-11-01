<?php

namespace App\Support;

/**
 * Mengubah baris basis data menjadi JSON yang bentuknya sama dengan data tiruan frontend
 * (src/data/mock.js dan src/data/dosen.js), supaya sambungan ke halaman tinggal mengganti sumbernya.
 * Murni fungsi PHP, tidak bergantung pada Laravel.
 */
final class Bentuk
{
    public static function angka($x): int|float|null
    {
        if ($x === null || $x === '') {
            return null;
        }
        $f = round((float) $x, 2);
        return floor($f) == $f ? (int) $f : $f;
    }

    public static function status(?string $s): string
    {
        return in_array($s, ['terverifikasi', 'terkunci'], true) ? 'terverifikasi' : 'menunggu';
    }

    public static function gol(?string $g): ?string
    {
        return $g ? "Gol $g" : null;
    }

    public static function kodeKeFrontend(string $kode): string
    {
        return $kode === 'admin_input' ? 'admin' : $kode;   // frontend memakai 'admin'
    }

    public static function profil(?object $r): ?array
    {
        return $r === null ? null : [
            'nama' => $r->nama, 'jabatan' => $r->jabatan, 'jurusan' => $r->jurusan, 'status' => $r->jenis_sdm,
            'statusPegawai' => $r->status_pegawai, 'email' => $r->email, 'nidn' => $r->nidn_samar, 'nip' => $r->nip_samar, 'nuptk' => $r->nuptk_samar,
        ];
    }

    /** @param object[] $rows */
    public static function riwayat(array $rows): array
    {
        return array_map(fn ($r) => [
            'periode' => $r->periode, 'sksWajib' => self::angka($r->sks_wajib), 'terlaksana' => self::angka($r->sks_terlaksana),
            'lebih' => self::angka($r->sks_lebih), 'sks100' => self::angka($r->sks_100), 'sks50' => self::angka($r->sks_50),
            'sks0' => self::angka($r->sks_0), 'bruto' => (int) $r->bruto, 'honor' => (int) $r->bruto, 'pajak' => (int) $r->pajak, 'netto' => (int) $r->netto,
            'status' => self::status($r->status_verifikasi),
        ], $rows);
    }

    public static function kelasPeriode(array $rows): array
    {
        return array_map(fn ($r) => [
            'mk' => $r->mk, 'kelas' => $r->kelas, 'kode' => $r->kode_matakuliah,
            'prodi' => trim(($r->jenjang_pendidikan ?? '') . ' ' . ($r->program_studi ?? '')),
            'jenis' => $r->jenis_kelas, 'sks' => self::angka($r->sks), 'sumber' => 'SISTER',
        ], $rows);
    }

    /** Padanan detailKelas[id] di mock.js, ditambah ringkasan dan tarif. */
    public static function detailHonor(?object $p, array $kelas, array $tarif): ?array
    {
        if ($p === null) {
            return null;
        }
        $t = ['Reguler' => null, 'Kerja Sama' => null];
        foreach ($tarif as $x) {
            if ($x->golongan === 'Semua' || $x->golongan === $p->golongan) {
                $t[$x->jenis_kelas] ??= (int) $x->tarif_per_sks;
            }
        }
        return [
            'nama' => $p->nama, 'golongan' => self::gol($p->golongan),
            'tarifReguler' => $t['Reguler'], 'tarifKerjaSama' => $t['Kerja Sama'],
            'status' => self::status($p->status_verifikasi),
            'sksWajib' => self::angka($p->sks_wajib), 'sksTerlaksana' => self::angka($p->sks_terlaksana), 'sksLebih' => self::angka($p->sks_lebih),
            'total' => ['lebih100' => self::angka($p->sks_100), 'lebih50' => self::angka($p->sks_50), 'lebih0' => self::angka($p->sks_0),
                        'bruto' => (int) $p->bruto, 'pajak' => (int) $p->pajak, 'netto' => (int) $p->netto],
            'kelas' => array_map(fn ($k) => [
                'no' => (int) $k->no, 'mk' => "{$k->mk} ({$k->kelas})", 'jenis' => $k->jenis_kelas, 'sks' => self::angka($k->sks_kelas),
                'wajib' => self::angka($k->sks_wajib_terpakai), 'lebih100' => self::angka($k->sks_100), 'lebih50' => self::angka($k->sks_50),
                'lebih0' => self::angka($k->sks_0), 'tarif' => (int) $k->tarif_per_sks,
                'bruto' => (int) $k->bruto, 'pajak' => (int) $k->pajak, 'netto' => (int) $k->netto,
            ], $kelas),
        ];
    }

    /** Padanan `dosen` di mock.js. */
    public static function dataHonor(array $rows): array
    {
        return array_map(fn ($r) => [
            'id' => $r->id_dosen, 'nama' => $r->nama, 'jurusan' => $r->jurusan, 'jabatan' => $r->jabatan, 'golongan' => self::gol($r->golongan),
            'sksWajib' => self::angka($r->sks_wajib), 'sksTerlaksana' => self::angka($r->sks_terlaksana), 'sksLebih' => self::angka($r->sks_lebih),
            'honor' => (int) $r->bruto, 'pajak' => (int) $r->pajak, 'netto' => (int) $r->netto, 'status' => self::status($r->status_verifikasi),
        ], $rows);
    }

    /** Padanan `jurusan` dan `laporanJurusan` di mock.js, ditambah total. */
    public static function dashboard(array $rows, int $honorTerverifikasi = 0): array
    {
        $jur = array_map(fn ($r) => [
            'nama' => $r->jurusan, 'dosen' => (int) $r->jumlah_dosen, 'terverifikasi' => (int) $r->terverifikasi,
            'menunggu' => (int) $r->jumlah_dosen - (int) $r->terverifikasi, 'sksLebih' => self::angka($r->sks_lebih),
            'honor' => (int) $r->bruto, 'pajak' => (int) $r->pajak, 'netto' => (int) $r->netto,
        ], $rows);
        $sum = fn (string $k) => array_sum(array_column($jur, $k));
        return [
            'honorTerverifikasi' => $honorTerverifikasi,
            'jurusan' => $jur,
            'total' => ['dosen' => $sum('dosen'), 'terverifikasi' => $sum('terverifikasi'), 'menunggu' => $sum('menunggu'),
                        'sksLebih' => self::angka($sum('sksLebih')), 'honor' => $sum('honor'), 'pajak' => $sum('pajak'), 'netto' => $sum('netto')],
        ];
    }

    public static function tarif(array $tarif, array $pajak, array $aturan): array
    {
        return [
            'tarif' => array_map(fn ($t) => ['jenis' => $t->jenis_kelas, 'golongan' => $t->golongan, 'perSks' => (int) $t->tarif_per_sks,
                'berlakuMulai' => $t->berlaku_mulai, 'dokumen' => $t->dokumen, 'tentang' => $t->tentang], $tarif),
            'pajak' => array_map(fn ($p) => ['golongan' => $p->golongan, 'tarif' => (float) $p->tarif_pph, 'catatan' => $p->catatan], $pajak),
            'aturan' => array_column(array_map(fn ($a) => (array) $a, $aturan), 'nilai', 'kunci'),
        ];
    }
}
