# Menyambungkan frontend ke API Laravel (data dari database)

1. Jalankan backend (lihat api-laravel/BACA_DULU.md) di http://localhost:8000 dengan APP_ENV=local.
2. Di folder frontend buat `.env.local`:
   ```
   VITE_AUTH_MODE=dev
   VITE_USE_MOCK=false
   VITE_API_URL=http://localhost:8000
   ```
3. `npm install` lalu `npm run dev -- --port 8081` (harus 8081 agar cocok dengan CORS/Sanctum dan CAS).
4. Login: pilih peran saja (tanpa NIP/password). "Dosen" = Dosen B (SKS lebih 12, honor bruto Rp1.040.000).

Kembali ke data tiruan: `VITE_AUTH_MODE=mock` dan `VITE_USE_MOCK=true` (default).

Catatan: rincian per minggu belum ada (butuh kalender akademik); Kunci Periode & Ekspor masih lokal (belum ada API).
