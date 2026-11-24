-- Migrasi 6: koreksi manual jabatan fungsional, golongan, dan tugas tambahan per dosen.
-- Aman dijalankan ulang. Tidak mengubah data yang sudah ada.

-- Jabatan: bila jabfung_manual = true, sinkron SISTER tidak lagi menimpa dosen.id_jabfung.
-- (Golongan tidak perlu penanda: dosen.golongan memang tidak pernah ditimpa sinkron.)
ALTER TABLE dosen ADD COLUMN IF NOT EXISTS jabfung_manual boolean NOT NULL DEFAULT false;
ALTER TABLE dosen ADD COLUMN IF NOT EXISTS koreksi_oleh uuid REFERENCES users;
ALTER TABLE dosen ADD COLUMN IF NOT EXISTS koreksi_pada timestamptz;

-- Tugas tambahan: baris buatan manual ditandai sumber = 'manual' dan tidak dihapus sinkron.
ALTER TABLE tugas_tambahan_dosen ADD COLUMN IF NOT EXISTS sumber text NOT NULL DEFAULT 'sister';
ALTER TABLE tugas_tambahan_dosen ADD COLUMN IF NOT EXISTS alasan text;
ALTER TABLE tugas_tambahan_dosen ADD COLUMN IF NOT EXISTS dibuat_oleh uuid REFERENCES users;
ALTER TABLE tugas_tambahan_dosen ADD COLUMN IF NOT EXISTS dibuat_pada timestamptz;
DO $$ BEGIN
    ALTER TABLE tugas_tambahan_dosen ADD CONSTRAINT tugas_sumber_cek CHECK (sumber IN ('sister', 'manual'));
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

-- Tugas dari SISTER yang dianggap tidak berlaku. Dipisah dari tabel utama karena sinkron menghapus lalu mengisi ulang tabel itu.
CREATE TABLE IF NOT EXISTS tugas_tambahan_diabaikan (
    id_tugas uuid PRIMARY KEY,
    id_dosen uuid NOT NULL REFERENCES dosen ON DELETE CASCADE,
    alasan   text NOT NULL,
    oleh     uuid REFERENCES users,
    waktu    timestamptz NOT NULL DEFAULT now()
);

-- Kelompok tugas tambahan yang ditentukan manual per tugas (dasar SKS wajib DT1..DT3).
-- Berlaku untuk tugas buatan manual (nama bebas) maupun tugas SISTER yang belum dipetakan. Tanpa FK karena sinkron mengisi ulang tugas SISTER.
CREATE TABLE IF NOT EXISTS tugas_tambahan_kelompok (
    id_tugas uuid PRIMARY KEY,
    id_dosen uuid NOT NULL REFERENCES dosen ON DELETE CASCADE,
    kelompok smallint NOT NULL CHECK (kelompok BETWEEN 1 AND 3),
    alasan   text NOT NULL,
    oleh     uuid REFERENCES users,
    waktu    timestamptz NOT NULL DEFAULT now()
);
