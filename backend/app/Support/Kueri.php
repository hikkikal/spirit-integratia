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
    public const USER_PROFIL = "SELECT username, nama, email, id_dosen FROM users WHERE id_user = :id";
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
    public const PERHITUNGAN = "SELECT p.id_perhitungan, d.nama_sdm AS nama, g.golongan, p.sks_wajib, p.sks_terlaksana, p.sks_lebih,
        p.sks_100, p.sks_50, p.sks_0, p.bruto, p.pajak, p.netto, p.status_verifikasi, p.catatan
        FROM perhitungan p JOIN dosen d USING (id_dosen) LEFT JOIN v_dosen_golongan g USING (id_dosen) WHERE p.id_dosen = :id AND p.id_smt = :smt";
    public const DETAIL_KELAS = "SELECT no, mk, kelas, jenis_kelas, sks_kelas, sks_wajib_terpakai, sks_100, sks_50, sks_0,
        tarif_per_sks, tarif_pph, bruto, pajak, netto, prodi, jenjang FROM v_detail_honor_kelas WHERE id_dosen = :id AND id_smt = :smt ORDER BY no";

    public const TARIF_NP_UBAH = "UPDATE tarif_nonpengajaran SET tarif_iv = :iv, tarif_iii = :iii, keterangan = :ket WHERE kode = :kode";
    public const TARIF_NP_BY_KODE = "SELECT kode, nama, satuan, tarif_iv, tarif_iii, keterangan FROM tarif_nonpengajaran WHERE kode = :kode";
    public const TARIF_NP = "SELECT kode, nama, satuan, tarif_iv, tarif_iii, keterangan FROM tarif_nonpengajaran ORDER BY urutan";

    // Non-pengajaran (bimbingan, penguji, magang)
    public const NP_RINGKAS = "SELECT count(*) AS dosen, count(*) FILTER (WHERE status_verifikasi = 'terverifikasi') AS terverifikasi,
        coalesce(sum(bruto) FILTER (WHERE status_verifikasi = 'terverifikasi'), 0) AS bruto_verif, coalesce(sum(bruto), 0) AS bruto,
        coalesce(sum(pajak), 0) AS pajak, coalesce(sum(netto), 0) AS netto FROM v_np_honor WHERE id_smt = :smt";
    /** Daftar non-pengajaran per dosen; kolom flag berisi kunci Perhatian (dipisah koma). Bukan konstanta karena memakai Perhatian::sqlFlagNp(). */
    public static function npDaftar(): string
    {
        return "SELECT v.id_dosen, v.nama, v.jabatan, v.jurusan, v.jumlah_kegiatan, v.bruto, v.jenis_gabung, v.status_verifikasi, v.simpulan,
        (SELECT " . Perhatian::sqlFlagNp() . " FROM kegiatan_nonpengajaran k WHERE k.id_dosen = v.id_dosen AND k.id_smt = v.id_smt) AS flag
        FROM v_nonpengajaran_dosen v WHERE v.id_smt = :smt AND (:jur1 = '' OR v.jurusan = :jur2)
        AND ((:jn1 = '' AND :q1 = '') OR EXISTS (SELECT 1 FROM kegiatan_nonpengajaran k WHERE k.id_dosen = v.id_dosen AND k.id_smt = v.id_smt
             AND (:jn2 = '' OR k.jenis = :jn3) AND (:q2 = '' OR k.judul ILIKE '%' || :q3 || '%')))
        ORDER BY v.nama";
    }
    public const NP_KEGIATAN = "SELECT urutan, judul, jenis, kategori, program_studi, perguruan_tinggi, honor_bruto, stat_bkd, jumlah_mhs, catatan, detail
        FROM kegiatan_nonpengajaran WHERE id_dosen = :id AND id_smt = :smt ORDER BY urutan";
    public const NP_STATUS = "SELECT status_verifikasi FROM verifikasi_nonpengajaran WHERE id_dosen = :id AND id_smt = :smt";
    public const NP_VERIFIKASI = "INSERT INTO verifikasi_nonpengajaran (id_dosen, id_smt, status_verifikasi, diverifikasi_oleh, diverifikasi_pada)
        SELECT CAST(:id1 AS uuid), CAST(:smt1 AS char(5)), 'terverifikasi', CAST(:pengguna AS uuid), now()
        WHERE EXISTS (SELECT 1 FROM kegiatan_nonpengajaran WHERE id_dosen = CAST(:id2 AS uuid) AND id_smt = CAST(:smt2 AS char(5)))
        ON CONFLICT (id_dosen, id_smt) DO UPDATE SET status_verifikasi = 'terverifikasi', diverifikasi_oleh = EXCLUDED.diverifikasi_oleh,
            diverifikasi_pada = EXCLUDED.diverifikasi_pada WHERE verifikasi_nonpengajaran.status_verifikasi <> 'terverifikasi'";

    // Tarif dan ketentuan
    public const TARIF_AKTIF = "SELECT jenis_kelas, golongan, tarif_per_sks, berlaku_mulai, dokumen, tentang FROM v_tarif_aktif ORDER BY jenis_kelas";
    public const PAJAK = "SELECT golongan, tarif_pph, catatan FROM pengaturan_pajak ORDER BY golongan";
    public const ATURAN = "SELECT kunci, nilai FROM pengaturan ORDER BY kunci";

    // Keuangan dan pimpinan
    public const DASHBOARD = "SELECT jurusan, jumlah_dosen, terverifikasi, sks_lebih, bruto, pajak, netto
        FROM v_dashboard_jurusan WHERE id_smt = :smt ORDER BY jurusan";
    public const HONOR_TERVERIFIKASI = "SELECT coalesce(sum(bruto) FILTER (WHERE status_verifikasi IN ('terverifikasi', 'terkunci')), 0) AS n
        FROM v_data_honor WHERE id_smt = :smt";
    public const DATA_HONOR = "SELECT id_dosen, nama, jabatan, golongan, jurusan, sks_wajib, sks_terlaksana, sks_lebih, bruto, pajak, netto, status_verifikasi, simpulan, catatan
        FROM v_data_honor WHERE id_smt = :smt AND (:jur1 = '' OR jurusan = :jur2) AND (:q1 = '' OR nama ILIKE '%' || :q2 || '%')
        ORDER BY nama";
    public const VERIFIKASI = "UPDATE perhitungan SET status_verifikasi = 'terverifikasi', diverifikasi_oleh = :pengguna, diverifikasi_pada = now()
        WHERE id_dosen = :id AND id_smt = :smt AND status_verifikasi IN ('draf', 'disesuaikan')";
    public const LOG = "INSERT INTO log_aktivitas (pelaku, aksi, objek, peran, status) VALUES (:pelaku, :aksi, :objek, :peran, :status)";

    // Admin Input: sinkronisasi SISTER
    public const SINKRON_KONEKSI = "SELECT s.status, s.mulai, u.nama AS oleh FROM sinkronisasi s LEFT JOIN users u ON u.id_user = s.dijalankan_oleh
        WHERE s.status <> 'berjalan' ORDER BY s.mulai DESC LIMIT 1";
    public const SINKRON_RIWAYAT = "SELECT id_sinkron, mulai, status FROM sinkronisasi WHERE status <> 'berjalan' ORDER BY mulai DESC LIMIT 50";
    public const SINKRON_BERJALAN = "SELECT s.id_sinkron, s.simulasi_gagal, s.mode, s.tahap, s.endpoint_total,
        (SELECT count(*) FROM sinkronisasi_endpoint e WHERE e.id_sinkron = s.id_sinkron)::int AS ep_selesai,
        EXTRACT(EPOCH FROM (now() - s.mulai))::float AS lewat,
        EXTRACT(EPOCH FROM (now() - coalesce(s.terakhir_aktif, s.mulai)))::float AS diam FROM sinkronisasi s WHERE s.status = 'berjalan'";
    public const SINKRON_DENYUT = "UPDATE sinkronisasi SET terakhir_aktif = now() WHERE id_sinkron = CAST(:id AS uuid) AND status = 'berjalan'";
    public const SINKRON_BARU = "INSERT INTO sinkronisasi (id_sinkron, id_smt, dijalankan_oleh, simulasi_gagal, mode) VALUES (:id, :smt, :oleh, CAST(:sim AS boolean), :mode)";
    public const SINKRON_TAHAP = "UPDATE sinkronisasi SET tahap = :tahap, endpoint_total = coalesce(CAST(:total AS smallint), endpoint_total) WHERE id_sinkron = :id AND status = 'berjalan'";
    public const SINKRON_EP_KET = "UPDATE sinkronisasi_endpoint SET keterangan = :ket WHERE id_sinkron = :id AND no = :no";
    public const PERIODE_SINKRON = "SELECT id_smt, label, terkunci FROM periode WHERE id_smt = :id";
    public const PERIODE_SINKRON_AWAL = "SELECT id_smt, label, terkunci FROM periode WHERE NOT terkunci ORDER BY id_smt DESC LIMIT 1";
    public const SINKRON_SELESAI = "UPDATE sinkronisasi SET status = :status, selesai = now(), durasi_detik = :durasi, pesan_gagal = :pesan
        WHERE id_sinkron = :id AND status = 'berjalan'";
    public const SINKRON_EP_TAMBAH = "INSERT INTO sinkronisasi_endpoint (id_sinkron, no, nama, path, status, detik, rekord, keterangan)
        VALUES (:id, :no, :nama, :path, :status, :detik, :rekord, :ket)";
    public const SINKRON_ATR_TAMBAH = "INSERT INTO sinkronisasi_atribut (id_sinkron, no, nama, urut, status) VALUES (:id, :no, :nama, :urut, :status)";
    public const SINKRON_BY_ID = "SELECT s.id_sinkron, s.mulai, s.status, s.durasi_detik, s.pesan_gagal, s.id_smt, s.mode, s.tahap, s.endpoint_total, p.label
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
    // Sinkronisasi SISTER sungguhan (App\Support\SinkronSister). Semua dijalankan dalam satu transaksi di tahap penyimpanan.
    public const SY_POLA = "SELECT tipe, pola FROM pola_kelas_kerja_sama";
    public const SY_JABFUNG = "INSERT INTO jabatan_fungsional (id_jabfung, nama_jabfung) VALUES (:id, :nama)
        ON CONFLICT (id_jabfung) DO UPDATE SET nama_jabfung = EXCLUDED.nama_jabfung";
    // golongan dan id_jurusan tidak ada di SISTER, jadi tidak pernah ditimpa. id_jabfung lama dipertahankan bila SISTER tidak memberi yang baru.
    public const SY_DOSEN = "INSERT INTO dosen (id_dosen, nama_sdm, nidn, nip, nuptk, jenis_sdm, status_aktif, status_pegawai, id_jabfung, waktu_data_update)
        VALUES (CAST(:id AS uuid), :nama, :nidn, :nip, :nuptk, :jenis, :aktif, :pegawai,
                (SELECT id_jabfung FROM jabatan_fungsional WHERE id_jabfung = :jab), CAST(:waktu AS timestamp))
        ON CONFLICT (id_dosen) DO UPDATE SET nama_sdm = EXCLUDED.nama_sdm, nidn = EXCLUDED.nidn, nip = EXCLUDED.nip, nuptk = EXCLUDED.nuptk,
            jenis_sdm = EXCLUDED.jenis_sdm, status_aktif = EXCLUDED.status_aktif, status_pegawai = EXCLUDED.status_pegawai,
            id_jabfung = CASE WHEN dosen.jabfung_manual THEN dosen.id_jabfung ELSE coalesce(EXCLUDED.id_jabfung, dosen.id_jabfung) END, waktu_data_update = EXCLUDED.waktu_data_update";
    public const SY_PENGAJARAN = "INSERT INTO pengajaran (id_pengajaran, id_dosen, id_smt, id_kelas, kode_matakuliah, mata_kuliah, kelas, jenis_kelas,
            program_studi, jenjang_pendidikan, sks, sks_mata_kuliah, tatap_muka_rencana, tatap_muka_realisasi, jumlah_mahasiswa)
        VALUES (CAST(:id AS uuid), CAST(:dosen AS uuid), CAST(:smt AS char(5)), CAST(:idkelas AS uuid), :kode, :mk, :kelas, :jenis,
            :prodi, :jenjang, :sks, :sksmk, :tmr, :tmrl, :mhs)
        ON CONFLICT (id_pengajaran) DO UPDATE SET id_dosen = EXCLUDED.id_dosen, id_smt = EXCLUDED.id_smt, id_kelas = EXCLUDED.id_kelas,
            kode_matakuliah = EXCLUDED.kode_matakuliah, mata_kuliah = EXCLUDED.mata_kuliah, kelas = EXCLUDED.kelas, jenis_kelas = EXCLUDED.jenis_kelas,
            program_studi = EXCLUDED.program_studi, jenjang_pendidikan = EXCLUDED.jenjang_pendidikan, sks = EXCLUDED.sks,
            sks_mata_kuliah = EXCLUDED.sks_mata_kuliah, tatap_muka_rencana = EXCLUDED.tatap_muka_rencana,
            tatap_muka_realisasi = EXCLUDED.tatap_muka_realisasi, jumlah_mahasiswa = EXCLUDED.jumlah_mahasiswa";
    // Jurusan dosen dari tabel program_studi (nama prodi pada kelas yang diajar). Hanya mengisi yang masih kosong. Dosen lintas jurusan: jurusan dengan kelas TERBANYAK;
    // bila seri, dibiarkan kosong (tidak ditebak). Isi manual di dosen.id_jurusan tidak pernah ditimpa.
    public const SY_JURUSAN = "WITH t AS (SELECT p.id_dosen, ps.id_jurusan, count(*) AS n FROM pengajaran p
            JOIN program_studi ps ON lower(ps.nama_prodi) = lower(trim(p.program_studi)) WHERE p.id_dosen = CAST(:dosen AS uuid) GROUP BY p.id_dosen, ps.id_jurusan),
        w AS (SELECT t.id_dosen, min(t.id_jurusan::text)::uuid AS id_jurusan, count(*) AS c FROM t WHERE t.n = (SELECT max(n) FROM t) GROUP BY t.id_dosen)
        UPDATE dosen d SET id_jurusan = w.id_jurusan FROM w WHERE d.id_dosen = w.id_dosen AND d.id_jurusan IS NULL AND w.c = 1";
    public const SY_TUGAS_HAPUS = "DELETE FROM tugas_tambahan_dosen WHERE id_dosen = CAST(:dosen AS uuid) AND sumber = 'sister'";   // tugas manual (koreksi) dipertahankan
    public const SY_TUGAS_TAMBAH = "INSERT INTO tugas_tambahan_dosen (id_tugas, id_dosen, jenis_tugas, unit_kerja, id_kategori_kegiatan, tanggal_mulai, tanggal_selesai)
        VALUES (CAST(:id AS uuid), CAST(:dosen AS uuid), :jenis, :unit, CAST(:kat AS integer), CAST(:mulai AS date), CAST(:selesai AS date))
        ON CONFLICT (id_tugas) DO UPDATE SET id_dosen = EXCLUDED.id_dosen, jenis_tugas = EXCLUDED.jenis_tugas, unit_kerja = EXCLUDED.unit_kerja,
            id_kategori_kegiatan = EXCLUDED.id_kategori_kegiatan, tanggal_mulai = EXCLUDED.tanggal_mulai, tanggal_selesai = EXCLUDED.tanggal_selesai";
    public const SY_PETA = "SELECT lower(jenis_tugas) AS jenis FROM pemetaan_tugas_tambahan";
    public const SY_BKD_HAPUS = "DELETE FROM bkd_pengajaran WHERE id_dosen = CAST(:dosen AS uuid) AND id_smt = CAST(:smt AS char(5))";
    public const SY_BKD_TAMBAH = "INSERT INTO bkd_pengajaran (id_bkd, id_dosen, id_smt, id_katgiat, unsur, judul_keg, nm_kat, beban_sks, nilai, stat_kegiatan)
        VALUES (CAST(:id AS uuid), CAST(:dosen AS uuid), CAST(:smt AS char(5)), :katgiat, :unsur, :judul, :kat, :beban, :nilai, :stat)";
    public const SY_BKDAKHIR = "INSERT INTO bkd_akhir (id_bkd_akhir, id_dosen, id_smt, id_reg_ptk, sks_kinerja_ajar, sks_lebih_ajar, id_jabfung, stat_kewajiban, simpulan_asesor)
        VALUES (CAST(:id AS uuid), CAST(:dosen AS uuid), CAST(:smt AS char(5)), CAST(:reg AS uuid), :kinerja, :lebih,
                (SELECT id_jabfung FROM jabatan_fungsional WHERE id_jabfung = :jab), :stat, :simpulan)
        ON CONFLICT (id_dosen, id_smt) DO UPDATE SET id_reg_ptk = EXCLUDED.id_reg_ptk, sks_kinerja_ajar = EXCLUDED.sks_kinerja_ajar,
            sks_lebih_ajar = EXCLUDED.sks_lebih_ajar, id_jabfung = EXCLUDED.id_jabfung, stat_kewajiban = EXCLUDED.stat_kewajiban,
            simpulan_asesor = EXCLUDED.simpulan_asesor";
    public const SY_NP_TANDA = "SELECT coalesce(md5(string_agg(id_sumber || '|' || kategori || '|' || judul, ',' ORDER BY id_sumber, kategori)), '') AS tanda
        FROM kegiatan_nonpengajaran WHERE id_dosen = CAST(:dosen AS uuid) AND id_smt = CAST(:smt AS char(5)) AND sumber <> 'manual'";
    public const SY_NP_HAPUS = "DELETE FROM kegiatan_nonpengajaran WHERE id_dosen = CAST(:dosen AS uuid) AND id_smt = CAST(:smt AS char(5)) AND sumber <> 'manual'";
    public const SY_NP_TAMBAH = "INSERT INTO kegiatan_nonpengajaran (id_kegiatan, id_dosen, id_smt, urutan, judul, jenis, kategori, program_studi, perguruan_tinggi, honor_bruto, id_sumber, sumber, stat_bkd, jumlah_mhs, detail)
        VALUES (CAST(:id AS uuid), CAST(:dosen AS uuid), CAST(:smt AS char(5)), :urutan, :judul, :jenis, :kategori, :prodi, :pt, NULL, :sumber_id, :sumber, :stat, :mhs, CAST(:detail AS jsonb))";
    // Data kegiatan berubah setelah diverifikasi: verifikasi dikembalikan ke "menunggu" supaya Keuangan memeriksa lagi.
    public const SY_NP_RESET = "DELETE FROM verifikasi_nonpengajaran WHERE id_dosen = CAST(:dosen AS uuid) AND id_smt = CAST(:smt AS char(5)) AND status_verifikasi = 'terverifikasi'";

    public const LOG_AKTIVITAS = "SELECT l.waktu, u.nama, l.peran, l.aksi, l.objek, l.status FROM log_aktivitas l LEFT JOIN users u ON u.id_user = l.pelaku
        WHERE (CAST(:hari1 AS integer) = 0 OR l.waktu >= now() - make_interval(days => CAST(:hari2 AS integer)))
        AND (:status1 = '' OR l.status = :status2) AND (:aksi1 = '' OR l.aksi = :aksi2) AND (:q1 = '' OR u.nama ILIKE '%' || :q2 || '%' OR u.username ILIKE '%' || :q3 || '%')
        ORDER BY l.waktu DESC, l.id_log DESC LIMIT 500";
    public const LOG_AKSI = "SELECT DISTINCT aksi FROM log_aktivitas ORDER BY aksi";
}
