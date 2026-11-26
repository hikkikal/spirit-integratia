# Dokumentasi API Integratia

Base URL pengembangan: `http://localhost:8000/api`  
Autentikasi: **Laravel Sanctum (cookie session stateful)**. Seluruh endpoint terautentikasi memerlukan cookie sesi aktif serta header `Accept: application/json` dan `X-XSRF-TOKEN` (untuk `POST`/`PUT`/`PATCH`/`DELETE`).

## Kode Status Umum

| Kode | Arti |
| :--- | :--- |
| `200` / `201` | Berhasil |
| `401` | Belum login atau sesi/tiket SSO tidak valid |
| `403` | Peran aktif tidak memiliki izin, atau akun dinonaktifkan |
| `404` | Data / periode / berkas tidak ditemukan, atau fitur dimatikan (`LOGIN_LOKAL=false`, `APP_ENV!=local`) |
| `409` | Konflik status (periode terkunci, sinkronisasi sedang berjalan, atau data sudah terverifikasi) |
| `422` | Validasi input gagal (detail per field pada `errors`) |
| `429` | Melebihi batas permintaan (*rate limit* / `throttle`) |
| `502` | Layanan eksternal (SSO PNJ / SISTER / Academia) tidak terjangkau |

## 1. Autentikasi & Sesi

| Method & Endpoint | Akses | Rate Limit | Body / Parameter | Keterangan |
| :--- | :--- | :--- | :--- | :--- |
| `POST /auth/sso/mulai` | Publik | 20/mnt | — | Membuat state + PKCE dan mengembalikan URL redirect OIDC PNJ |
| `POST /auth/sso/callback` | Publik | 20/mnt | `{ code, state }` | Menukar authorization code, mencocokkan akun (`sso_sub`), membuat sesi |
| `POST /auth/login` | Publik | 30/mnt (5 gagal/mnt/akun+IP) | `{ username, password }` | Login akun lokal (`metode_login = 'lokal'`); 404 bila `LOGIN_LOKAL=false` |
| `POST /dev-login` | Publik (`local`) | 30/mnt | `{ role }` | Login cepat untuk pengembangan; 404 di luar `APP_ENV=local` |
| `GET /me` | Login | — | — | Mengembalikan profil singkat, peran aktif, dan daftar peran pengguna |
| `GET /profil` | Login | — | — | Data profil lengkap pengguna yang sedang login |
| `POST /role` | Login | — | `{ role }` | Mengganti peran aktif pada sesi (divalidasi terhadap `user_roles`) |
| `POST /logout` | Login | — | — | Mengakhiri sesi Laravel dan mengembalikan `logout_url` OIDC bila login via SSO |

Contoh respons `GET /me`:
```json
{
  "data": {
    "nip": "demo",
    "name": "Dosen Contoh",
    "role": "dosen",
    "roles": ["dosen", "keuangan", "pimpinan", "admin", "admin_super"]
  }
}
```

## 2. Referensi Umum (Semua Peran Terautentikasi)

| Method & Endpoint | Parameter | Keterangan |
| :--- | :--- | :--- |
| `GET /periode` | — | Daftar seluruh periode semester (`id_smt`, `label`, `terkunci`) |
| `GET /tarif` | — | Tarif umum pengajaran, ketentuan SKS wajib, dan tarif PPh |
| `GET /dokumen-referensi` | — | Daftar dokumen Perdir aktif beserta penanda `adaBerkas` |
| `GET /dokumen-referensi/{id}/berkas` | Path `id` (integer) | Streaming PDF `inline` dari storage privat (`storage/app/perdir/`) |

## 3. Area Dosen (`peran:dosen`)

ID dosen selalu diambil dari sesi pengguna (bukan parameter URL). Nomor NIDN/NIP/NUPTK disamarkan pada *view*.

| Method & Endpoint | Query | Keterangan |
| :--- | :--- | :--- |
| `GET /dosen/profil` | — | Profil dosen, jabatan fungsional, golongan, jurusan |
| `GET /dosen/riwayat` | — | Riwayat honor dosen lintas periode |
| `GET /dosen/kelas` | `?periode=Genap 2025/2026` | Daftar kelas yang diajar pada periode terpilih |
| `GET /dosen/honor` | `?periode=...` | Rincian perhitungan honor pengajaran per kelas |
| `GET /dosen/non-pengajaran` | `?periode=...` | Daftar kegiatan non-pengajaran beserta tarif SBM & status BKD |
| `GET /dosen/academia` | `?periode=...` | Data pertemuan mingguan dari cache `academia_hasil` |

## 4. Area Keuangan, Pimpinan & Admin Input (Baca Data)

Middleware: `peran:keuangan,pimpinan,admin`.

| Method & Endpoint | Query / Path | Keterangan |
| :--- | :--- | :--- |
| `GET /dashboard` | `?periode=...` | Ringkasan KPI total dosen, total honor, status verifikasi, dan rekap per jurusan |
| `GET /data-honor` | `?periode=&jurusan=&q=` | Daftar honor pengajaran seluruh dosen |
| `GET /data-honor/{id}` | UUID `id`, `?periode=` | Ringkasan profil & kelas satu dosen |
| `GET /data-honor/{id}/detail` | UUID `id`, `?periode=` | Detail perhitungan SKS wajib/lebih, tarif, PPh, bruto/neto, dan `catatan` |
| `GET /data-honor/{id}/academia` | UUID `id`, `?periode=` | Realisasi pertemuan mingguan dosen dari Academia |
| `GET /non-pengajaran` | `?periode=&jurusan=&jenis=&q=` | Daftar dosen yang memiliki kegiatan non-pengajaran |
| `GET /non-pengajaran/{id}` | UUID `id`, `?periode=` | Rincian kegiatan non-pengajaran satu dosen |
| `GET /laporan` & `GET /laporan/data` | `?periode=&jurusan=` | Data rekap laporan pengajaran & non-pengajaran |
| `GET /tarif/daftar` | — | Daftar Master Tarif pengajaran (hanya-baca) |
| `GET /tarif/non-pengajaran` | — | Daftar 9 baris tarif non-pengajaran SBM Internal 23/2026 |

## 5. Operasional Keuangan & Koreksi Manual

| Method & Endpoint | Peran | Body / Query | Keterangan |
| :--- | :--- | :--- | :--- |
| `POST /hitung-honor` | `keuangan` (6/mnt) | `?periode=...` | Menghitung ulang honor pengajaran & non-pengajaran berstatus `draf` (409 jika periode terkunci) |
| `POST /data-honor/{id}/verifikasi` | `keuangan` | `?periode=...` | Menandai honor pengajaran dosen sebagai `terverifikasi` |
| `POST /non-pengajaran/{id}/verifikasi` | `keuangan` | `?periode=...` | Menandai honor non-pengajaran dosen sebagai `terverifikasi` (409 jika sudah/kosong) |
| `GET /laporan/ekspor` | `keuangan,admin` (20/mnt) | `?periode=&format=xlsx\|pdf` | Mengunduh berkas laporan Excel (`.xlsx`) atau PDF |
| `GET /laporan/riwayat` | `keuangan,admin` | — | Riwayat aktivitas ekspor laporan |
| `GET /dosen-koreksi/{id}` | `keuangan,admin` | — | Melihat status jabatan, golongan, dan daftar tugas tambahan dosen |
| `PUT /dosen-koreksi/{id}/jabatan` | `keuangan,admin` | `{ id_jabfung, alasan }` | Koreksi manual jabatan (`jabfung_manual = true`), tidak ditimpa sinkron SISTER |
| `PUT /dosen-koreksi/{id}/golongan` | `keuangan,admin` | `{ golongan, alasan }` | Koreksi manual golongan (`III` / `IV`) |
| `POST /dosen-koreksi/{id}/tugas` | `keuangan,admin` | `{ jenis_tugas, kelompok, alasan }` | Menambah tugas tambahan manual (`sumber = 'manual'`) |
| `POST /dosen-koreksi/{id}/tugas/{tugas}/kelompok` | `keuangan,admin` | `{ kelompok, alasan }` | Menetapkan kelompok SKS wajib (1–3) untuk tugas tambahan |
| `POST /dosen-koreksi/{id}/tugas/{tugas}/hapus` | `keuangan,admin` | `{ alasan }` | Menghapus tugas manual atau mengabaikan tugas SISTER |
| `POST /dosen-koreksi/{id}/tugas/{tugas}/pulihkan` | `keuangan,admin` | `{ alasan }` | Memulihkan tugas SISTER yang sebelumnya diabaikan |

## 6. Area Admin Input (`peran:admin`)

| Method & Endpoint | Body / Parameter | Keterangan |
| :--- | :--- | :--- |
| `GET /admin/sinkron/koneksi` | — | Status konfigurasi koneksi SISTER (mode `sister` vs `tiruan`) |
| `GET /admin/sinkron` | — | Riwayat sinkronisasi |
| `POST /admin/sinkron` | `{ periode? }` (10/mnt) | Memulai sinkronisasi atomik (409 jika ada proses berjalan / periode terkunci) |
| `GET /admin/sinkron/{id}/status` | UUID `id` | Polling progres 4 tahap & jumlah endpoint selesai |
| `GET /admin/sinkron/{id}` | UUID `id` | Log rinci per endpoint dan atribut yang ditarik |
| `GET /admin/tarif`, `POST /admin/tarif`, `PUT /admin/tarif/{id}` | `{ jenis_kelas, jabatan, tarif_per_sks, ... }` | CRUD Master Tarif pengajaran (validasi tidak tumpang tindih tanggal) |
| `GET /admin/tarif-np`, `PUT /admin/tarif-np/{kode}` | `{ tarif_iv, tarif_iii }` | Ubah nominal 9 baris tarif SBM 23/2026 (`tarif_iii <= tarif_iv`) |
| `GET /admin/perdir`, `POST /admin/perdir` | `multipart/form-data` (PDF maks. 20 MB) | Daftar & unggah dokumen Perdir ke storage privat |
| `PATCH /admin/perdir/{id}`, `DELETE /admin/perdir/{id}` | Path `id` | Aktif/nonaktifkan atau hapus Perdir (ditolak jika sedang dipakai tarif) |

## 7. Area Admin Super (`peran:admin_super`)

| Method & Endpoint | Body / Query | Keterangan |
| :--- | :--- | :--- |
| `GET /admin-super/pengguna` | `?q=` | Daftar seluruh akun pengguna beserta peran & status aktif |
| `PUT /admin-super/pengguna/{username}/role` | `{ roles: [...] }` | Mengatur ulang peran pengguna (menjaga minimal 1 Admin Super aktif & validasi link dosen) |
| `PATCH /admin-super/pengguna/{username}` | `{ aktif: bool }` | Mengaktifkan/menonaktifkan akun (Admin Super tidak bisa menonaktifkan diri sendiri) |
| `GET /admin-super/log` | `?hari=7\|30\|0&status=&aksi=&q=` | Audit log aktivitas sistem |
| `GET /admin-super/log/aksi` | — | Daftar jenis aksi unik untuk opsi filter log |
