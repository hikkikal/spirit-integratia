# Integratia – Backend API

REST API berbasis **Laravel 13 (PHP 8.3+)** dan **Laravel Sanctum 4** untuk sistem Integratia. Backend ini membaca langsung tabel dan *view* PostgreSQL melalui lapisan kueri terpusat (`app/Support/Kueri.php`) dan pemetaan respons (`app/Support/Bentuk.php`).

## Prasyarat

- PHP 8.3+ dengan ekstensi: `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `xml`, `curl`, `fileinfo`
- Composer
- PostgreSQL 14+ (database `integratia` sudah dimuat dari `../db/schema.sql` dan `../db/seed.sql`)
- Pengaturan `php.ini` untuk unggah dokumen Perdir PDF (maks. 20 MB):
  ```ini
  upload_max_filesize = 20M
  post_max_size = 25M
  extension=fileinfo
  ```

## Instalasi & Konfigurasi

1. Pasang dependensi PHP:
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```
2. Atur `.env` (jangan pernah di-commit ke Git):
   ```env
   APP_ENV=local
   APP_URL=http://localhost:8000

   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=integratia
   DB_USERNAME=postgres
   DB_PASSWORD=<isi_password_db>

   SESSION_DRIVER=file
   SESSION_DOMAIN=localhost
   CACHE_STORE=file
   QUEUE_CONNECTION=sync
   SANCTUM_STATEFUL_DOMAINS=localhost:8081

   # Autentikasi lokal (set false di produksi jika hanya memakai SSO)
   LOGIN_LOKAL=true

   # SSO PNJ (OpenID Connect: Authorization Code + PKCE)
   OIDC_ISSUER=https://dev-id.pnj.ac.id/cas/oidc
   OIDC_CLIENT_ID=<client_id>
   OIDC_CLIENT_SECRET=<client_secret>
   OIDC_REDIRECT_URI=http://localhost:8081/auth/callback
   OIDC_SCOPES="openid profile email publicacademia.read publicpersonalia.read"
   OIDC_VERIFY_TLS=true

   # SISTER (kosongkan SISTER_BASE_URL untuk memakai mode simulasi)
   SISTER_BASE_URL=
   SISTER_USERNAME=
   SISTER_PASSWORD=
   SISTER_ID_PENGGUNA=

   # Open API PNJ (Academia)
   PNJ_API_URL=https://dev-api.pnj.ac.id
   ACADEMIA_TOKEN=
   ACADEMIA_CLIENT_CREDENTIALS=false
   ACADEMIA_VERIFY_TLS=true
   ```
3. **Penting:** Jangan jalankan `php artisan migrate`. Skema tabel dikelola langsung melalui `../db/schema.sql` dan `../db/migrasi_*.sql`.
4. Jalankan server pengembangan:
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan serve
   ```
   Cek daftar rute API: `php artisan route:list --path=api`.

## Arsitektur Kode

- `routes/api.php` — Seluruh definisi endpoint API beserta middleware `auth:sanctum`, `peran:...`, dan `throttle`.
- `app/Http/Controllers/` — Controller tematik (`SsoController`, `AuthController`, `DosenController`, `KeuanganController`, `HitungController`, `NonPengajaranController`, `KoreksiController`, `LaporanController`, `AcademiaController`, `DokumenReferensiController`, `AdminInputController`, `AdminSuperController`).
- `app/Http/Middleware/PeranAktif.php` — Validasi peran aktif dari sesi dan penolakan otomatis (HTTP 403) untuk akun nonaktif.
- `app/Support/` — Logika inti terpisah dari framework agar mudah diuji:
  - `Kueri.php` & `Bentuk.php` — Kueri SQL PostgreSQL dan formatter JSON.
  - `HitungHonor.php`, `HitungNonPengajaran.php`, `PolaKelas.php` — Mesin perhitungan honor pengajaran & non-pengajaran (SBM 23/2026).
  - `Oidc.php` & `SsoPencocok.php` — Autentikasi OIDC PKCE & pencocokan `sso_sub`.
  - `SinkronSister.php`, `SisterKlien.php`, `SinkronSimulasi.php` — Sinkronisasi atomik 10 endpoint SISTER.
  - `Academia.php`, `AkademikKlien.php`, `SinkronAkademik.php` — Integrasi jadwal/pertemuan Open API PNJ.
  - `Ekspor/Xlsx.php` & `Ekspor/Pdf.php` — Pembuat laporan Excel & PDF.

## Ringkasan Endpoint API (`/api/...`)

| Grup | Endpoint Utama | Peran |
| :--- | :--- | :--- |
| **Autentikasi** | `POST /auth/sso/mulai`, `POST /auth/sso/callback`, `POST /auth/login`, `POST /dev-login`, `GET /me`, `POST /role`, `POST /logout` | Publik / Login |
| **Referensi Umum** | `GET /periode`, `GET /tarif`, `GET /dokumen-referensi`, `GET /dokumen-referensi/{id}/berkas` | Login |
| **Dosen** | `GET /dosen/profil`, `/riwayat`, `/kelas`, `/honor`, `/non-pengajaran`, `/academia` | `dosen` |
| **Keuangan & Pimpinan** | `GET /dashboard`, `/data-honor`, `/data-honor/{id}`, `/{id}/detail`, `/{id}/academia`, `/non-pengajaran`, `/laporan/data`, `/tarif/daftar`, `/tarif/non-pengajaran` | `keuangan`, `pimpinan`, `admin` |
| **Operasional Keuangan** | `POST /hitung-honor`, `POST /data-honor/{id}/verifikasi`, `POST /non-pengajaran/{id}/verifikasi` | `keuangan` |
| **Koreksi & Ekspor** | `GET/PUT/POST /dosen-koreksi/{id}/*`, `GET /laporan/ekspor`, `GET /laporan/riwayat` | `keuangan`, `admin` |
| **Admin Input** | `GET/POST /admin/sinkron/*`, `GET/POST/PUT /admin/tarif*`, `GET/POST/PATCH/DELETE /admin/perdir*` | `admin` |
| **Admin Super** | `GET/PUT/PATCH /admin-super/pengguna*`, `GET /admin-super/log`, `GET /admin-super/log/aksi` | `admin_super` |

## Perintah Artisan CLI

```bash
# Sinkronisasi data SISTER (opsional: kode semester, id SDM tertentu, atau batas uji)
php artisan sinkron:sister [20261] [--id-sdm=UUID] [--maks=3]

# Sinkronisasi data pertemuan Academia
php artisan sinkron:akademik

# Mengatur password akun lokal (minimal 12 karakter)
php artisan integratia:atur-password <username>
```

> Untuk sinkronisasi penuh ratusan dosen di produksi, jalankan melalui **Nginx + PHP-FPM** atau jadwal CLI (`routes/console.php`), bukan `php artisan serve`.

## Pengujian (`uji/`)

Skrip pengujian dijalankan langsung terhadap PostgreSQL:

```bash
# Uji kueri SQL & format JSON (termasuk non-pengajaran)
PGHOST=127.0.0.1 PGUSER=postgres PGPASSWORD=... php uji/uji_kueri.php

# Uji mesin hitung honor
php uji/uji_hitung.php

# Uji pola kelas kerja sama
php uji/uji_pola.php

# Uji sinkronisasi SISTER dengan mock server lokal (WAJIB database berakhiran _uji)
createdb integratia_uji
psql -d integratia_uji -f ../db/schema.sql
psql -d integratia_uji -f ../db/seed.sql
PGDATABASE=integratia_uji php uji/uji_sinkron.php
```
