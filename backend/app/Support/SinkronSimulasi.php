<?php

namespace App\Support;

/**
 * Sinkronisasi SISTER TIRUAN (data dummy). Kemajuan dihitung dari waktu berjalan, tanpa menghubungi SISTER.
 * Saat sinkronisasi sungguhan dibuat, ganti pemanggil di AdminInputController dengan proses nyata
 * (antrean) yang mengisi tabel sinkronisasi_endpoint/atribut; bentuk respons ke frontend tidak berubah.
 * Murni PHP, tidak bergantung Laravel.
 */
final class SinkronSimulasi
{
    public const LANGKAH = 4;
    public const DETIK_PER_LANGKAH = 1.1;
    public const LANGKAH_GAGAL = 2;      // simulasi gagal berhenti di langkah 2 pada tampilan kemajuan
    public const ENDPOINT_GAGAL = 4;     // dan tercatat gagal di endpoint ke-4 pada log rinci

    public const PESAN = 'Request ke SISTER tidak dibalas dalam 30 detik (HTTP 504 Gateway Timeout). Sudah dicoba ulang 3 kali. Tidak ada atribut yang tersimpan dari endpoint ini.';

    /** nama, path, atribut, detik (contoh), rekord (contoh) */
    public const ENDPOINT = [
        ['BKD Akhir', '/bkd/laporan_akhir_bkd', ['id_smt', 'sks_kinerja_ajar', 'sks_lebih_ajar', 'id_jabfung', 'simpulan_asesor'], 1.2, 142],
        ['BKD Pengajaran', '/bkd/ajar', ['id_smt', 'id_katgiat', 'judul_keg', 'nm_kat', 'stat_kegiatan', 'nilai'], 2.1, 318],
        ['List Data Pengajaran', '/pengajaran', ['id_pengajaran', 'id_sdm', 'id_semester', 'kode_matakuliah', 'sks', 'id_kelas'], 2.1, 256],
        ['Detail Pengajaran', '/pengajaran/{id}', ['mata_kuliah', 'kelas', 'sks', 'sks_mata_kuliah', 'tatap_muka_rencana', 'tatap_muka_realisasi'], 4.6, 372],
        ['Pegawai di Perguruan Tinggi', '/referensi/sdm', ['id_sdm', 'nama_sdm', 'nidn', 'nip', 'nuptk', 'id_stat_pegawai', 'jenis_sdm'], 4.6, 43],
        ['Jabatan Fungsional', '/referensi/jabatan_fungsional', ['id_jabfung', 'nm_jabfung'], 0.7, 8],
    ];

    public static function totalDetik(bool $gagal): float
    {
        return ($gagal ? self::LANGKAH_GAGAL : self::LANGKAH) * self::DETIK_PER_LANGKAH;
    }

    public static function selesai(float $lewat, bool $gagal): bool
    {
        return $lewat >= self::totalDetik($gagal);
    }

    /** @return array{status:string, persen:int, langkah:string[]} */
    public static function kemajuan(float $lewat, bool $gagal): array
    {
        $d = self::DETIK_PER_LANGKAH;
        $henti = $gagal ? self::LANGKAH_GAGAL : 0;
        $langkah = [];
        for ($i = 0; $i < self::LANGKAH; $i++) {
            if ($henti && $i + 1 > $henti) {
                $langkah[] = 'menunggu';
            } elseif ($lewat >= ($i + 1) * $d) {
                $langkah[] = ($henti && $i + 1 === $henti) ? 'gagal' : 'selesai';
            } else {
                $langkah[] = $lewat >= $i * $d ? 'berjalan' : 'menunggu';
            }
        }
        $penuh = self::LANGKAH * $d;
        $selesai = self::selesai($lewat, $gagal);
        $persen = (int) round(min($lewat, $henti ? $henti * $d : $penuh) / $penuh * 100);
        return ['status' => !$selesai ? 'berjalan' : ($gagal ? 'gagal' : 'berhasil'),
                'persen' => $selesai && !$gagal ? 100 : min(100, $persen), 'langkah' => $langkah];
    }

    /** Kemajuan untuk sinkronisasi yang sudah selesai (dibaca dari riwayat). */
    public static function kemajuanSelesai(string $status): array
    {
        return $status === 'berhasil'
            ? ['status' => 'berhasil', 'persen' => 100, 'langkah' => array_fill(0, self::LANGKAH, 'selesai')]
            : self::kemajuan(self::totalDetik(true), true);
    }

    /**
     * Rincian endpoint dan atribut untuk satu run. $gagal: endpoint ke-4 gagal, sisanya dilewati.
     * @return array{endpoint: array[], atribut: array[], pesan: ?string}
     */
    public static function rincian(bool $gagal): array
    {
        $henti = $gagal ? self::ENDPOINT_GAGAL : 0;
        $ep = $at = [];
        foreach (self::ENDPOINT as $i => [$nama, $path, $atr, $dtk, $rek]) {
            $no = $i + 1;
            $st = !$henti || $no < $henti ? 'berhasil' : ($no === $henti ? 'gagal' : 'dilewati');
            $ep[] = ['no' => $no, 'nama' => $nama, 'path' => $path, 'status' => $st,
                'detik' => $st === 'berhasil' ? $dtk : ($st === 'gagal' ? 90 : null), 'rekord' => $st === 'berhasil' ? $rek : 0,
                'ket' => $st === 'gagal' ? self::PESAN : ($st === 'dilewati' ? 'Dilewati karena proses berhenti' : null)];
            foreach ($atr as $k => $a) {
                $at[] = ['no' => $no, 'nama' => $a, 'urut' => $k, 'status' => $st === 'berhasil' ? 'terambil' : $st];
            }
        }
        return ['endpoint' => $ep, 'atribut' => $at,
                'pesan' => $gagal ? 'Sinkronisasi berhenti di endpoint ' . self::ENDPOINT_GAGAL . ' (' . self::ENDPOINT[self::ENDPOINT_GAGAL - 1][0] . '). ' . self::PESAN : null];
    }
}
