// Data tiruan (sementara). TODO(backend): ganti dengan panggilan API Laravel.
// Angka mengikuti mock-up Figma (Page 1), dengan koreksi pada data yang saling bertentangan.

export const periodeOptions = ['Ganjil 2026/2027', 'Genap 2025/2026']

// Ringkasan per jurusan (Dashboard dan Laporan). Total: 180 dosen, 116 terverifikasi, 485 SKS lebih.
export const jurusan = [
  { nama: 'Teknik Sipil', dosen: 32, terverifikasi: 24, sksLebih: 96, honor: 98_400_000 },
  { nama: 'Teknik Mesin', dosen: 30, terverifikasi: 18, sksLebih: 82, honor: 84_000_000 },
  { nama: 'Teknik Elektro', dosen: 28, terverifikasi: 21, sksLebih: 75, honor: 76_900_000 },
  { nama: 'Teknik Grafika dan Penerbitan', dosen: 18, terverifikasi: 9, sksLebih: 40, honor: 41_000_000 },
  { nama: 'Akuntansi', dosen: 26, terverifikasi: 13, sksLebih: 70, honor: 71_800_000 },
  { nama: 'Administrasi Niaga', dosen: 24, terverifikasi: 20, sksLebih: 58, honor: 59_500_000 },
  { nama: 'Teknik Informatika dan Komputer', dosen: 22, terverifikasi: 11, sksLebih: 64, honor: 65_600_000 },
]

// Honor dosen yang sudah terverifikasi. Sisanya (total - ini) = belum terverifikasi.
export const honorTerverifikasi = 318_400_000

const TIK = 'Teknik Informatika dan Komputer'

// Daftar dosen untuk halaman Data Honor. Status Dosen B sengaja "menunggu"
// supaya alur "Tandai terverifikasi" di halaman Detail bisa dicoba.
export const dosen = [
  { id: 'A', nama: 'Dosen A', jurusan: TIK, jabatan: 'Kaprodi', sksWajib: 10, sksLebih: 6, honor: 960_000, status: 'terverifikasi' },
  { id: 'B', nama: 'Dosen B', jurusan: TIK, jabatan: 'Lektor', sksWajib: 10, sksLebih: 12, honor: 1_040_000, status: 'menunggu' },
  { id: 'C', nama: 'Dosen C', jurusan: TIK, jabatan: 'Lektor', sksWajib: 10, sksLebih: 2, honor: 240_000, status: 'terverifikasi' },
  { id: 'D', nama: 'Dosen D', jurusan: TIK, jabatan: 'Lektor', sksWajib: 10, sksLebih: 8, honor: 910_000, status: 'terverifikasi' },
  { id: 'E', nama: 'Dosen E', jurusan: TIK, jabatan: 'Lektor Kepala', sksWajib: 6, sksLebih: 4, honor: 680_000, status: 'terverifikasi' },
  { id: 'F', nama: 'Dosen F', jurusan: TIK, jabatan: 'Lektor Kepala', sksWajib: 6, sksLebih: 6, honor: 840_000, status: 'menunggu' },
  { id: 'G', nama: 'Dosen G', jurusan: TIK, jabatan: 'Lektor', sksWajib: 10, sksLebih: 0, honor: 0, status: 'menunggu' },
  { id: 'H', nama: 'Dosen H', jurusan: TIK, jabatan: 'Lektor', sksWajib: 10, sksLebih: 8, honor: 910_000, status: 'menunggu' },
]

// Rincian kelas per dosen. Baru tersedia untuk Dosen B.
// lebih100/lebih50/lebih0 = SKS lebih yang dibayar 100% / 50% / tidak dibayar.
const kelas = (mk, jenis, wajib, lebih100, lebih50, lebih0) => ({ mk, jenis, wajib, lebih100, lebih50, lebih0 })

export const detailKelas = {
  B: {
    golongan: 'Gol III',
    tarifReguler: 130_000,
    kelas: [
      kelas('Pemrograman Web (3A)', 'Reguler D4', 4, 0, 0, 0),
      kelas('Basis Data (2B)', 'Reguler D4', 3, 0, 0, 0),
      kelas('Statistika & Probabilitas (CCIT-A)', 'Kerja sama CCIT', 3, 0, 0, 0),
      kelas('Algoritma (1A)', 'Reguler D4', 0, 3, 0, 0),
      kelas('Sistem Operasi (2A)', 'Reguler D4', 0, 3, 0, 0),
      kelas('Pemrograman Mobile (3C)', 'Reguler D4', 0, 0, 4, 0),
      kelas('Etika Profesi (1B)', 'Reguler D4', 0, 0, 0, 0),
      kelas('Desain UI/UX (3A)', 'Reguler D4', 0, 0, 0, 0),
      kelas('Manajemen Proyek (3A)', 'Reguler D4', 0, 0, 0, 2),
    ],
  },
}
