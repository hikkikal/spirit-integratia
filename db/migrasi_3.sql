-- Honor non-pengajaran dari tarif SBM Internal PNJ 23/2026. Aman dijalankan ulang.
CREATE TABLE IF NOT EXISTS tarif_nonpengajaran (
    kode       text PRIMARY KEY,
    nama       text NOT NULL,
    satuan     text NOT NULL,
    tarif_iv   integer NOT NULL CHECK (tarif_iv >= 0),
    tarif_iii  integer NOT NULL CHECK (tarif_iii >= 0),
    urutan     smallint NOT NULL,
    keterangan text
);
INSERT INTO tarif_nonpengajaran (kode, nama, satuan, tarif_iv, tarif_iii, urutan, keterangan) VALUES
 ('skripsi_penguji_proposal','Penguji Proposal Skripsi/Tugas Akhir','per mahasiswa',50000,44750,1,'SBM 23/2026 3.B.1'),
 ('skripsi_penguji','Penguji Skripsi/Tugas Akhir','per mahasiswa',75000,67100,2,'SBM 23/2026 3.B.1'),
 ('skripsi_pembimbing','Pembimbing Skripsi/Tugas Akhir','per mahasiswa',350000,310000,3,'Dibimbing lebih dari 1 dosen: pembimbing 1 = 60%, pembimbing 2 = 40%'),
 ('tesis_penguji_proposal','Penguji Seminar Proposal Tesis','per mahasiswa',50000,44750,4,'SBM 23/2026 3.B.2'),
 ('tesis_penguji_hasil','Penguji Seminar Hasil Penelitian Tesis','per mahasiswa',200000,178950,5,'SBM 23/2026 3.B.2'),
 ('tesis_pembimbing_utama','Pembimbing Tesis Utama','per mahasiswa',750000,671100,6,'Minimal Doktor (S3)'),
 ('tesis_pembimbing_pendamping','Pembimbing Tesis Pendamping','per mahasiswa',500000,447300,7,'Minimal Doktor (S3)'),
 ('pkl_pembimbing','Pembimbing Magang/Praktik Lapangan/On Job Training','per mahasiswa',200000,178900,8,'SBM 23/2026 3.C'),
 ('pembimbing_akademik','Pembimbing Akademik','per mahasiswa per semester',20000,17900,9,'SBM 23/2026 3.D')
ON CONFLICT (kode) DO NOTHING;

ALTER TABLE kegiatan_nonpengajaran
    ADD COLUMN IF NOT EXISTS jumlah_mhs integer NOT NULL DEFAULT 1 CHECK (jumlah_mhs >= 1),
    ADD COLUMN IF NOT EXISTS kode_tarif text REFERENCES tarif_nonpengajaran,
    ADD COLUMN IF NOT EXISTS catatan text,
    ADD COLUMN IF NOT EXISTS detail jsonb;   -- ringkasan detail SISTER: mahasiswa, pembimbing, SK, tanggal (diisi saat sinkron)
