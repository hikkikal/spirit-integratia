# Patch: backend Admin Input + Admin Super, dan penyambungan frontend

Timpa berkas di repo Anda sesuai foldernya (`backend/...` dan `frontend/...`). `buat_seed.py` hanya perlu bila ingin membuat ulang seed.

## Langkah
1. Salin isi zip ini ke folder proyek (timpa).
2. **Muat ulang database** (skema berubah; ini menghapus data lama di basis data `integratia`):
   ```
   export PGCLIENTENCODING=UTF8
   psql -U postgres -h localhost -d integratia -f backend/db/schema.sql
   psql -U postgres -h localhost -d integratia -f backend/db/seed.sql
   ```
   Kalau Anda sudah mendaftarkan akun SSO sendiri, jalankan lagi `backend/db/daftar_akun_sso.sql` setelahnya.
3. Di `php.ini` (Laragon: Menu > PHP > php.ini): `upload_max_filesize = 20M`, `post_max_size = 25M`, pastikan `extension=fileinfo` aktif; restart PHP.
4. Di folder backend Laravel: `php artisan route:list --path=api` (harus ada rute `admin/...` dan `admin-super/...`), lalu
   `php uji/uji_kueri.php` (harus `SEMUA UJI LOLOS`).
5. Frontend: `.env.local` dengan `VITE_USE_MOCK=false` dan `VITE_AUTH_MODE=dev`, jalankan `npm run dev -- --port 8081`.
   Masuk sebagai Admin Input Data (tarif, Perdir, sinkronisasi) dan Admin Super (pengguna, log).

## Yang perlu Anda tahu
- Sinkronisasi SISTER masih TIRUAN (4 langkah ~4,4 detik, log contoh). Shift + klik tombol = simulasi gagal (hanya `APP_ENV=local`).
- Master tarif tersimpan di DB tetapi BELUM mempengaruhi honor; itu pekerjaan service hitung honor berikutnya.
- Uji saya dijalankan pada emulator PHP (kueri, JSON, aturan validasi) dan browser sungguhan; kode controller di dalam Laravel sendiri belum bisa saya jalankan.
  Kalau ada error, kirim teks error-nya (atau `storage/logs/laravel.log`).
- Detail: `backend/BACA_DULU.md` (bagian "Admin Input dan Admin Super"), `frontend/docs/ADMIN_INPUT_API.md`, `frontend/docs/ADMIN_SUPER_API.md`.
