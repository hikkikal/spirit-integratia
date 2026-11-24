-- INTEGRATIA PNJ - skema basis data (PostgreSQL 14+)
-- Status: rancangan kerja. Belum menggantikan ERD revisi analyst; sesuaikan setelah ERD final.
-- Cakupan: halaman Dosen (riwayat, rincian kelas, detail honor), Keuangan/Pimpinan (dashboard, data dosen,
-- detail honor, laporan, tarif & ketentuan), Admin Input (sinkronisasi SISTER, master tarif, Perdir), Admin Super.
--
-- Jalankan SEKALI pada database kosong:   psql -d integratia -f schema.sql -f seed.sql
-- Berkas ini TIDAK menghapus apa pun (aman terhadap data hasil sinkronisasi). Untuk mengulang dari nol pakai reset.sql.
-- Jangan jalankan `php artisan migrate`: tabel users milik kita dibuat di sini.

-- ============ Pengguna dan peran (login SSO; peran disimpan di basis data sendiri) ============
CREATE TABLE roles (
    id_role      smallint PRIMARY KEY,
    kode         text NOT NULL UNIQUE,          -- dosen, keuangan, pimpinan, admin_input, admin_super
    nama_role    text NOT NULL
);

-- ============ Referensi ============
CREATE TABLE jurusan (
    id_jurusan    uuid PRIMARY KEY,
    nama_jurusan  text NOT NULL UNIQUE
);

CREATE TABLE program_studi (
    id_prodi      serial PRIMARY KEY,
    nama_prodi    text NOT NULL,
    jenjang       text NOT NULL,                -- D3, D4, S2, dst.
    id_jurusan    uuid NOT NULL REFERENCES jurusan,
    UNIQUE (nama_prodi, jenjang)
);

CREATE TABLE jabatan_fungsional (
    id_jabfung    text PRIMARY KEY,             -- id SISTER (angka, mis. "48"; data contoh berbentuk UUID)
    nama_jabfung  text NOT NULL,                -- mis. "Lektor"
    angka_kredit  numeric(7,2)
);

-- Golongan untuk pajak diturunkan dari jabatan fungsional (aturan Keuangan: Gol IV 15%, Gol III 5%). Dicocokkan dengan AWALAN nama jabatan SISTER
-- (mis. "Lektor Kepala (400.00)" cocok dengan "Lektor Kepala"); awalan terpanjang menang. Jabatan yang tidak ada di sini (mis. Tenaga Pengajar)
-- berarti golongan TIDAK diketahui: jangan ditebak, tanyakan ke Keuangan atau isi dosen.golongan manual.
CREATE TABLE pemetaan_jabfung_golongan (
    awalan_jabfung text PRIMARY KEY,
    golongan       text NOT NULL CHECK (golongan IN ('III','IV')),
    catatan        text
);

CREATE TABLE periode (
    id_smt           char(5) PRIMARY KEY,       -- id semester SISTER, mis. 20252
    label            text NOT NULL UNIQUE,      -- "Genap 2025/2026"
    semester         text NOT NULL CHECK (semester IN ('Ganjil','Genap')),
    tahun_ajaran     text NOT NULL,
    tanggal_mulai    date,                      -- awal semester; boleh diisi Admin. (Perhitungan kini memakai 1 Sep / 1 Mar sebagai tanggal acuan tarif)
    tanggal_selesai  date,
    terkunci         boolean NOT NULL DEFAULT false
);

-- ============ Dosen dan data sumber SISTER ============
CREATE TABLE dosen (
    id_dosen            uuid PRIMARY KEY,       -- = id_sdm SISTER
    nama_sdm            text NOT NULL,
    nidn                text,
    nip                 text,
    nuptk               text,
    jenis_sdm           text NOT NULL,
    status_aktif        text NOT NULL,
    status_pegawai      text,
    id_jabfung          text REFERENCES jabatan_fungsional,
    id_jurusan          uuid REFERENCES jurusan,   -- sumber di luar SISTER (lihat catatan analyst)
    golongan            text,                      -- III / IV. SISTER tidak memberi golongan; isi manual hanya bila perlu menimpa turunan dari jabatan (lihat v_dosen_golongan)
    waktu_data_update   timestamp
);

CREATE TABLE pengajaran (
    id_pengajaran         uuid PRIMARY KEY,
    id_dosen              uuid NOT NULL REFERENCES dosen,
    id_smt                char(5) NOT NULL REFERENCES periode,
    id_kelas              uuid,
    kode_matakuliah       text,
    mata_kuliah           text NOT NULL,
    kelas                 text NOT NULL,
    jenis_kelas           text NOT NULL DEFAULT 'Reguler' CHECK (jenis_kelas IN ('Reguler','Kerja Sama')),
    program_studi         text,
    jenjang_pendidikan    text,
    sks                   numeric(5,2) NOT NULL,   -- SKS substansi bagian dosen ini
    sks_mata_kuliah       numeric(5,2),
    tatap_muka_rencana    integer,
    tatap_muka_realisasi  integer,
    jumlah_mahasiswa      integer
);
CREATE INDEX ON pengajaran (id_dosen, id_smt);

CREATE TABLE bkd_pengajaran (
    id_bkd         uuid PRIMARY KEY,
    id_dosen       uuid NOT NULL REFERENCES dosen,
    id_smt         char(5) NOT NULL REFERENCES periode,
    id_katgiat     integer,
    unsur          text,
    judul_keg      text,
    nm_kat         text,
    beban_sks      numeric(7,4),
    nilai          numeric(12,6),
    stat_kegiatan  text                         -- Selesai / Beban lebih / Berlanjut
);
CREATE INDEX ON bkd_pengajaran (id_dosen, id_smt);

CREATE TABLE bkd_akhir (
    id_bkd_akhir      uuid PRIMARY KEY,
    id_dosen          uuid NOT NULL REFERENCES dosen,
    id_smt            char(5) NOT NULL REFERENCES periode,
    id_reg_ptk        uuid,
    sks_kinerja_ajar  numeric(7,4),
    sks_lebih_ajar    numeric(7,4),
    id_jabfung        text REFERENCES jabatan_fungsional,
    stat_kewajiban    text,
    simpulan_asesor   text,
    UNIQUE (id_dosen, id_smt)
);

-- ============ Tarif, pajak, dokumen Perdir ============
CREATE TABLE perdir_dokumen (
    id_dokumen       serial PRIMARY KEY,
    nomor            text NOT NULL UNIQUE,
    tentang          text NOT NULL,
    nama_file_asli   text,                       -- hanya untuk tampilan
    nama_file_server text,                       -- nama acak di server
    berlaku          boolean NOT NULL DEFAULT true,
    tanggal          date,                       -- tanggal penetapan / berlaku mulai (istilah di Figma belum seragam)
    diunggah_pada    timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE master_tarif (
    id_tarif        serial PRIMARY KEY,
    id_dokumen      integer REFERENCES perdir_dokumen,   -- boleh kosong untuk tarif yang diinput Admin Input
    jenis_kelas     text NOT NULL CHECK (jenis_kelas IN ('Reguler','Kerja Sama')),
    golongan        text,                        -- NULL = berlaku untuk semua golongan
    tarif_per_sks   integer NOT NULL CHECK (tarif_per_sks >= 0),
    berlaku_mulai   date,
    aktif           boolean NOT NULL DEFAULT true,
    catatan         text,
    -- Tarif per kelas dan jabatan (halaman Master Tarif Admin Input). Baris dengan jabatan NULL adalah
    -- tarif umum yang dipakai perhitungan sementara (lihat v_tarif_aktif).
    nama_kelas      text,                        -- mis. "Teknik Informatika - CCIT" atau "Reguler"
    mitra           text,                        -- mitra kerja sama, mis. "MSU", "CCIT" (NULL untuk Reguler). Dipakai mencocokkan kelas kerja sama dengan tarif
    jabatan         text,                        -- mis. "Lektor III/c-Penata" atau "Lektor ---" (--- = semua golongan)
    berlaku_sampai  date,
    CHECK (berlaku_sampai IS NULL OR berlaku_mulai IS NULL OR berlaku_sampai >= berlaku_mulai)
    -- Mengubah tarif tidak mengubah perhitungan lama: detail_perhitungan menyimpan salinan tarif_per_sks.
);

CREATE TABLE pengaturan_pajak (
    golongan   text PRIMARY KEY,
    tarif_pph  numeric(5,4) NOT NULL CHECK (tarif_pph >= 0 AND tarif_pph < 1),
    catatan    text
);

CREATE TABLE pengaturan (
    kunci   text PRIMARY KEY,
    nilai   text NOT NULL,
    catatan text
);

-- Tugas tambahan dosen dari SISTER (/tugas_tambahan). Menentukan SKS wajib: dosen biasa (DS) 10, dosen dengan tugas tambahan (DT) 4/5/6 menurut kelompoknya
-- (Perdir 11/2025 Pasal 8 dan Lampiran II; Kepdir 638/2026 Tabel 1). Tanggal dipakai untuk memeriksa tugas yang berlaku pada periode.
CREATE TABLE tugas_tambahan_dosen (
    id_tugas             uuid PRIMARY KEY,        -- id SISTER
    id_dosen             uuid NOT NULL REFERENCES dosen ON DELETE CASCADE,
    jenis_tugas          text NOT NULL,
    unit_kerja           text,
    id_kategori_kegiatan integer,
    tanggal_mulai        date,
    tanggal_selesai      date
);
CREATE INDEX ON tugas_tambahan_dosen (id_dosen);

-- Pemetaan jenis tugas tambahan (persis seperti tertulis di SISTER) ke kelompok Perdir: 1 = Direktur (wajib 4), 2 = Wakil Direktur, Ketua Jurusan, Ketua Pascasarjana,
-- Kepala PPMPP / P3M / SPI (wajib 5), 3 = Kepala Prodi, Sekretaris Jurusan, Kepala Lab/Bengkel/UPA, Sekretaris SPI/UPA, PPK, Staf Khusus Direktur, Asisten Wadir,
-- Kepala Kantor, Koordinator (wajib 6). Diisi oleh Admin: jenis yang belum dipetakan TIDAK ditebak, perhitungan dosennya ditunda sampai dipetakan.
CREATE TABLE pemetaan_tugas_tambahan (
    id_pemetaan serial PRIMARY KEY,
    jenis_tugas text NOT NULL,
    kelompok    smallint NOT NULL CHECK (kelompok BETWEEN 1 AND 3),
    catatan     text
);
CREATE UNIQUE INDEX ux_pemetaan_tugas ON pemetaan_tugas_tambahan (lower(jenis_tugas));

CREATE TABLE pola_kelas_kerja_sama (
    id_pola  serial PRIMARY KEY,
    tipe     text NOT NULL CHECK (tipe IN ('akhiran','awalan','mengandung')),
    pola     text NOT NULL,                  -- bagian kode kelas SISTER, mis. "MSU" pada kelas "6AMSU"
    mitra    text NOT NULL,                  -- mitra kerja sama pemilik pola, sama dengan master_tarif.mitra (mis. "MSU")
    UNIQUE (tipe, pola)
);

-- ============ Pengguna ============
CREATE TABLE users (
    id_user       uuid PRIMARY KEY,
    username      text NOT NULL UNIQUE,
    email         text,
    nama          text NOT NULL,
    id_dosen      uuid REFERENCES dosen,
    status_aktif  boolean NOT NULL DEFAULT true,
    metode_login  text NOT NULL DEFAULT 'sso' CHECK (metode_login IN ('sso','lokal')),
    password_hash text,                          -- hash bcrypt; kosong untuk akun SSO (password kampus ada di CAS, tidak pernah sampai ke sini)
    CHECK (metode_login <> 'lokal' OR password_hash IS NOT NULL)
);

CREATE TABLE user_roles (
    id_user  uuid NOT NULL REFERENCES users ON DELETE CASCADE,
    id_role  smallint NOT NULL REFERENCES roles,
    PRIMARY KEY (id_user, id_role)
);

-- ============ Kegiatan non-pengajaran (bimbingan, penguji, magang) ============
-- Honor bruto per kegiatan disimpan apa adanya (data contoh). Penghitungan dari tarif SBM SK 23/2026 belum dibuat.
-- Tarif honor non-pengajaran, SBM Internal PNJ 23/2026 (isinya di seed.sql)
CREATE TABLE tarif_nonpengajaran (
    kode       text PRIMARY KEY,
    nama       text NOT NULL,
    satuan     text NOT NULL,
    tarif_iv   integer NOT NULL CHECK (tarif_iv >= 0),
    tarif_iii  integer NOT NULL CHECK (tarif_iii >= 0),
    urutan     smallint NOT NULL,
    keterangan text
);
CREATE TABLE kegiatan_nonpengajaran (
    id_kegiatan      uuid PRIMARY KEY,
    id_dosen         uuid NOT NULL REFERENCES dosen,
    id_smt           char(5) NOT NULL REFERENCES periode,
    urutan           integer NOT NULL,
    judul            text NOT NULL,
    jenis            text NOT NULL CHECK (jenis IN ('Skripsi','Tesis','Tugas Akhir','Seminar Proposal','PKL/Magang','Pembimbing Akademik','Lainnya')),
    kategori         text NOT NULL,              -- nm_kat, mis. "Skripsi (pembimbing utama)"
    program_studi    text,
    perguruan_tinggi text,
    honor_bruto      integer CHECK (honor_bruto IS NULL OR honor_bruto >= 0),   -- NULL = belum dihitung (tarif SBM SK 23/2026 belum dipakai)
    id_sumber        text,                       -- id kegiatan di SISTER; NULL untuk data contoh atau input manual
    sumber           text NOT NULL DEFAULT 'manual' CHECK (sumber IN ('manual','sister_bimbingan','sister_pengujian','sister_tunjang')),
    jumlah_mhs       integer NOT NULL DEFAULT 1 CHECK (jumlah_mhs >= 1),   -- jumlah mahasiswa pada kegiatan (dari detail SISTER)
    kode_tarif       text REFERENCES tarif_nonpengajaran,
    catatan          text,                       -- alasan Rp0/NULL atau hal yang perlu dicek Keuangan
    detail           jsonb,                      -- ringkasan detail SISTER: mahasiswa, pembimbing, SK, tanggal
    stat_bkd         text                        -- status BKD di SISTER ("Beban lebih", ...); NULL = tidak ditemukan/tidak cocok. Honor hanya untuk "Beban lebih" (Kepdir 638/2026)
);
-- Sinkronisasi memperbarui baris yang sama, bukan menggandakannya.
CREATE UNIQUE INDEX ux_np_sumber ON kegiatan_nonpengajaran (id_dosen, id_smt, id_sumber, kategori) WHERE id_sumber IS NOT NULL;
CREATE INDEX ON kegiatan_nonpengajaran (id_dosen, id_smt);

-- Verifikasi Keuangan per dosen per periode. Tidak ada baris = menunggu verifikasi.
CREATE TABLE verifikasi_nonpengajaran (
    id_dosen          uuid NOT NULL REFERENCES dosen,
    id_smt            char(5) NOT NULL REFERENCES periode,
    status_verifikasi text NOT NULL DEFAULT 'menunggu' CHECK (status_verifikasi IN ('menunggu','terverifikasi')),
    diverifikasi_oleh uuid REFERENCES users,
    diverifikasi_pada timestamptz,
    PRIMARY KEY (id_dosen, id_smt)
);

-- ============ Perhitungan honor (header - detail) ============
CREATE TABLE perhitungan (
    id_perhitungan    uuid PRIMARY KEY,
    id_dosen          uuid NOT NULL REFERENCES dosen,
    id_smt            char(5) NOT NULL REFERENCES periode,
    sks_wajib         numeric(6,2) NOT NULL,
    sumber_sks_wajib  text NOT NULL DEFAULT 'default',     -- default / bkd / manual
    sks_terlaksana    numeric(6,2) NOT NULL,
    sks_lebih         numeric(6,2) NOT NULL,
    sks_100           numeric(6,2) NOT NULL DEFAULT 0,
    sks_50            numeric(6,2) NOT NULL DEFAULT 0,
    sks_0             numeric(6,2) NOT NULL DEFAULT 0,
    bruto             bigint NOT NULL,
    pajak             bigint NOT NULL,
    netto             bigint NOT NULL,
    status_verifikasi text NOT NULL DEFAULT 'draf'
        CHECK (status_verifikasi IN ('draf','disesuaikan','terverifikasi','terkunci')),
    diverifikasi_oleh uuid REFERENCES users,
    diverifikasi_pada timestamptz,
    dihitung_pada     timestamptz NOT NULL DEFAULT now(),
    catatan           text,                       -- tanda "perlu pengecekan": tugas tambahan belum dipetakan, golongan/jabatan kosong, tarif sementara
    UNIQUE (id_dosen, id_smt)
);

CREATE TABLE detail_perhitungan (
    id_detail       uuid PRIMARY KEY,
    id_perhitungan  uuid NOT NULL REFERENCES perhitungan ON DELETE CASCADE,
    id_pengajaran   uuid NOT NULL REFERENCES pengajaran,
    urutan          smallint NOT NULL,
    sks_kelas       numeric(5,2) NOT NULL,
    sks_wajib_terpakai numeric(5,2) NOT NULL DEFAULT 0,    -- bagian yang menutup kewajiban (tidak dibayar)
    sks_100         numeric(5,2) NOT NULL DEFAULT 0,
    sks_50          numeric(5,2) NOT NULL DEFAULT 0,
    sks_0           numeric(5,2) NOT NULL DEFAULT 0,
    jenis_kelas     text NOT NULL,
    id_tarif        integer REFERENCES master_tarif,
    tarif_per_sks   integer NOT NULL,                      -- salinan (snapshot) saat dihitung
    tarif_pph       numeric(5,4) NOT NULL,                 -- salinan (snapshot)
    bruto           bigint NOT NULL,
    pajak           bigint NOT NULL,
    netto           bigint NOT NULL,
    UNIQUE (id_perhitungan, id_pengajaran),
    CHECK (abs(sks_wajib_terpakai + sks_100 + sks_50 + sks_0 - sks_kelas) < 0.005)
);

CREATE TABLE penyesuaian_perhitungan (        -- koreksi manual (hasil sistem dan penyesuaian dipisah)
    id_penyesuaian  serial PRIMARY KEY,
    id_perhitungan  uuid NOT NULL REFERENCES perhitungan ON DELETE CASCADE,
    id_detail       uuid REFERENCES detail_perhitungan,
    kolom           text NOT NULL,
    nilai_lama      text,
    nilai_baru      text,
    alasan          text NOT NULL,
    oleh            uuid REFERENCES users,
    pada            timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE log_aktivitas (
    id_log    bigserial PRIMARY KEY,
    waktu     timestamptz NOT NULL DEFAULT now(),
    pelaku    uuid REFERENCES users,
    aksi      text NOT NULL,
    objek     text,
    status    text NOT NULL DEFAULT 'berhasil' CHECK (status IN ('berhasil','gagal')),
    peran     text                           -- peran aktif pelaku saat itu (kode frontend: dosen, keuangan, admin, ...)
);
CREATE INDEX ix_log_waktu ON log_aktivitas (waktu DESC);

-- ============ Sinkronisasi SISTER (riwayat dan log rinci) ============
CREATE TABLE sinkronisasi (
    id_sinkron     uuid PRIMARY KEY,
    mulai          timestamptz NOT NULL DEFAULT now(),
    selesai        timestamptz,
    status         text NOT NULL DEFAULT 'berjalan' CHECK (status IN ('berjalan','berhasil','gagal')),
    id_smt         char(5) REFERENCES periode,
    dijalankan_oleh uuid REFERENCES users,
    simulasi_gagal boolean NOT NULL DEFAULT false,   -- hanya untuk sinkronisasi tiruan (APP_ENV=local)
    mode           text NOT NULL DEFAULT 'tiruan' CHECK (mode IN ('tiruan','sister')),   -- 'sister' = menghubungi SISTER sungguhan
    tahap          smallint NOT NULL DEFAULT 1 CHECK (tahap BETWEEN 1 AND 4),          -- langkah yang sedang berjalan (empat langkah di halaman)
    endpoint_total smallint,                         -- jumlah endpoint yang direncanakan, untuk persen kemajuan
    durasi_detik   integer,
    pesan_gagal    text,
    terakhir_aktif timestamptz                       -- denyut proses sinkronisasi; dipakai mendeteksi proses yang mati (bukan total waktu)
);
CREATE UNIQUE INDEX ux_sinkron_berjalan ON sinkronisasi ((true)) WHERE status = 'berjalan';   -- satu sinkronisasi pada satu waktu

CREATE TABLE sinkronisasi_endpoint (
    id_sinkron  uuid NOT NULL REFERENCES sinkronisasi ON DELETE CASCADE,
    no          smallint NOT NULL,
    nama        text NOT NULL,
    path        text NOT NULL,
    status      text NOT NULL CHECK (status IN ('berhasil','gagal','dilewati')),
    detik       numeric(6,1),
    rekord      integer NOT NULL DEFAULT 0,
    keterangan  text,
    PRIMARY KEY (id_sinkron, no)
);

CREATE TABLE sinkronisasi_atribut (
    id_sinkron  uuid NOT NULL,
    no          smallint NOT NULL,
    nama        text NOT NULL,
    urut        smallint NOT NULL DEFAULT 0,
    status      text NOT NULL CHECK (status IN ('terambil','gagal','dilewati')),
    PRIMARY KEY (id_sinkron, no, nama),
    FOREIGN KEY (id_sinkron, no) REFERENCES sinkronisasi_endpoint ON DELETE CASCADE
);

-- ============ Fungsi bantu ============
CREATE FUNCTION samarkan(teks text) RETURNS text LANGUAGE sql IMMUTABLE AS $$
    SELECT CASE WHEN teks IS NULL OR teks = '' THEN NULL
                WHEN length(teks) <= 4 THEN repeat('*', length(teks))
                ELSE left(teks, 2) || repeat('*', length(teks) - 4) || right(teks, 2) END
$$;

-- ============ Tampilan untuk halaman ============
-- Golongan dosen: nilai manual di dosen.golongan bila ada, kalau tidak turunan dari jabatan fungsional. NULL = belum diketahui.
CREATE VIEW v_dosen_golongan AS
SELECT d.id_dosen,
       coalesce(d.golongan, (SELECT m.golongan FROM pemetaan_jabfung_golongan m
                             WHERE lower(jf.nama_jabfung) LIKE lower(m.awalan_jabfung) || '%'
                             ORDER BY length(m.awalan_jabfung) DESC LIMIT 1)) AS golongan
FROM dosen d LEFT JOIN jabatan_fungsional jf USING (id_jabfung);

-- Dosen: profil (nomor sensitif sudah disamarkan)
CREATE VIEW v_profil_dosen AS
SELECT d.id_dosen, d.nama_sdm AS nama, jf.nama_jabfung || ' (Gol ' || coalesce(g.golongan, '-') || ')' AS jabatan,
       j.nama_jurusan AS jurusan, d.jenis_sdm, d.status_pegawai,
       samarkan(d.nidn) AS nidn_samar, samarkan(d.nip) AS nip_samar, samarkan(d.nuptk) AS nuptk_samar
FROM dosen d
LEFT JOIN v_dosen_golongan g USING (id_dosen)
LEFT JOIN jabatan_fungsional jf USING (id_jabfung)
LEFT JOIN jurusan j USING (id_jurusan);

-- Dosen: riwayat honor per periode (terbaru di atas)
CREATE VIEW v_riwayat_honor_dosen AS
SELECT p.id_dosen, p.id_smt, pr.label AS periode, p.sks_wajib, p.sks_terlaksana, p.sks_lebih,
       p.bruto, p.pajak, p.netto, p.status_verifikasi,
       CASE WHEN p.status_verifikasi IN ('terverifikasi','terkunci') THEN 'Terverifikasi' ELSE 'Menunggu Verifikasi' END AS status_tampil
FROM perhitungan p JOIN periode pr USING (id_smt)
ORDER BY pr.id_smt DESC;

-- Dosen dan Keuangan: rincian kelas per periode
CREATE VIEW v_rincian_kelas AS
SELECT pg.id_dosen, pg.id_smt, pg.id_pengajaran, pg.mata_kuliah AS mk, pg.kelas, pg.kode_matakuliah,
       pg.program_studi, pg.jenjang_pendidikan, pg.jenis_kelas, pg.sks
FROM pengajaran pg;

-- Keuangan/Pimpinan: daftar dosen dan honor per periode
CREATE VIEW v_data_honor AS
SELECT p.id_smt, pr.label AS periode, d.id_dosen, d.nama_sdm AS nama,
       jf.nama_jabfung AS jabatan, g.golongan, j.nama_jurusan AS jurusan,
       p.sks_wajib, p.sks_terlaksana, p.sks_lebih, p.bruto, p.pajak, p.netto, p.status_verifikasi,
       CASE WHEN p.status_verifikasi IN ('terverifikasi','terkunci') THEN 'Terverifikasi' ELSE 'Menunggu Verifikasi' END AS status_tampil,
       ba.simpulan_asesor AS simpulan, p.catatan
FROM perhitungan p
JOIN dosen d USING (id_dosen)
JOIN periode pr USING (id_smt)
LEFT JOIN v_dosen_golongan g USING (id_dosen)
LEFT JOIN jabatan_fungsional jf USING (id_jabfung)
LEFT JOIN jurusan j USING (id_jurusan)
LEFT JOIN bkd_akhir ba ON ba.id_dosen = p.id_dosen AND ba.id_smt = p.id_smt;

-- Keuangan/Pimpinan: ringkasan per jurusan
CREATE VIEW v_dashboard_jurusan AS
SELECT id_smt, periode, coalesce(jurusan, '(tanpa jurusan)') AS jurusan,
       count(*) AS jumlah_dosen,
       count(*) FILTER (WHERE status_verifikasi IN ('terverifikasi','terkunci')) AS terverifikasi,
       round(100.0 * count(*) FILTER (WHERE status_verifikasi IN ('terverifikasi','terkunci')) / count(*), 1) AS persen_terverifikasi,
       sum(sks_lebih) AS sks_lebih, sum(bruto) AS bruto, sum(pajak) AS pajak, sum(netto) AS netto
FROM v_data_honor
GROUP BY id_smt, periode, jurusan;

-- Detail honor per kelas (kolom 100% / 50% / 0%)
CREATE VIEW v_detail_honor_kelas AS
SELECT dp.id_perhitungan, p.id_dosen, p.id_smt, dp.urutan AS no, pg.mata_kuliah AS mk, pg.kelas, dp.jenis_kelas,
       dp.sks_kelas, dp.sks_wajib_terpakai, dp.sks_100, dp.sks_50, dp.sks_0,
       dp.tarif_per_sks, dp.tarif_pph, dp.bruto, dp.pajak, dp.netto,
       pg.program_studi AS prodi, pg.jenjang_pendidikan AS jenjang
FROM detail_perhitungan dp
JOIN perhitungan p USING (id_perhitungan)
JOIN pengajaran pg USING (id_pengajaran)
ORDER BY p.id_dosen, p.id_smt, dp.urutan;

-- Tarif & Ketentuan (baca saja untuk Keuangan, Pimpinan, Dosen)
CREATE VIEW v_tarif_aktif AS
SELECT t.id_tarif, t.jenis_kelas, coalesce(t.jabatan, t.golongan, 'Semua') AS golongan, t.tarif_per_sks, t.berlaku_mulai,
       d.nomor AS dokumen, d.tentang, d.berlaku AS dokumen_berlaku
FROM master_tarif t LEFT JOIN perdir_dokumen d USING (id_dokumen)
WHERE t.aktif AND (t.jabatan IS NULL OR t.jenis_kelas = 'Reguler');   -- tarif umum dan tarif Reguler per jabatan; tarif kerja sama per mitra dikelola di halaman Master Tarif

-- Keuangan/Pimpinan: ringkasan non-pengajaran per dosen per periode
CREATE VIEW v_nonpengajaran_dosen AS
SELECT k.id_smt, pr.label AS periode, d.id_dosen, d.nama_sdm AS nama, jf.nama_jabfung AS jabatan, j.nama_jurusan AS jurusan,
       count(*)::int AS jumlah_kegiatan, sum(k.honor_bruto)::bigint AS bruto,
       string_agg(DISTINCT k.jenis, '|' ORDER BY k.jenis) AS jenis_gabung,
       coalesce(v.status_verifikasi, 'menunggu') AS status_verifikasi,
       ba.simpulan_asesor AS simpulan,
       (count(*) FILTER (WHERE k.stat_bkd = 'Beban lebih'))::int AS jumlah_beban_lebih
FROM kegiatan_nonpengajaran k
JOIN dosen d USING (id_dosen)
JOIN periode pr USING (id_smt)
LEFT JOIN jabatan_fungsional jf USING (id_jabfung)
LEFT JOIN jurusan j USING (id_jurusan)
LEFT JOIN verifikasi_nonpengajaran v ON v.id_dosen = k.id_dosen AND v.id_smt = k.id_smt
LEFT JOIN bkd_akhir ba ON ba.id_dosen = k.id_dosen AND ba.id_smt = k.id_smt
GROUP BY k.id_smt, pr.label, d.id_dosen, d.nama_sdm, jf.nama_jabfung, j.nama_jurusan, v.status_verifikasi, ba.simpulan_asesor;

-- Non-pengajaran dengan pajak dan neto (dashboard dan laporan)
CREATE VIEW v_np_honor AS
SELECT n.id_smt, n.periode, n.id_dosen, n.nama, n.jabatan, n.jurusan, g.golongan, n.jumlah_kegiatan,
       coalesce(n.bruto, 0)::bigint AS bruto,
       round(coalesce(n.bruto, 0) * coalesce(pj.tarif_pph, (SELECT tarif_pph FROM pengaturan_pajak WHERE golongan = 'III'), 0))::bigint AS pajak,
       (coalesce(n.bruto, 0) - round(coalesce(n.bruto, 0) * coalesce(pj.tarif_pph, (SELECT tarif_pph FROM pengaturan_pajak WHERE golongan = 'III'), 0)))::bigint AS netto,
       n.status_verifikasi
FROM v_nonpengajaran_dosen n
LEFT JOIN v_dosen_golongan g USING (id_dosen)
LEFT JOIN pengaturan_pajak pj ON pj.golongan = g.golongan;


-- ============ Laporan data yang perlu dicek ============
-- SEMUA jenis tugas tambahan SISTER yang belum dipetakan ke kelompok SKS wajib (sementara dihitung sebagai DS).
-- Isi pemetaan: INSERT INTO pemetaan_tugas_tambahan (jenis_tugas, kelompok) VALUES ('<persis seperti di sini>', 1|2|3);
CREATE VIEW v_tugas_belum_dipetakan AS
SELECT t.jenis_tugas, count(*)::int AS jumlah_tugas, count(DISTINCT t.id_dosen)::int AS jumlah_dosen
FROM tugas_tambahan_dosen t
WHERE NOT EXISTS (SELECT 1 FROM pemetaan_tugas_tambahan m WHERE lower(m.jenis_tugas) = lower(trim(t.jenis_tugas)))
GROUP BY t.jenis_tugas ORDER BY count(*) DESC;

-- Program studi di data pengajaran yang belum ada di tabel program_studi (jurusan dosen tidak bisa diturunkan dari sini).
CREATE VIEW v_prodi_belum_dikenal AS
SELECT p.program_studi, p.jenjang_pendidikan, count(*)::int AS jumlah_kelas
FROM pengajaran p
WHERE p.program_studi IS NOT NULL AND NOT EXISTS (SELECT 1 FROM program_studi ps WHERE lower(ps.nama_prodi) = lower(trim(p.program_studi)))
GROUP BY p.program_studi, p.jenjang_pendidikan ORDER BY count(*) DESC;

-- Kelas yang dianggap Reguler tetapi bentuknya BUKAN [kode prodi][semester][huruf kelas] (mis. TI5A, 6B). Kemungkinan kelas kerja sama yang pola mitranya belum didaftarkan.
CREATE VIEW v_kelas_belum_dikenali AS
SELECT pg.kelas, count(*)::int AS jumlah, min(pg.program_studi) AS contoh_prodi, min(pg.mata_kuliah) AS contoh_mk
FROM pengajaran pg
WHERE pg.jenis_kelas = 'Reguler' AND upper(trim(pg.kelas)) !~ '^([A-Z]{1,6}[ -]?)?[0-9]{1,2}[ -]?[A-Z]?$'
GROUP BY pg.kelas ORDER BY count(*) DESC;
