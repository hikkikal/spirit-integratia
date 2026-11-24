# Kontrak API Admin Super

Frontend: `src/services/super.js`. Backend: `AdminSuperController`. Semua rute di bawah `/api`, sesi Sanctum yang sama,
dibatasi `peran:admin_super`. Respons `{ "data": ... }`; galat `{ "message": "..." }`.
`nip` pada pengguna = `users.username` (identitas login). Kode peran frontend: `dosen`, `keuangan`, `pimpinan`, `admin` (= `admin_input` di DB), `admin_super`.

| Metode & rute | Fungsi | Respons `data` |
|---|---|---|
| `GET /admin-super/pengguna` | Semua pengguna | `[{nip, nama, email, roles:[...], aktif:bool, loginTerakhir:'25 Okt 2026, 08:10' atau '-'}]` |
| `PUT /admin-super/pengguna/{nip}/role` `{roles:[...]}` | Mengganti seluruh daftar role | baris pengguna terbaru |
| `PATCH /admin-super/pengguna/{nip}` `{aktif:bool}` | Aktif/nonaktifkan | baris pengguna terbaru |
| `GET /admin-super/log?hari=7&status=Gagal&q=nama` | Log aktivitas, terbaru dulu (maks 500) | `[{waktu, nama, role:'Admin Super', aksi, status:'Berhasil'\|'Gagal'}]` |

`hari`: 7, 30, atau 0 (semua); nilai lain dianggap 7. `status` dan `q` boleh kosong. `loginTerakhir` diambil dari log aksi "Login ..." yang berhasil.

Aturan yang dijaga server (jawaban 422 dengan pesan): minimal satu role; role dikenal; role Dosen hanya untuk akun yang punya `id_dosen`;
tidak boleh mencabut Admin Super dari akun sendiri; harus tersisa minimal satu Admin Super aktif; tidak boleh menonaktifkan akun sendiri atau Admin Super.
Akun nonaktif langsung ditolak (403) di semua rute berperan walau sesinya masih ada. Setiap perubahan tercatat di `log_aktivitas` (satu baris per role yang ditambah/dihapus).

Belum ada: menambah pengguna baru (halaman belum memintanya; akun SSO baru didaftarkan lewat `db/daftar_akun_sso.sql`).
