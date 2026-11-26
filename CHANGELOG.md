# Changelog & Daftar Tugas (Issues) Integratia

Dokumen ini mencatat riwayat perubahan penting proyek Integratia beserta daftar tugas/issue yang masih terbuka menuju rilis v1.0.

## Riwayat Perubahan (Changelog)

### [0.7.0] — Kondisi Saat Ini (Siap Inisialisasi GitHub)
**Ditambahkan**
- **Integrasi Open API PNJ (Academia):** Pengambilan jadwal/pertemuan mingguan dengan cache JSONB (`academia_hasil`, `migrasi_8.sql`), CLI `php artisan sinkron:akademik`, dan utilitas eksplorasi `php artisan akademik:jelajah`.
- **Autentikasi SSO PNJ via OIDC (PKCE):** Transisi dari CAS lama ke OpenID Connect (`SsoController`, `Oidc.php`) dengan pengikatan identitas permanen `sso_sub` (`migrasi_7.sql`).
- **Koreksi Manual Beralasan:** Modul koreksi jabatan (`jabfung_manual`), golongan, dan tugas tambahan dosen (`KoreksiController`, `migrasi_6.sql`) yang tidak tertimpa saat sinkronisasi SISTER.
- **Honor Non-Pengajaran SBM Internal 23/2026:** Tabel 9 baris tarif (`tarif_nonpengajaran`, `migrasi_3.sql`), view pajak & neto (`v_np_honor`, `migrasi_4.sql`), mesin hitung `HitungNonPengajaran.php`, serta penyelarasan status BKD (`migrasi_5.sql`, `migrasi_9.sql`, `migrasi_10.sql`).
- **CLI Hitung Ulang Honor:** Perintah `php artisan honor:hitung [periode]` (`HitungHonorCommand.php`) untuk menghitung ulang satu periode tanpa sinkron SISTER ulang.
- **Ekspor Laporan:** Pembuatan laporan resmi XLSX (`Ekspor/Xlsx.php`) dan PDF (`Ekspor/Pdf.php`).

**Dihapus / Dirapikan**
- Mengeluarkan folder `paket_data/` dan salinan skema duplikat dari struktur repositori utama.
- Menghapus kode & konfigurasi CAS lama (`Cas.php`, `config/cas.php`, `AuthController::ssoValidate`, `AuthController::logout`) serta berkas frontend tidak terpakai.
- Menyusun `integratia.sql` (skema gabungan bersih tanpa `DO $$ BEGIN`) dan dokumentasi README per modul.

### [0.5.0] — Fondasi Sinkronisasi SISTER & Hitung Honor Pengajaran
- Sinkronisasi SISTER atomik (10 endpoint dengan rollback otomatis + mode simulasi + CLI `sinkron:sister`).
- Mesin hitung honor pengajaran bilangan bulat (`HitungHonor.php`), deteksi kelas Kerja Sama (`pola_kelas_kerja_sama`), dan verifikasi per dosen/periode.
- Area **Admin Input** (Master Tarif, unggah Perdir PDF privat maks. 20 MB, log sinkronisasi) dan **Admin Super** (manajemen multi-role & audit log aktivitas).

### [0.2.0] — Kerangka SPA Vue 3, REST API Laravel & Skema PostgreSQL
- Skema relasional awal PostgreSQL (`db/schema.sql`) beserta *view* penyamaran identitas dan data *seed*.
- Halaman inti 5 peran pengguna (Dosen, Keuangan, Pimpinan, Admin Input, Admin Super) dengan autentikasi Sanctum & login lokal/dev.

---

## Daftar Issue & Sisa Pengembangan (~30% menuju v1.0)

> Setelah repositori dipush ke GitHub, setiap baris di bawah ini dapat dipindahkan menjadi **GitHub Issue** agar progres dan *Pull Request* dapat ditautkan otomatis (`Closes #1`, dst.).

| ID | Judul Issue / Tugas | Kategori | Prioritas | Status | PIC |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `#1` | Implementasi fitur **Kunci Periode** (*lock/unlock* periode dari UI Keuangan/Admin) | Fitur | Tinggi | Belum dikerjakan | — |
| `#2` | Gabungkan tahap sinkronisasi **Academia** ke dalam alur satu tombol sinkronisasi bersama SISTER | Integrasi | Tinggi | Sedang berjalan | — |
| `#3` | Integrasi penarikan data **PKL/Magang** & **Pembimbing Akademik** dari SISTER (menunggu endpoint) | Integrasi | Sedang | Menunggu pihak luar | — |
| `#4` | Konfirmasi & dukung tarif kelas **Kerja Sama per mitra** serta aturan SKS wajib khusus | Aturan Bisnis | Sedang | Perlu keputusan | — |
| `#5` | Pemisahan tata letak khusus peran **Pimpinan** & **Admin Input** bila desain berbeda dari Keuangan | Frontend | Rendah | Menunggu desain | — |
| `#6` | Uji beban & *deployment* penuh pada server **Nginx + PHP-FPM** untuk sinkronisasi ratusan dosen | DevOps / QA | Tinggi | Belum dikerjakan | — |
