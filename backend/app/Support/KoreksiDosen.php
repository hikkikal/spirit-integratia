<?php

namespace App\Support;

/**
 * Koreksi manual data dosen yang tidak (atau belum benar) dari SISTER: jabatan fungsional, golongan, tugas tambahan.
 *
 *  - Jabatan: dosen.id_jabfung diisi manual dan dosen.jabfung_manual = true, sehingga sinkron SISTER tidak menimpanya.
 *  - Golongan: dosen.golongan (sinkron memang tidak pernah menulisnya). NULL = turunan dari jabatan (v_dosen_golongan).
 *  - Tugas tambahan: tugas buatan manual (sumber = 'manual') tidak dihapus sinkron; tugas SISTER yang keliru
 *    dicatat di tugas_tambahan_diabaikan dan tidak ikut perhitungan. Dua-duanya bisa dibatalkan.
 * Setiap perubahan wajib beralasan dan dicatat di log_aktivitas oleh controller.
 */
final class KoreksiDosen
{
    public const GOLONGAN = ['III', 'IV'];

    public const DOSEN = "SELECT d.id_dosen, d.nama_sdm, d.id_jabfung, d.jabfung_manual, d.golongan AS golongan_manual, g.golongan AS golongan_efektif,
            jf.nama_jabfung, d.koreksi_pada, u.nama AS koreksi_oleh
        FROM dosen d LEFT JOIN jabatan_fungsional jf ON jf.id_jabfung = d.id_jabfung
        LEFT JOIN v_dosen_golongan g ON g.id_dosen = d.id_dosen LEFT JOIN users u ON u.id_user = d.koreksi_oleh
        WHERE d.id_dosen = CAST(:id AS uuid)";
    public const JABFUNG_DAFTAR = "SELECT id_jabfung, nama_jabfung FROM jabatan_fungsional ORDER BY nama_jabfung";
    public const TUGAS = "SELECT t.id_tugas, t.jenis_tugas, t.unit_kerja, t.tanggal_mulai::text AS mulai, t.tanggal_selesai::text AS selesai, t.sumber, t.alasan,
            coalesce(km.kelompok, p.kelompok) AS kelompok, (km.id_tugas IS NOT NULL) AS kelompok_manual, p.kelompok AS kelompok_peta,
            (x.id_tugas IS NOT NULL) AS diabaikan, x.alasan AS alasan_abaikan
        FROM tugas_tambahan_dosen t
        LEFT JOIN pemetaan_tugas_tambahan p ON lower(p.jenis_tugas) = lower(trim(t.jenis_tugas))
        LEFT JOIN tugas_tambahan_kelompok km ON km.id_tugas = t.id_tugas
        LEFT JOIN tugas_tambahan_diabaikan x ON x.id_tugas = t.id_tugas
        WHERE t.id_dosen = CAST(:id AS uuid) ORDER BY t.tanggal_mulai DESC NULLS LAST, t.jenis_tugas";
    public const JENIS_DIKENAL = "SELECT jenis_tugas, kelompok FROM pemetaan_tugas_tambahan ORDER BY jenis_tugas";
    public const TUGAS_SATU = "SELECT id_tugas, jenis_tugas, sumber FROM tugas_tambahan_dosen WHERE id_tugas = CAST(:tugas AS uuid) AND id_dosen = CAST(:id AS uuid)";

    public const UBAH_JABATAN = "UPDATE dosen SET id_jabfung = :jab, jabfung_manual = :manual, koreksi_oleh = CAST(:oleh AS uuid), koreksi_pada = now() WHERE id_dosen = CAST(:id AS uuid)";
    public const UBAH_GOLONGAN = "UPDATE dosen SET golongan = :gol, koreksi_oleh = CAST(:oleh AS uuid), koreksi_pada = now() WHERE id_dosen = CAST(:id AS uuid)";
    public const TUGAS_TAMBAH = "INSERT INTO tugas_tambahan_dosen (id_tugas, id_dosen, jenis_tugas, unit_kerja, tanggal_mulai, tanggal_selesai, sumber, alasan, dibuat_oleh, dibuat_pada)
        VALUES (gen_random_uuid(), CAST(:id AS uuid), :jenis, :unit, CAST(:mulai AS date), CAST(:selesai AS date), 'manual', :alasan, CAST(:oleh AS uuid), now())";
    public const TUGAS_TAMBAH_ID = "INSERT INTO tugas_tambahan_dosen (id_tugas, id_dosen, jenis_tugas, unit_kerja, tanggal_mulai, tanggal_selesai, sumber, alasan, dibuat_oleh, dibuat_pada)
        VALUES (CAST(:tugas AS uuid), CAST(:id AS uuid), :jenis, :unit, CAST(:mulai AS date), CAST(:selesai AS date), 'manual', :alasan, CAST(:oleh AS uuid), now())";
    public const KELOMPOK_SET = "INSERT INTO tugas_tambahan_kelompok (id_tugas, id_dosen, kelompok, alasan, oleh) VALUES (CAST(:tugas AS uuid), CAST(:id AS uuid), :kel, :alasan, CAST(:oleh AS uuid))
        ON CONFLICT (id_tugas) DO UPDATE SET kelompok = EXCLUDED.kelompok, alasan = EXCLUDED.alasan, oleh = EXCLUDED.oleh, waktu = now()";
    public const KELOMPOK_HAPUS = "DELETE FROM tugas_tambahan_kelompok WHERE id_tugas = CAST(:tugas AS uuid) AND id_dosen = CAST(:id AS uuid)";
    public const TUGAS_HAPUS_MANUAL = "DELETE FROM tugas_tambahan_dosen WHERE id_tugas = CAST(:tugas AS uuid) AND id_dosen = CAST(:id AS uuid) AND sumber = 'manual'";
    public const TUGAS_ABAIKAN = "INSERT INTO tugas_tambahan_diabaikan (id_tugas, id_dosen, alasan, oleh) VALUES (CAST(:tugas AS uuid), CAST(:id AS uuid), :alasan, CAST(:oleh AS uuid))
        ON CONFLICT (id_tugas) DO UPDATE SET alasan = EXCLUDED.alasan, oleh = EXCLUDED.oleh, waktu = now()";
    public const TUGAS_PULIHKAN = "DELETE FROM tugas_tambahan_diabaikan WHERE id_tugas = CAST(:tugas AS uuid) AND id_dosen = CAST(:id AS uuid)";

    // Perhitungan yang sudah diverifikasi dikembalikan ke draf supaya dihitung ulang dan diperiksa lagi. Yang terkunci tidak disentuh.
    public const RESET_PENGAJARAN = "UPDATE perhitungan SET status_verifikasi = 'draf', diverifikasi_oleh = NULL, diverifikasi_pada = NULL
        WHERE id_dosen = CAST(:id AS uuid) AND id_smt = :smt AND status_verifikasi = 'terverifikasi'";
    public const RESET_NP = "DELETE FROM verifikasi_nonpengajaran WHERE id_dosen = CAST(:id AS uuid) AND id_smt = :smt AND status_verifikasi = 'terverifikasi'";

    /** Bentuk data popup. */
    public static function bentuk(object $d, array $tugas, array $jabfung, array $jenis): array
    {
        return [
            'id' => $d->id_dosen, 'nama' => $d->nama_sdm,
            'jabatan' => ['id' => $d->id_jabfung, 'nama' => $d->nama_jabfung, 'manual' => (bool) $d->jabfung_manual],
            'golongan' => ['nilai' => $d->golongan_efektif, 'manual' => $d->golongan_manual !== null],
            'koreksiTerakhir' => $d->koreksi_pada ? ['oleh' => $d->koreksi_oleh, 'waktu' => Bentuk::stempel($d->koreksi_pada)] : null,
            'tugas' => array_map(fn ($t) => [
                'id' => $t->id_tugas, 'jenis' => $t->jenis_tugas, 'unit' => $t->unit_kerja, 'mulai' => $t->mulai, 'selesai' => $t->selesai,
                'sumber' => $t->sumber, 'alasan' => $t->alasan, 'kelompok' => $t->kelompok === null ? null : (int) $t->kelompok,
                'kelompokManual' => (bool) $t->kelompok_manual, 'kelompokPeta' => $t->kelompok_peta === null ? null : (int) $t->kelompok_peta,
                'diabaikan' => (bool) $t->diabaikan, 'alasanDiabaikan' => $t->alasan_abaikan,
            ], $tugas),
            'opsiJabatan' => array_map(fn ($j) => ['id' => $j->id_jabfung, 'nama' => $j->nama_jabfung], $jabfung),
            'opsiGolongan' => self::GOLONGAN,
            'opsiKelompok' => [1, 2, 3],
        ];
    }

    /** Ringkas nilai untuk log, misalnya "Lektor (SISTER)" → "Lektor Kepala (manual)". */
    public static function ringkas(?string $nilai): string
    {
        return $nilai === null || $nilai === '' ? 'belum ditentukan' : $nilai;
    }
}
