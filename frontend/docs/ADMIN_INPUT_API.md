# Kontrak API Admin Input (untuk backend)

Frontend: `src/services/admin.js`. Backend: `AdminInputController` (sudah dibuat dan diuji lewat emulator; belum dijalankan di dalam Laravel sungguhan).
Semua rute di bawah `/api`, memakai sesi Sanctum yang sama, dibatasi `peran:admin_input`
(peran frontend `admin`). Respons dibungkus `{ "data": ... }`. Galat: `{ "message": "..." }`.

## Sinkronisasi SISTER
| Metode & rute | Fungsi | Respons `data` |
|---|---|---|
| `GET /admin/sinkron/koneksi` | Status koneksi SISTER | `{status:'Terhubung'\|'Gagal', layanan, metode, terakhir, dijalankan}` |
| `GET /admin/sinkron` | Riwayat (terbaru dulu) | `[{id, mulai, status:'berhasil'\|'gagal'}]` |
| `POST /admin/sinkron` | Mulai sinkronisasi (jalan di antrean/proses terpisah) | `{id}` |
| `GET /admin/sinkron/{id}/status` | Kemajuan (dipoll tiap 0,4 dtk) | `{status:'berjalan'\|'berhasil'\|'gagal', persen:0-100, langkah:['selesai'\|'berjalan'\|'menunggu'\|'gagal' x4], logId}` |
| `GET /admin/sinkron/{id}` | Log rinci | lihat di bawah |

Log rinci:
```json
{ "id": "...", "mulai": "25 Okt 2026, 20:58", "durasi": "14 detik", "semester": "Ganjil 2026/2027 (20261)",
  "status": "berhasil|gagal", "endpointBerhasil": 6, "endpointTotal": 6, "totalRekord": 1284, "pesanGagal": "",
  "endpoint": [ { "no": 1, "nama": "BKD Akhir", "path": "/bkd/laporan_akhir_bkd", "status": "berhasil|gagal|dilewati",
    "detik": 1.2, "rekord": 142, "keterangan": "",
    "atributStatus": [ {"nama": "id_smt", "status": "terambil|gagal|dilewati"} ] } ] }
```
Urutan 6 endpoint: BKD Akhir, BKD Pengajaran, List Data Pengajaran, Detail Pengajaran, Pegawai di Perguruan Tinggi, Jabatan Fungsional.
Aturan: bila satu endpoint gagal (setelah 3 percobaan), endpoint berikutnya `dilewati`, dan sinkronisasi `gagal`.
Tabel: `sinkronisasi`, `sinkronisasi_endpoint`, `sinkronisasi_atribut` (sudah ada di `db/schema.sql`). Saat ini sinkronisasi TIRUAN (tanpa menghubungi SISTER).
`POST /admin/sinkron` menerima `{simulasiGagal: true}` hanya bila `APP_ENV=local`; bila sudah ada yang berjalan, jawabannya 409.

## Master tarif
`GET /admin/tarif` -> `[{id, jenis:'Kerja Sama'|'Reguler', kelas, jabatan, honor:int, mulai:'YYYY-MM-DD', sampai:'YYYY-MM-DD'}]`
`POST /admin/tarif` (tanpa id) dan `PUT /admin/tarif/{id}` dengan badan yang sama -> baris tersimpan.
Validasi server (jangan hanya mengandalkan frontend): honor bulat > 0, `sampai >= mulai`, jabatan dari daftar.
Status AKTIF/KEDALUWARSA dihitung dari `sampai` terhadap tanggal hari ini. Padanan di DB: `master_tarif` (kolom `nama_kelas`, `jabatan`, `berlaku_sampai`; hanya baris ber-`jabatan` yang tampil di sini).
Tarif yang tumpang tindih (jenis + kelas + jabatan sama, rentang tanggal beririsan) ditolak 422. Untuk jenis Reguler, server memaksa `kelas = 'Reguler'`.
Perubahan tarif dicatat di `log_aktivitas`; perhitungan lama tidak berubah karena `detail_perhitungan` menyimpan salinan tarifnya. Tarif ini belum dipakai perhitungan honor.

## Dokumen Perdir
`GET /admin/perdir` -> `[{id, nomor, judul, tanggal:'YYYY-MM-DD', berkas:'nama.pdf', berlaku:bool}]`
`POST /admin/perdir` (multipart: `nomor`, `judul`, `tanggal`, `berkas` PDF maks 20 MB; nomor unik) -> baris tersimpan.
`PATCH /admin/perdir/{id}` `{berlaku: bool}`; `DELETE /admin/perdir/{id}` (204).
Server wajib memeriksa tipe MIME PDF sebenarnya (bukan hanya ekstensi) dan menyimpan berkas di luar `public/`.
Padanan di DB: `perdir_dokumen`. Berkas disimpan di `storage/app/perdir` dengan nama acak. Dokumen yang dipakai tarif tidak bisa dihapus (409).
PHP perlu `upload_max_filesize=20M` dan `post_max_size=25M`.

## Catatan
- `tanggal` pada Perdir = "Berlaku Mulai" di form dan "Tanggal Penetapan" di tabel (Figma memakai dua istilah; perlu konfirmasi).
- Nomor peraturan di Figma tampak sebagai dropdown; frontend memakai kolom teks bebas.
- Mode tiruan: Shift + klik "Sinkronisasi Data" mensimulasikan kegagalan.
