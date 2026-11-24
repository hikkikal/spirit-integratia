-- Hasil pengambilan data akademik (Academia) disimpan di database supaya tetap ada setelah refresh/restart
-- dan bisa dilihat semua pengguna tanpa memanggil API lagi.
CREATE TABLE IF NOT EXISTS academia_hasil (
    id_dosen    uuid NOT NULL REFERENCES dosen ON DELETE CASCADE,
    id_smt      char(5) NOT NULL,
    data        jsonb NOT NULL,
    diambil     timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (id_dosen, id_smt)
);
