-- Honor non-pengajaran dengan pajak dan neto (golongan sama seperti pengajaran: III 5%, IV 15% dari tabel pengaturan_pajak).
-- Dipakai dashboard dan laporan. Aman dijalankan ulang.
CREATE OR REPLACE VIEW v_np_honor AS
SELECT n.id_smt, n.periode, n.id_dosen, n.nama, n.jabatan, n.jurusan, g.golongan, n.jumlah_kegiatan,
       coalesce(n.bruto, 0)::bigint AS bruto,
       round(coalesce(n.bruto, 0) * coalesce(pj.tarif_pph, (SELECT tarif_pph FROM pengaturan_pajak WHERE golongan = 'III'), 0))::bigint AS pajak,
       (coalesce(n.bruto, 0) - round(coalesce(n.bruto, 0) * coalesce(pj.tarif_pph, (SELECT tarif_pph FROM pengaturan_pajak WHERE golongan = 'III'), 0)))::bigint AS netto,
       n.status_verifikasi
FROM v_nonpengajaran_dosen n
LEFT JOIN v_dosen_golongan g USING (id_dosen)
LEFT JOIN pengaturan_pajak pj ON pj.golongan = g.golongan;
