# Integratia – Frontend

Vue 3 + Vite. Halaman yang sudah ada: Login (dari frame "Login Page" di Figma).

```bash
npm install
npm run dev
```

## Mengganti aset
| Berkas | Isi | Catatan |
|---|---|---|
| `src/assets/logo.svg` | Logo Integratia | Boleh PNG/SVG. Tampil di lencana putih, di atas teks sambutan (layar lebar) dan di atas form (layar sempit). Bila memakai PNG, ubah impor di `src/components/BrandLogo.vue`. |
| `src/assets/login-illustration.svg` | Ilustrasi kiri bawah | Di Figma, klik grup `data-analysis/cuate` (node 63:338) lalu Export sebagai SVG. Rasio 376 × 392. |

## Data sementara
`src/services/auth.js` masih data tiruan (password minimal 6 karakter dianggap benar). Ganti dengan panggilan ke API Laravel nanti.

## Halaman Keuangan
Login lalu masuk ke `/keuangan/dashboard`. Halaman: Dashboard, Data Honor Dosen, Detail Honor (klik "Detail" pada Dosen B untuk rincian kelas), Laporan & Ekspor.
Data masih tiruan di `src/data/mock.js`; setelah `npm install` jalankan `npm run dev`.

## Peran dan login
- `.env.example` -> salin ke `.env`. `VITE_AUTH_MODE=mock` (default): form login tiruan dengan pilihan peran (Keuangan/Pimpinan/Admin Input/Dosen), tanpa backend.
- `VITE_AUTH_MODE=sso`: tombol "Masuk dengan SSO PNJ" saja, login lewat backend Laravel + CAS PNJ. Lihat `docs/BACKEND.md`.
- Halaman Admin Input (khusus) dan Dosen menunggu desain Figma; Pimpinan/Admin memakai halaman yang sama dengan Keuangan untuk sementara.

## Role Dosen
Login mode mock dengan peran **Dosen** memunculkan Dashboard Saya, Rincian Kelas, Riwayat Honor, Profil (menu pengguna di kiri bawah), dan Ganti Akun (demo: Dosen dan Pimpinan).
