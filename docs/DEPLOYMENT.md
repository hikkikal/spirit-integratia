# Panduan Build, Migrasi & Deployment Integratia

Dokumen ini merangkum langkah *build* produksi, migrasi database PostgreSQL, konfigurasi server, penjadwalan sinkronisasi, serta prosedur *rollback* untuk proyek Integratia.

## 1. Spesifikasi Lingkungan Produksi

- **Web Server:** Nginx + PHP-FPM 8.3+ *(jangan gunakan `php artisan serve` di produksi karena sinkronisasi SISTER ratusan dosen memakan waktu beberapa menit dan dapat terputus)*
- **PHP Extensions:** `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `xml`, `curl`, `fileinfo`
- **Batas Upload PHP (`php.ini`):**
  ```ini
  upload_max_filesize = 20M
  post_max_size = 25M
  max_execution_time = 300
  ```
- **Database:** PostgreSQL 14+
- **Node.js:** 20+ (hanya diperlukan saat proses *build* aset frontend)

## 2. Build Frontend Produksi

1. Siapkan `frontend/.env.production`:
   ```env
   VITE_AUTH_MODE=sso
   VITE_USE_MOCK=false
   VITE_API_URL=https://api-integratia.pnj.ac.id
   ```
2. Jalankan *build*:
   ```bash
   cd frontend
   npm ci
   npm run build
   ```
3. Hasil *build* berada di `frontend/dist/`. Sajikan folder ini melalui Nginx dengan *fallback* SPA:
   ```nginx
   location / {
       try_files $uri $uri/ /index.html;
   }
   ```

## 3. Konfigurasi Backend Produksi

1. Pasang dependensi tanpa paket *dev*:
   ```bash
   cd backend
   composer install --no-dev --optimize-autoloader
   ```
2. Pastikan nilai produksi di `backend/.env`:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   LOGIN_LOKAL=false
   OIDC_VERIFY_TLS=true
   OIDC_LOG_CLAIMS=false
   ACADEMIA_VERIFY_TLS=true
   SANCTUM_STATEFUL_DOMAINS=integratia.pnj.ac.id
   SESSION_DOMAIN=.pnj.ac.id
   ```
3. Pastikan direktori storage dapat ditulis oleh user PHP-FPM (`www-data`), terutama `storage/app/perdir/` dan `storage/logs/`.
4. Optimalkan cache Laravel:
   ```bash
   php artisan config:cache
   php artisan route:cache
   ```

## 4. Alur Migrasi Database PostgreSQL

> **Penting:** Jangan pernah menjalankan `php artisan migrate`. Seluruh skema dikelola melalui berkas SQL di folder `db/`.

### Instalasi Baru (Database Kosong)
```bash
createdb integratia
export PGCLIENTENCODING=UTF8
psql -U postgres -h 127.0.0.1 -d integratia -f integratia.sql
psql -U postgres -h 127.0.0.1 -d integratia -f db/seed.sql
```
*(Atau jalankan `db/schema.sql` diikuti `db/migrasi_3.sql` s.d. `db/migrasi_10.sql` dan `db/seed.sql`.)*

### Pembaruan Database yang Sudah Berjalan
1. **Wajib backup sebelum migrasi:**
   ```bash
   pg_dump -U postgres -h 127.0.0.1 -Fc integratia > backup_integratia_$(date +%Y%m%d_%H%M).dump
   ```
2. Jalankan **hanya** file migrasi baru secara berurutan (contoh `migrasi_9.sql` & `migrasi_10.sql`), bukan `schema.sql` atau `seed.sql`.
3. Jika migrasi mengubah status BKD atau tarif, hitung ulang honor periode aktif lewat CLI:
   ```bash
   php artisan honor:hitung 20251
   ```

## 5. Penjadwalan Sinkronisasi (Cron)

Aktifkan scheduler Laravel di crontab server agar sinkronisasi harian berjalan otomatis:
```cron
* * * * * cd /var/www/integratia/backend && php artisan schedule:run >> /dev/null 2>&1
```

Perintah operasional manual yang tersedia:
- `php artisan sinkron:sister [id_smt] [--id-sdm=UUID] [--maks=N]` — Tarik data SISTER secara atomik.
- `php artisan sinkron:akademik` — Tarik & simpan cache pertemuan Open API PNJ (Academia).
- `php artisan honor:hitung [id_smt]` — Hitung ulang honor pengajaran & non-pengajaran dari data DB.
- `php artisan akademik:jelajah` — Eksplorasi endpoint Open API Academia/AcademiaV2 (hanya dari jaringan PNJ).
- `php artisan integratia:atur-password <username>` — Atur password akun lokal (min. 12 karakter).

## 6. Prosedur Rollback

Jika rilis mengalami kendala kritis di produksi:

1. **Aktifkan mode pemeliharaan:**
   ```bash
   cd backend && php artisan down
   ```
2. **Kembalikan kode ke commit/tag stabil sebelumnya:**
   ```bash
   git checkout <tag-stabil-sebelumnya>
   composer install --no-dev --optimize-autoloader
   php artisan config:cache && php artisan route:cache
   ```
   *(Bangun ulang `frontend/dist` jika ada perubahan frontend.)*
3. **Pulihkan database (hanya jika terjadi perubahan skema/data yang rusak):**
   ```bash
   pg_restore -U postgres -h 127.0.0.1 --clean --if-exists -d integratia backup_integratia_YYYYMMDD_HHMM.dump
   ```
4. **Aktifkan kembali aplikasi:**
   ```bash
   php artisan up
   ```
