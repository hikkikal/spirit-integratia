<?php

namespace App\Support;

/**
 * Semua SQL aplikasi ada di sini (dibaca dari view di db/schema.sql).
 * Nama parameter tidak boleh dipakai dua kali dalam satu kueri (batasan PDO).
 */
final class Kueri
{
    public const PERIODE_SEMUA = "SELECT id_smt, label FROM periode ORDER BY id_smt DESC";
    public const PERIODE_BY_LABEL = "SELECT id_smt, label FROM periode WHERE label = :label";
    public const PERIODE_TERBARU_BERDATA = "SELECT pr.id_smt, pr.label FROM periode pr
        WHERE EXISTS (SELECT 1 FROM perhitungan p WHERE p.id_smt = pr.id_smt) ORDER BY pr.id_smt DESC LIMIT 1";

    // Pengguna dan peran
    public const USER_BY_USERNAME = "SELECT id_user, username, nama, id_dosen FROM users WHERE username = :username AND status_aktif";
    public const ROLES_USER = "SELECT r.kode FROM user_roles ur JOIN roles r USING (id_role) WHERE ur.id_user = :id_user ORDER BY r.id_role";

    // Dosen
    public const PROFIL = "SELECT v.nama, v.jabatan, v.jurusan, v.jenis_sdm, v.status_pegawai, v.nidn_samar, v.nip_samar, v.nuptk_samar,
        (SELECT u.email FROM users u WHERE u.id_dosen = v.id_dosen ORDER BY u.username LIMIT 1) AS email
        FROM v_profil_dosen v WHERE v.id_dosen = :id";
    public const RIWAYAT = "SELECT pr.label AS periode, p.sks_wajib, p.sks_terlaksana, p.sks_lebih, p.sks_100, p.sks_50, p.sks_0,
        p.bruto, p.pajak, p.netto, p.status_verifikasi
        FROM perhitungan p JOIN periode pr USING (id_smt) WHERE p.id_dosen = :id ORDER BY p.id_smt DESC";
    public const KELAS_PERIODE = "SELECT mk, kelas, kode_matakuliah, program_studi, jenjang_pendidikan, jenis_kelas, sks
        FROM v_rincian_kelas WHERE id_dosen = :id AND id_smt = :smt ORDER BY mk, kelas";
    public const PERHITUNGAN = "SELECT p.id_perhitungan, d.nama_sdm AS nama, d.golongan, p.sks_wajib, p.sks_terlaksana, p.sks_lebih,
        p.sks_100, p.sks_50, p.sks_0, p.bruto, p.pajak, p.netto, p.status_verifikasi
        FROM perhitungan p JOIN dosen d USING (id_dosen) WHERE p.id_dosen = :id AND p.id_smt = :smt";
    public const DETAIL_KELAS = "SELECT no, mk, kelas, jenis_kelas, sks_kelas, sks_wajib_terpakai, sks_100, sks_50, sks_0,
        tarif_per_sks, tarif_pph, bruto, pajak, netto FROM v_detail_honor_kelas WHERE id_dosen = :id AND id_smt = :smt ORDER BY no";

    // Tarif dan ketentuan
    public const TARIF_AKTIF = "SELECT jenis_kelas, golongan, tarif_per_sks, berlaku_mulai, dokumen, tentang FROM v_tarif_aktif ORDER BY jenis_kelas";
    public const PAJAK = "SELECT golongan, tarif_pph, catatan FROM pengaturan_pajak ORDER BY golongan";
    public const ATURAN = "SELECT kunci, nilai FROM pengaturan ORDER BY kunci";

    // Keuangan dan pimpinan
    public const DASHBOARD = "SELECT jurusan, jumlah_dosen, terverifikasi, sks_lebih, bruto, pajak, netto
        FROM v_dashboard_jurusan WHERE id_smt = :smt ORDER BY jurusan";
    public const HONOR_TERVERIFIKASI = "SELECT coalesce(sum(bruto) FILTER (WHERE status_verifikasi IN ('terverifikasi', 'terkunci')), 0) AS n
        FROM v_data_honor WHERE id_smt = :smt";
    public const DATA_HONOR = "SELECT id_dosen, nama, jabatan, golongan, jurusan, sks_wajib, sks_terlaksana, sks_lebih, bruto, pajak, netto, status_verifikasi
        FROM v_data_honor WHERE id_smt = :smt AND (:jur1 = '' OR jurusan = :jur2) AND (:q1 = '' OR nama ILIKE '%' || :q2 || '%')
        ORDER BY nama";
    public const VERIFIKASI = "UPDATE perhitungan SET status_verifikasi = 'terverifikasi', diverifikasi_oleh = :pengguna, diverifikasi_pada = now()
        WHERE id_dosen = :id AND id_smt = :smt AND status_verifikasi IN ('draf', 'disesuaikan')";
    public const LOG = "INSERT INTO log_aktivitas (pelaku, aksi, objek) VALUES (:pelaku, :aksi, :objek)";
}
