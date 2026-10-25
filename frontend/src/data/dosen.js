// Data tiruan untuk role Dosen (Dosen B). TODO(backend): ganti dengan API.
// Nilai mengikuti mock-up Figma "Role Dosen".

export const TARIF_REGULER = 130_000

export const profil = {
  nama: 'Dosen B',
  gelar: 'Dosen B, S.KOM., M.KOM.',
  jabatan: 'Lektor (Gol III)',
  prodi: 'D4 Teknik Informatika',
  kampus: 'Politeknik Negeri Jakarta',
  email: 'dosenb@pnj.ac.id',
  // Nomor sensitif harus sudah disamarkan oleh backend. Frontend tidak pernah menerima nomor utuh.
  nidn: '04•••••••01',
  sertifikat: '2304•••••••78',
  status: 'Dosen (DS)',
  ttl: '-',
  hp: '08954335856',
  alamatKampus: 'KAMPUS BARU UNIVERSITAS INDONESIA, KOTA DEPOK, PROV. JAWA BARAT - 16425',
  jurusan: 'Teknik Informatika',
  jabatanFungsional: 'LEKTOR KEPALA (01 Oktober 2022)/IV.A',
}

// Riwayat honor per periode, terbaru di atas. Honor dihitung dari SKS lebih (lihat lib/honor.js).
export const riwayat = [
  { periode: 'Genap 2025/2026', sksWajib: 10, terlaksana: 22, lebih: 12, status: 'terverifikasi' },
  { periode: 'Ganjil 2025/2026', sksWajib: 10, terlaksana: 18, lebih: 8, status: 'terverifikasi' },
  { periode: 'Genap 2024/2025', sksWajib: 10, terlaksana: 16, lebih: 6, status: 'terverifikasi' },
  { periode: 'Ganjil 2024/2025', sksWajib: 10, terlaksana: 14, lebih: 4, status: 'terverifikasi' },
]

// Rincian kelas hanya tersedia untuk periode terbaru.
export const kelasPeriode = {
  'Genap 2025/2026': [
    { mk: 'Pemrograman Web', kelas: 'TI-3A', prodi: 'D4 Teknik Informatika', sks: 4, sumber: 'Unsur I, hal. 14' },
    { mk: 'Basis Data', kelas: 'TI-2B', prodi: 'D4 Teknik Informatika', sks: 4, sumber: 'Unsur I, hal. 14' },
    { mk: 'Rekayasa Perangkat Lunak', kelas: 'TI-5A', prodi: 'D4 Teknik Informatika', sks: 4, sumber: 'Unsur I, hal. 15' },
    { mk: 'Jaringan Komputer', kelas: 'TI-4A', prodi: 'D3 Teknik Informatika', sks: 4, sumber: 'Unsur I, hal. 15' },
    { mk: 'Algoritma & Struktur Data', kelas: 'TI-1C', prodi: 'D4 Teknik Informatika', sks: 4, sumber: 'Unsur I, hal. 16' },
    { mk: 'Sistem Operasi', kelas: 'TI-3B', prodi: 'D4 Teknik Informatika', sks: 2, sumber: 'Unsur I, hal. 16' },
  ],
}

// Rincian per minggu perkuliahan (skema tarif Perdir). Angka di mock-up identik tiap minggu
// dan belum cocok dengan honor Rp1.040.000, jadi ini hanya contoh tampilan.
const minggu = (n) => ({
  label: `Minggu ${n}`,
  totalSks: 22,
  wm: 6,
  tarif: ['S2: 220000(SKS:2)', 'R: 160000(SKS:11)', 'K-1: 0(SKS:3)'],
  rincian: ['S2: 220000x2.00x100%=440000', 'R: 160000x4.00x100%=640000', 'R: 160000x4.00x50%=320000'],
  total: 1_400_000,
  pajak: 210_000,
  netto: 1_190_000,
})
export const mingguPeriode = {
  'Genap 2025/2026': [1, 2, 3, 4, 5, 6].map(minggu),
}

// Peran yang dimiliki pengguna ini selain dosen (untuk menu Ganti Akun).
export const deskripsiRole = {
  dosen: 'Lihat SKS, rincian data, dan honor pribadi',
  pimpinan: 'Lihat ringkasan dan data honor seluruh dosen',
  keuangan: 'Verifikasi honor dan ekspor laporan',
  admin: 'Input dan kelola data dosen',
}
