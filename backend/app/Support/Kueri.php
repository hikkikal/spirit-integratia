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
    public const USER_BY_SSO = "SELECT id_user, username, nama, id_dosen, status_aktif FROM users WHERE metode_login = 'sso' AND (username = :u1 OR lower(email) = lower(:u2)) ORDER BY (username = :u3) DESC LIMIT 1";
    public const USER_LOKAL = "SELECT id_user, username, nama, id_dosen, status_aktif, password_hash FROM users
        WHERE metode_login = 'lokal' AND (username = :u1 OR lower(email) = lower(:u2)) ORDER BY (username = :u3) DESC LIMIT 1";
    public const ROLES_USER = "SELECT r.kode FROM user_roles ur JOIN roles r USING (id_role) WHERE ur.id_user = :id_user ORDER BY r.id_role";

    // Dosen
    public const PROFIL = "SELECT v.nama, v.jabatan, v.jurusan, v.jenis_sdm, v.status_pegawai, v.nidn_samar, v.nip_samar, v.nuptk_samar,
        (SELECT u.email FROM users u WHERE u.id_dosen = v.id_dosen ORDER BY (u.metode_login = 'lokal'), u.username LIMIT 1) AS email
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
    public const LOG = "INSERT INTO log_aktivitas (pelaku, aksi, objek, peran, status) VALUES (:pelaku, :aksi, :objek, :peran, :status)";

    // Admin Input: sinkronisasi SISTER
    public const SINKRON_KONEKSI = "SELECT s.status, s.mulai, u.nama AS oleh FROM sinkronisasi s LEFT JOIN users u ON u.id_user = s.dijalankan_oleh
        WHERE s.status <> 'berjalan' ORDER BY s.mulai DESC LIMIT 1";
    public const SINKRON_RIWAYAT = "SELECT id_sinkron, mulai, status FROM sinkronisasi WHERE status <> 'berjalan' ORDER BY mulai DESC LIMIT 50";
    public const SINKRON_BERJALAN = "SELECT id_sinkron, simulasi_gagal, EXTRACT(EPOCH FROM (now() - mulai))::float AS lewat FROM sinkronisasi WHERE status = 'berjalan'";
    public const SINKRON_BARU = "INSERT INTO sinkronisasi (id_sinkron, id_smt, dijalankan_oleh, simulasi_gagal) VALUES (:id, :smt, :oleh, CAST(:sim AS boolean))";
    public const SINKRON_SELESAI = "UPDATE sinkronisasi SET status = :status, selesai = now(), durasi_detik = :durasi, pesan_gagal = :pesan
        WHERE id_sinkron = :id AND status = 'berjalan'";
    public const SINKRON_EP_TAMBAH = "INSERT INTO sinkronisasi_endpoint (id_sinkron, no, nama, path, status, detik, rekord, keterangan)
        VALUES (:id, :no, :nama, :path, :status, :detik, :rekord, :ket)";
    public const SINKRON_ATR_TAMBAH = "INSERT INTO sinkronisasi_atribut (id_sinkron, no, nama, urut, status) VALUES (:id, :no, :nama, :urut, :status)";
    public const SINKRON_BY_ID = "SELECT s.id_sinkron, s.mulai, s.status, s.durasi_detik, s.pesan_gagal, s.id_smt, p.label
        FROM sinkronisasi s LEFT JOIN periode p USING (id_smt) WHERE s.id_sinkron = :id";
    public const SINKRON_EP = "SELECT no, nama, path, status, detik, rekord, keterangan FROM sinkronisasi_endpoint WHERE id_sinkron = :id ORDER BY no";
    public const SINKRON_ATR = "SELECT no, nama, status FROM sinkronisasi_atribut WHERE id_sinkron = :id ORDER BY no, urut";

    // Admin Input: master tarif (hanya baris per jabatan; tarif umum dipakai perhitungan dan tidak ditampilkan di sini)
    public const TARIF_ADMIN = "SELECT id_tarif, jenis_kelas, nama_kelas, jabatan, tarif_per_sks, berlaku_mulai, berlaku_sampai
        FROM master_tarif WHERE jabatan IS NOT NULL AND aktif ORDER BY jenis_kelas, nama_kelas, id_tarif";
    public const TARIF_ADMIN_BY_ID = "SELECT id_tarif, jenis_kelas, nama_kelas, jabatan, tarif_per_sks, berlaku_mulai, berlaku_sampai
        FROM master_tarif WHERE id_tarif = :id AND jabatan IS NOT NULL AND aktif";
    public const TARIF_TUMPANG_TINDIH = "SELECT 1 FROM master_tarif WHERE aktif AND jenis_kelas = :jenis AND nama_kelas = :kelas AND jabatan = :jabatan
        AND berlaku_mulai <= :sampai AND berlaku_sampai >= :mulai AND id_tarif <> :id LIMIT 1";
    public const TARIF_TAMBAH = "INSERT INTO master_tarif (jenis_kelas, golongan, tarif_per_sks, berlaku_mulai, berlaku_sampai, nama_kelas, jabatan, catatan)
        VALUES (:jenis, :gol, :honor, :mulai, :sampai, :kelas, :jabatan, 'Diinput Admin Input') RETURNING id_tarif";
    public const TARIF_UBAH = "UPDATE master_tarif SET jenis_kelas = :jenis, golongan = :gol, tarif_per_sks = :honor, berlaku_mulai = :mulai,
        berlaku_sampai = :sampai, nama_kelas = :kelas, jabatan = :jabatan WHERE id_tarif = :id AND jabatan IS NOT NULL AND aktif RETURNING id_tarif";

    // Admin Input: dokumen Perdir
    public const PERDIR_SEMUA = "SELECT id_dokumen, nomor, tentang, tanggal, nama_file_asli, berlaku FROM perdir_dokumen
        ORDER BY tanggal DESC NULLS LAST, id_dokumen DESC";
    public const PERDIR_BY_ID = "SELECT id_dokumen, nomor, tentang, tanggal, nama_file_asli, nama_file_server, berlaku FROM perdir_dokumen WHERE id_dokumen = :id";
    public const PERDIR_TAMBAH = "INSERT INTO perdir_dokumen (nomor, tentang, tanggal, nama_file_asli, nama_file_server, berlaku)
        VALUES (:nomor, :judul, :tanggal, :asli, :server, true) RETURNING id_dokumen";
    public const PERDIR_BERLAKU = "UPDATE perdir_dokumen SET berlaku = CAST(:berlaku AS boolean) WHERE id_dokumen = :id";
    public const PERDIR_DIPAKAI = "SELECT count(*)::int AS n FROM master_tarif WHERE id_dokumen = :id";
    public const PERDIR_HAPUS = "DELETE FROM perdir_dokumen WHERE id_dokumen = :id";

    // Admin Super: pengguna, peran, log
    public const PENGGUNA_SEMUA = "SELECT u.username, u.nama, u.email, u.status_aktif,
        coalesce(string_agg(r.kode, ',' ORDER BY r.id_role), '') AS roles,
        (SELECT max(l.waktu) FROM log_aktivitas l WHERE l.pelaku = u.id_user AND l.aksi LIKE 'Login%' AND l.status = 'berhasil') AS login_terakhir
        FROM users u LEFT JOIN user_roles ur USING (id_user) LEFT JOIN roles r USING (id_role)
        GROUP BY u.id_user, u.username, u.nama, u.email, u.status_aktif ORDER BY u.nama, u.username";
    public const PENGGUNA_BY_USERNAME = "SELECT id_user, username, nama, id_dosen, status_aktif FROM users WHERE username = :username";
    public const ROLE_SEMUA = "SELECT id_role, kode FROM roles ORDER BY id_role";
    public const ROLE_TAMBAH = "INSERT INTO user_roles (id_user, id_role) VALUES (:id_user, :id_role) ON CONFLICT DO NOTHING";
    public const ROLE_HAPUS = "DELETE FROM user_roles WHERE id_user = :id_user AND id_role = :id_role";
    public const SUPER_AKTIF_LAIN = "SELECT count(*)::int AS n FROM users u JOIN user_roles ur USING (id_user) JOIN roles r USING (id_role)
        WHERE r.kode = 'admin_super' AND u.status_aktif AND u.id_user <> :id_user";
    public const STATUS_PENGGUNA = "UPDATE users SET status_aktif = CAST(:aktif AS boolean) WHERE id_user = :id_user";
    public const LOG_AKTIVITAS = "SELECT l.waktu, u.nama, l.peran, l.aksi, l.status FROM log_aktivitas l LEFT JOIN users u ON u.id_user = l.pelaku
        WHERE (CAST(:hari1 AS integer) = 0 OR l.waktu >= now() - make_interval(days => CAST(:hari2 AS integer)))
        AND (:status1 = '' OR l.status = :status2) AND (:q1 = '' OR u.nama ILIKE '%' || :q2 || '%' OR u.username ILIKE '%' || :q3 || '%')
        ORDER BY l.waktu DESC, l.id_log DESC LIMIT 500";
}
