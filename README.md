# Integratia

Sistem Integrasi & Perhitungan Honor Dosen untuk lingkungan Politeknik Negeri Jakarta (PNJ). Integratia menghubungkan data akademik dosen dari **SISTER** dan **Open API PNJ (Academia)**, menghitung honor pengajaran maupun non-pengajaran berdasarkan tarif **SBM Internal 23/2026** dan dokumen **Perdir**, serta menyediakan alur verifikasi, koreksi beralasan, audit log, hingga ekspor laporan resmi.

> **Status Pengembangan:** ~70% (Arsitektur inti, autentikasi OIDC, sinkronisasi SISTER atomik, integrasi Academia, mesin hitung honor pengajaran & non-pengajaran, serta 5 peran pengguna sudah berjalan.)

## Struktur Proyek

```text
integratia/
├── backend/        # REST API Laravel 13 (PHP 8.3+), Sanctum, mesin hitung honor & sinkronisasi
├── frontend/       # SPA Vue 3 + Vite 6 + Vue Router 4
├── db/             # Skema PostgreSQL (schema.sql, seed.sql, migrasi_3.sql – migrasi_8.sql)
└── .gitignore
```

Dokumentasi detail per bagian:
- [Dokumentasi Backend (backend/README.md)](https://www.google.com/search?q=./backend/README.md)
- [Dokumentasi Frontend (frontend/README.md)](https://www.google.com/search?q=./frontend/README.md)

## Fitur Utama & Peran Pengguna

Integratia mendukung **multi-role** dengan mekanisme **Ganti Akun/Peran** yang divalidasi di sisi server:

| Peran | Cakupan Fitur |
| :--- | :--- |
| **Dosen** | Dashboard pribadi, rincian kelas ajar, pertemuan mingguan (Academia), detail & riwayat honor, kegiatan non-pengajaran, profil |
| **Keuangan** | Dashboard ringkasan, data honor dosen, hitung ulang honor, verifikasi pengajaran & non-pengajaran, koreksi manual beralasan, tarif & ketentuan, ekspor laporan (XLSX & PDF) |
| **Pimpinan** | Akses pemantauan (baca-saja) untuk dashboard, data honor, pertemuan, non-pengajaran, tarif, dan laporan |
| **Admin Input** | Sinkronisasi data SISTER & Academia beserta log rinci, pemeriksaan & koreksi data tarikan, Master Tarif (pengajaran & non-pengajaran SBM 23/2026), pengelolaan dokumen Perdir PDF (storage privat maks. 20 MB), ekspor laporan |
| **Admin Super** | Manajemen pengguna (aktif/nonaktif, kelola multi-role dengan aturan pengaman) dan pemantauan Audit Log Aktivitas |

## Tech Stack

- **Frontend:** Vue 3 (`^3.5`), Vue Router 4, Vite 6
- **Backend:** Laravel 13, PHP 8.3+, Laravel Sanctum 4 (cookie/session stateful)
- **Database:** PostgreSQL (dikelola via `db/schema.sql` dan file migrasi SQL)
- **Integrasi Eksternal:** SSO PNJ (OpenID Connect — Authorization Code + PKCE), API SISTER (10 endpoint atomik), Open API PNJ (Academia)

## Ringkasan Aturan Perhitungan Honor

- **Honor Pengajaran (`HitungHonor`):** Menggunakan aritmetika bilangan bulat (SKS ×100, pajak basis point) dan pembulatan *half-up*. SKS wajib default 12 dipenuhi kelas Reguler terlebih dahulu lalu kelas Kerja Sama (`pola_kelas_kerja_sama`). 6 SKS lebih pertama dibayar 100%, 4 SKS berikutnya 50%, sisanya 0%.
- **Honor Non-Pengajaran (`HitungNonPengajaran`):** Mengacu pada 9 baris tarif **SBM Internal 23/2026** (Golongan IV & III). Rumus `tarif × jumlah mahasiswa × proporsi` (Skripsi/TA >1 pembimbing: utama 60%, pendamping 40%). Hanya status BKD **Beban lebih** yang dibayar.
- **Pajak PPh 21 & Verifikasi:** Potongan PPh mengikuti tabel `pengaturan_pajak` (Golongan III: 5%, Golongan IV: 15%). Data yang sudah berstatus **terverifikasi** atau pada periode **terkunci** tidak berubah saat hitung ulang.

## Prasyarat

- PHP 8.3+ dengan ekstensi `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `xml`, `curl`, `fileinfo`
- Composer
- Node.js 20+ & npm
- PostgreSQL 14+

## Menjalankan Proyek Secara Lokal

### 1. Siapkan Database PostgreSQL
Buat database `integratia`, lalu muat skema dan data awal (jangan jalankan `php artisan migrate`):

```bash
createdb integratia
export PGCLIENTENCODING=UTF8
psql -U postgres -h 127.0.0.1 -d integratia -f db/schema.sql
psql -U postgres -h 127.0.0.1 -d integratia -f db/seed.sql
```

*(Untuk database lama yang diperbarui bertahap, jalankan `db/migrasi_3.sql` hingga `db/migrasi_8.sql` sesuai urutan.)*

### 2. Jalankan Backend
```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
# Sesuaikan konfigurasi DB PostgreSQL, CORS/Sanctum, OIDC, SISTER, dan Academia di .env
php artisan serve
```
Backend berjalan di `http://localhost:8000`.

### 3. Jalankan Frontend
```bash
cd frontend
cp .env.example .env.local
npm install
npm run dev -- --port 8081
```
Frontend berjalan di `http://localhost:8081`.

## Status & Sisa Pengembangan (~30%)

**Sudah Berjalan:**
- Autentikasi SSO PNJ (OIDC PKCE dengan `sso_sub`), login lokal (`bcrypt` + rate limit), dan dev login.
- Sinkronisasi SISTER atomik (10 endpoint + mode simulasi + CLI `sinkron:sister`).
- Pengambilan data pertemuan mingguan Academia (cache JSONB `academia_hasil` + CLI `sinkron:akademik`).
- Perhitungan honor pengajaran & non-pengajaran (SBM 23/2026), verifikasi, koreksi manual beralasan, master tarif, dokumen Perdir privat, audit log, dan ekspor XLSX/PDF.

**Sisa Pekerjaan:**
- Fitur **Kunci Periode** (*lock period*) penuh dari sisi UI & alur tutup buku.
- Penggabungan tahap sinkronisasi **Academia** ke dalam alur satu tombol sinkronisasi bersama SISTER.
- Penarikan data **PKL/Magang** dan **Pembimbing Akademik** (menunggu ketersediaan endpoint SISTER).
- Penyesuaian tarif kelas Kerja Sama per mitra & konfirmasi aturan SKS wajib khusus.
- Pengujian end-to-end produksi pada lingkungan Nginx + PHP-FPM dan pemisahan tampilan khusus Pimpinan/Admin jika diperlukan.

## Keamanan

- **Jangan pernah commit** file `.env`, `.env.local`, data mentah SISTER, maupun dokumen Perdir privat ke Git.
- Pastikan `APP_ENV=production`, `APP_DEBUG=false`, dan `LOGIN_LOKAL=false` (jika hanya memakai SSO) saat rilis ke server produksi.
