-- Status BKD bimbingan akademik tanpa sinkron ulang.
-- BKD (/bkd/ajar) mencatat bimbingan akademik sebagai SATU baris per kategori dengan judul = nm_kat (tanpa nama mahasiswa),
-- sehingga pencocokan lewat nama mahasiswa tidak pernah berhasil. Di sini dicocokkan lewat kategori yang sama persis,
-- memakai data bkd_pengajaran yang sudah ada. Bila statusnya berbeda-beda untuk dosen dan semester yang sama: dibiarkan kosong.
-- Aman diulang. Setelahnya klik "Hitung ulang" (Keuangan).
UPDATE kegiatan_nonpengajaran k
SET stat_bkd = s.st
FROM (
    SELECT k2.id_kegiatan, min(b.stat_kegiatan) AS st, count(DISTINCT b.stat_kegiatan) AS n
    FROM kegiatan_nonpengajaran k2
    JOIN bkd_pengajaran b
      ON b.id_dosen = k2.id_dosen AND b.id_smt = k2.id_smt
     AND lower(trim(b.nm_kat)) = lower(trim(k2.kategori))
     AND lower(trim(b.judul_keg)) = lower(trim(b.nm_kat))
    WHERE k2.jenis = 'Pembimbing Akademik' AND k2.stat_bkd IS NULL AND b.unsur = 'ajar' AND b.stat_kegiatan IS NOT NULL
    GROUP BY k2.id_kegiatan
) s
WHERE k.id_kegiatan = s.id_kegiatan AND s.n = 1;
