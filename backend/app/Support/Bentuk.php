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

    // ---------- Admin Input dan Admin Super ----------

    /** Harus sama dengan JABATAN_OPSI di frontend (src/data/admin.js). Nanti diganti tabel referensi. */
    public const JABATAN_OPSI = [
        'Guru Besar IV/d - Pembina Utama Madya', 'Guru Besar IV/e - Pembina Utama',
        'Lektor Kepala IV/a - Pembina', 'Lektor Kepala IV/b - Pembina Tingkat I',
        'Lektor III/c - Penata', 'Lektor III/d - Penata Tingkat I',
        'Asisten Ahli III/a - Penata Muda', 'Asisten Ahli III/b - Penata Muda Tingkat I',
    ];

    public const LABEL_PERAN = ['dosen' => 'Dosen', 'keuangan' => 'Keuangan', 'pimpinan' => 'Pimpinan', 'admin' => 'Admin Input', 'admin_super' => 'Admin Super'];

    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** "25 Okt 2026, 20:58" dalam WIB. */
    public static function stempel(?string $waktu): string
    {
        if ($waktu === null || $waktu === '') {
            return '-';
        }
        $t = (new \DateTimeImmutable($waktu))->setTimezone(new \DateTimeZone('Asia/Jakarta'));
        return sprintf('%02d %s %d, %s', (int) $t->format('j'), self::BULAN[(int) $t->format('n') - 1], (int) $t->format('Y'), $t->format('H:i'));
    }

    public static function durasi(?int $detik): string
    {
        $d = max(0, (int) $detik);
        if ($d < 60) {
            return "$d detik";
        }
        return intdiv($d, 60) . ' menit' . ($d % 60 ? ' ' . ($d % 60) . ' detik' : '');
    }

    public static function kodeDariFrontend(string $kode): string
    {
        return $kode === 'admin' ? 'admin_input' : $kode;
    }

    public static function tarifAdmin(object $t): array
    {
        return ['id' => (int) $t->id_tarif, 'jenis' => $t->jenis_kelas, 'kelas' => $t->nama_kelas, 'jabatan' => $t->jabatan,
                'honor' => (int) $t->tarif_per_sks, 'mulai' => $t->berlaku_mulai, 'sampai' => $t->berlaku_sampai];
    }

    public static function perdir(object $d): array
    {
        return ['id' => (int) $d->id_dokumen, 'nomor' => $d->nomor, 'judul' => $d->tentang, 'tanggal' => $d->tanggal,
                'berkas' => $d->nama_file_asli, 'berlaku' => (bool) $d->berlaku];
    }

    public static function koneksi(?object $terakhir): array
    {
        return ['status' => ($terakhir === null || $terakhir->status === 'berhasil') ? 'Terhubung' : 'Gagal',
                'layanan' => 'SISTER Web Service', 'metode' => 'API (REST)',
                'terakhir' => self::stempel($terakhir->mulai ?? null), 'dijalankan' => $terakhir->oleh ?? '-'];
    }

    /** Log rinci satu sinkronisasi, bentuknya sama dengan buatLog() di frontend. */
    public static function logSinkron(object $run, array $endpoint, array $atribut): array
    {
        $per = [];
        foreach ($atribut as $a) {
            $per[$a->no][] = ['nama' => $a->nama, 'status' => $a->status];
        }
        $ep = array_map(fn ($e) => ['no' => (int) $e->no, 'nama' => $e->nama, 'path' => $e->path, 'status' => $e->status,
            'detik' => $e->detik === null ? null : (float) $e->detik, 'rekord' => (int) $e->rekord, 'keterangan' => $e->keterangan ?? '',
            'atributStatus' => $per[$e->no] ?? []], $endpoint);
        $ok = array_filter($ep, fn ($e) => $e['status'] === 'berhasil');
        return ['id' => $run->id_sinkron, 'mulai' => self::stempel($run->mulai), 'durasi' => self::durasi($run->durasi_detik),
            'semester' => $run->label ? "{$run->label} ({$run->id_smt})" : '-', 'status' => $run->status,
            'endpointBerhasil' => count($ok), 'endpointTotal' => count($ep), 'totalRekord' => array_sum(array_column($ok, 'rekord')),
            'endpoint' => $ep, 'pesanGagal' => $run->pesan_gagal ?? ''];
    }

    public static function pengguna(object $u): array
    {
        $roles = $u->roles === '' ? [] : array_map([self::class, 'kodeKeFrontend'], explode(',', $u->roles));
        return ['nip' => $u->username, 'nama' => $u->nama, 'email' => $u->email ?? '', 'roles' => $roles,
                'aktif' => (bool) $u->status_aktif, 'loginTerakhir' => self::stempel($u->login_terakhir)];
    }

    public static function logAktivitas(object $l): array
    {
        return ['waktu' => self::stempel($l->waktu), 'nama' => $l->nama ?? 'Tidak dikenal', 'role' => self::LABEL_PERAN[$l->peran ?? ''] ?? '-',
                'aksi' => $l->aksi, 'status' => $l->status === 'berhasil' ? 'Berhasil' : 'Gagal'];
    }
}
