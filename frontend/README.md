# Integratia – Frontend

Antarmuka Single Page Application (SPA) berbasis **Vue 3**, **Vue Router 4**, dan **Vite 6** untuk sistem Integratia.

## Prasyarat

- Node.js 20+ dan npm
- Backend Laravel berjalan di `http://localhost:8000` (kecuali saat memakai mode `mock`)

## Instalasi & Menjalankan

```bash
npm install
cp .env.example .env.local
npm run dev -- --port 8081
```

> Gunakan port **`8081`** (akses lewat `http://localhost:8081`, bukan `127.0.0.1`) agar cocok dengan konfigurasi `SANCTUM_STATEFUL_DOMAINS`, CORS backend, dan `OIDC_REDIRECT_URI` SSO PNJ.

### Build Produksi
```bash
npm run build
npm run preview
```

## Konfigurasi Environment (`.env.local`)

| Variabel | Nilai Contoh | Keterangan |
| :--- | :--- | :--- |
| `VITE_AUTH_MODE` | `sso` \| `dev` \| `mock` | `sso`: login lewat SSO PNJ (OIDC) & login lokal; `dev`: pilih peran cepat saat backend `APP_ENV=local`; `mock`: tanpa backend |
| `VITE_USE_MOCK` | `false` \| `true` | `false`: ambil data dari API Laravel; `true`: pakai data tiruan di `src/data/` |
| `VITE_API_URL` | `http://localhost:8000` | Alamat dasar backend Laravel |

Contoh konfigurasi pengembangan terhubung ke backend lokal:
```env
VITE_AUTH_MODE=dev
VITE_USE_MOCK=false
VITE_API_URL=http://localhost:8000
```

## Struktur Folder `src/`

```text
src/
├── assets/       # Logo aplikasi (BrandLogo.vue)
├── components/   # Komponen reusable (Modal, Pager, ThUrut, MingguanAcademia, TarifNonPengajaranPanel, dll.)
├── data/         # Data tiruan & daftar opsi referensi (JABATAN_OPSI, mock.js, dosen.js)
├── layouts/      # Layout utama aplikasi (AppLayout.vue)
├── lib/          # Utilitas format angka/tanggal, aturan peran, dan composables (useHitung, useKoreksi, useTabel)
├── router/       # Definisi rute per peran & navigation guard (index.js)
├── services/     # Klien HTTP API (api.js, auth.js, data.js, academia.js, admin.js, koreksi.js, laporan.js, nonpengajaran.js, super.js, tarif.js)
├── stores/       # State reaktif sesi pengguna & honor (auth.js, honor.js)
└── views/
    ├── dosen/    # Dashboard Saya, Rincian Kelas, Riwayat Honor, Detail Honor, Non-Pengajaran, Profil
    ├── keuangan/ # Dashboard, Data Honor, Rincian & Detail Honor, Pertemuan, Non-Pengajaran, Tarif & Ketentuan, Laporan & Ekspor
    ├── admin/    # Dashboard Sinkronisasi, Log Sinkronisasi, Master Tarif, Dokumen Perdir
    └── super/    # Manajemen Pengguna & Log Aktivitas
```

## Peta Rute Utama

- `/login` & `/auth/callback` — Halaman masuk (OIDC / lokal / dev) dan penerima callback SSO.
- `/dosen/*` — Area pribadi dosen (rincian kelas, pertemuan Academia, riwayat & detail honor, non-pengajaran).
- `/keuangan/*` & `/pimpinan/*` — Area operasional keuangan dan pemantauan pimpinan.
- `/admin/*` — Area Admin Input (sinkronisasi SISTER, pemeriksaan data tarikan, master tarif, Perdir).
- `/admin-super/*` — Area Admin Super (pengguna, peran, log aktivitas).

> Navigation guard di `src/router/index.js` memastikan pengguna hanya dapat membuka area sesuai peran aktifnya, sementara validasi otorisasi utama tetap dijaga oleh backend Laravel.

## Catatan Pengelolaan Aset & Referensi

- Logo aplikasi diatur melalui `src/components/BrandLogo.vue`.
- Daftar pilihan jabatan (`JABATAN_OPSI`) di `src/data/admin.js` perlu dijaga tetap selaras dengan `Bentuk::JABATAN_OPSI` di backend.
- Dokumen referensi Perdir PDF diambil melalui sesi terautentikasi dan dibuka sebagai *blob* di tab baru (tanpa URL publik).
