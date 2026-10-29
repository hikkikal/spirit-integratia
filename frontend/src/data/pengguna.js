// Data tiruan untuk halaman Admin Super. Diganti panggilan API saat backend siap.
import { reactive } from 'vue'

export const ROLE_INFO = {
  dosen: { label: 'Dosen', ket: 'Lihat sks, rincian data, dan honor pribadi', kode: 'D' },
  keuangan: { label: 'Keuangan', ket: 'Periksa & Verifikasi Data SKS/Honorarium', kode: 'KU' },
  pimpinan: { label: 'Pimpinan', ket: 'Pantau Rekap & Kelebihan SKS/Honorarium', kode: 'PM' },
  admin: { label: 'Admin Input', ket: 'Kelola Sinkronasi & Data Pendukung', kode: 'AI' },
  admin_super: { label: 'Admin Super', ket: 'Kelola Role & Hak Akses Pengguna', kode: 'AS' },
}

export const penggunaList = reactive([
  { nip: '198503122010121001', nama: 'Budi Santoso', email: 'budi.santoso@pnj.ac.id', roles: ['admin'], aktif: true, loginTerakhir: '25 Okt 2026, 08:10' },
  { nip: '197902152005012002', nama: 'Sari Wulandari', email: 'sari.w@pnj.ac.id', roles: ['keuangan'], aktif: true, loginTerakhir: '24 Okt 2026, 09:58' },
  { nip: '196805201994031003', nama: 'Dr. Hendra Kusuma', email: 'hendra.k@pnj.ac.id', roles: ['pimpinan'], aktif: true, loginTerakhir: '23 Okt 2026, 14:20' },
  { nip: '198811302015041001', nama: 'Dosen B', email: 'dosen.b@pnj.ac.id', roles: ['dosen'], aktif: true, loginTerakhir: '26 Okt 2026, 07:45' },
  { nip: '199001052018032001', nama: 'Rina Anggraini', email: 'rina.a@pnj.ac.id', roles: ['keuangan'], aktif: false, loginTerakhir: '02 Sep 2026, 10:00' },
  { nip: '197505102001121001', nama: 'Agus Prasetyo', email: 'agus.p@pnj.ac.id', roles: ['admin_super'], aktif: true, loginTerakhir: '26 Okt 2026, 06:30' },
])

export const logList = reactive([
  { waktu: '25 Okt 2026, 21:41', nama: 'Budi Santoso', role: 'Pimpinan', aksi: 'Tambah role', status: 'Berhasil' },
  { waktu: '25 Okt 2026, 20:15', nama: 'Agus Prasetyo', role: 'Admin Super', aksi: 'Nonaktifkan pengguna', status: 'Berhasil' },
  { waktu: '25 Okt 2026, 19:02', nama: 'Agus Prasetyo', role: 'Admin Super', aksi: 'Ubah role', status: 'Berhasil' },
  { waktu: '25 Okt 2026, 16:48', nama: 'Rina Anggraini', role: 'Keuangan', aksi: 'Login', status: 'Gagal' },
  { waktu: '25 Okt 2026, 10:30', nama: 'Sari Wulandari', role: 'Keuangan', aksi: 'Verifikasi honor', status: 'Berhasil' },
  { waktu: '24 Okt 2026, 09:58', nama: 'Dr. Hendra Kusuma', role: 'Pimpinan', aksi: 'Login', status: 'Berhasil' },
  { waktu: '23 Okt 2026, 13:12', nama: 'Agus Prasetyo', role: 'Admin Super', aksi: 'Tambah role', status: 'Berhasil' },
])

const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
const dua = (n) => String(n).padStart(2, '0')

// Mencatat aktivitas Admin Super ke log (terbaru di atas). Backend yang mencatat sebenarnya.
export function catatLog(pelaku, aksi, status = 'Berhasil') {
  const t = new Date()
  logList.unshift({
    waktu: `${dua(t.getDate())} ${BULAN[t.getMonth()]} ${t.getFullYear()}, ${dua(t.getHours())}:${dua(t.getMinutes())}`,
    nama: pelaku?.name ?? 'Super Admin',
    role: 'Admin Super',
    aksi,
    status,
  })
}
