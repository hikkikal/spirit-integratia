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
  { id: 'A', nama: 'Dosen A', jurusan: TIK, jabatan: 'Kaprodi', golongan: 'Gol III', sksWajib: 10, sksLebih: 6, honor: 960_000, status: 'terverifikasi' },
  { id: 'B', nama: 'Dosen B', jurusan: TIK, jabatan: 'Lektor', golongan: 'Gol III', sksWajib: 10, sksLebih: 12, honor: 1_040_000, status: 'menunggu' },
  { id: 'C', nama: 'Dosen C', jurusan: TIK, jabatan: 'Lektor', golongan: 'Gol III', sksWajib: 10, sksLebih: 2, honor: 240_000, status: 'terverifikasi' },
  { id: 'D', nama: 'Dosen D', jurusan: TIK, jabatan: 'Lektor', golongan: 'Gol III', sksWajib: 10, sksLebih: 8, honor: 910_000, status: 'terverifikasi' },
  { id: 'E', nama: 'Dosen E', jurusan: TIK, jabatan: 'Lektor Kepala', golongan: 'Gol IV', sksWajib: 6, sksLebih: 4, honor: 680_000, status: 'terverifikasi' },
  { id: 'F', nama: 'Dosen F', jurusan: TIK, jabatan: 'Lektor Kepala', golongan: 'Gol IV', sksWajib: 6, sksLebih: 6, honor: 840_000, status: 'menunggu' },
  { id: 'G', nama: 'Dosen G', jurusan: TIK, jabatan: 'Lektor', golongan: 'Gol III', sksWajib: 10, sksLebih: 0, honor: 0, status: 'menunggu' },
  { id: 'H', nama: 'Dosen H', jurusan: TIK, jabatan: 'Lektor', golongan: 'Gol III', sksWajib: 10, sksLebih: 8, honor: 910_000, status: 'menunggu' },
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

// Rekap Laporan & Ekspor (mock-up Figma, periode Genap 2025/2026).
export const laporanJurusan = [
  { nama: 'Teknik Informatika & Komputer', dosen: 28, sksLebih: 162, honor: 92_500_000, terverifikasi: 24, menunggu: 4 },
  { nama: 'Teknik Sipil', dosen: 24, sksLebih: 140, honor: 71_200_000, terverifikasi: 20, menunggu: 4 },
  { nama: 'Teknik Mesin', dosen: 20, sksLebih: 118, honor: 58_900_000, terverifikasi: 15, menunggu: 5 },
  { nama: 'Teknik Elektro', dosen: 17, sksLebih: 96, honor: 49_600_000, terverifikasi: 12, menunggu: 5 },
  { nama: 'Akuntansi', dosen: 15, sksLebih: 60, honor: 28_400_000, terverifikasi: 10, menunggu: 5 },
  { nama: 'Administrasi Niaga', dosen: 12, sksLebih: 41, honor: 17_800_000, terverifikasi: 9, menunggu: 3 },
]

// Tarif mengajar. Rp130.000 (reguler, Gol III) dan Rp250.000 (kerja sama) mengikuti mock-up Figma.
export const TARIF_KERJA_SAMA = 250_000

// Tarif honor unsur pendidikan di luar mengajar. Sumber: SK SBM Internal PNJ No. 23/2026 (Bab 3, poin B–D).
// Satuan "mhs" = per mahasiswa. Dasar hukum perhitungan tetap diverifikasi oleh Bidang Keuangan.
export const tarifPendidikan = [
  { kegiatan: 'Penguji proposal skripsi/tugas akhir', satuan: 'mhs', gol4: 50_000, gol3: 44_750 },
  { kegiatan: 'Penguji skripsi/tugas akhir', satuan: 'mhs', gol4: 75_000, gol3: 67_100 },
  { kegiatan: 'Pembimbing skripsi/tugas akhir', satuan: 'mhs', gol4: 350_000, gol3: 310_000, catatan: 'Dua pembimbing: 60% dan 40%' },
  { kegiatan: 'Penguji seminar proposal tesis', satuan: 'mhs', gol4: 50_000, gol3: 44_750 },
  { kegiatan: 'Penguji seminar hasil penelitian tesis', satuan: 'mhs', gol4: 200_000, gol3: 178_950 },
  { kegiatan: 'Pembimbing tesis utama', satuan: 'mhs', gol4: 750_000, gol3: 671_100, catatan: 'Minimal Doktor (S3)' },
  { kegiatan: 'Pembimbing tesis pendamping', satuan: 'mhs', gol4: 500_000, gol3: 447_300, catatan: 'Minimal Doktor (S3)' },
  { kegiatan: 'Pembimbing magang/praktik lapangan/OJT', satuan: 'mhs', gol4: 200_000, gol3: 178_900 },
  { kegiatan: 'Pembimbing akademik', satuan: 'mhs per semester', gol4: 20_000, gol3: 17_900 },
]
