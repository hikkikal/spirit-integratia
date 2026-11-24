// Data tiruan Non-Pengajaran untuk mode mock. Mode API memakai backend (/api/non-pengajaran).
// Isinya mengikuti data contoh di db/seed.sql (Dosen A..H = dosen contoh).
const TIK = 'Teknik Informatika dan Komputer'
const PRODI = 'D4 - Teknik Informatika'
const PT = 'Politeknik Negeri Jakarta'
const k = (jenis, kategori, judul, honor) => ({ jenis, kategori, judul, prodi: PRODI, kampus: PT, honor, jumlahMhs: 2, catatan: 'dibimbing 2 dosen: 60% dari tarif', detail: { sk: '524/X', komunal: 'Tidak', flagship: 'Tidak', mahasiswa: [{ nama: 'Mhs Contoh Satu', nim: '2107412001', peran: 'Individu/Mandiri' }, { nama: 'Mhs Contoh Dua', nim: '2107412002', peran: 'Individu/Mandiri' }], pembimbing: [{ nama: 'Dosen A', peran: 'Skripsi (pembimbing utama)', urutan: 1, saya: true }, { nama: 'Dosen B', peran: 'Skripsi (pembimbing pendamping)', urutan: 2 }] } })

export const JENIS_KEGIATAN = ['Skripsi', 'Tesis', 'PKL/Magang', 'Pembimbing Akademik', 'Seminar Proposal', 'Tugas Akhir']

export const npDosen = [
  { id: 'A', nama: 'Dosen A', jabatan: 'Kaprodi', jurusan: TIK, status: 'terverifikasi', kegiatan: [
    k('Skripsi', 'Skripsi (pembimbing utama)', 'Deteksi Hoaks Berita Online Menggunakan IndoBERT', 400_000),
    k('Seminar Proposal', 'Penguji seminar proposal', 'Seminar proposal: Chatbot Layanan Akademik', 250_000),
    k('Tugas Akhir', 'Tugas Akhir (pembimbing utama)', 'Aplikasi Presensi Mahasiswa Berbasis QR Code', 300_000)] },
  { id: 'B', nama: 'Dosen B', jabatan: 'Lektor', jurusan: TIK, status: 'terverifikasi', kegiatan: [
    k('Skripsi', 'Skripsi (pembimbing utama)', 'Analisis Sentimen Ulasan Aplikasi Mobile Menggunakan Naive Bayes', 500_000),
    k('Skripsi', 'Skripsi (pembimbing pendamping)', 'Rancang Bangun Aplikasi Inventaris Laboratorium Berbasis Laravel', 400_000),
    k('Skripsi', 'Skripsi (pembimbing utama)', 'Sistem Informasi Peminjaman Alat Laboratorium Berbasis Web', 500_000),
    k('Pembimbing Akademik', 'Pembimbing Akademik', 'Bimbingan akademik mahasiswa kelas TI-5B (26 mahasiswa)', 500_000),
    k('Skripsi', 'Skripsi (pembimbing utama)', 'Prediksi Churn Pelanggan Telekomunikasi dengan Random Forest', 520_000)] },
  { id: 'C', nama: 'Dosen C', jabatan: 'Lektor', jurusan: TIK, status: 'terverifikasi', kegiatan: [
    k('Pembimbing Akademik', 'Pembimbing Akademik', 'Bimbingan akademik mahasiswa kelas TI-3A (30 mahasiswa)', 600_000),
    k('PKL/Magang', 'Pembimbing magang', 'Pembimbingan magang industri (3 mahasiswa)', 700_000),
    k('Skripsi', 'Skripsi (pembimbing utama)', 'Sistem Rekomendasi Mata Kuliah Pilihan', 600_000)] },
  { id: 'D', nama: 'Dosen D', jabatan: 'Lektor', jurusan: TIK, status: 'terverifikasi', kegiatan: [
    k('Tugas Akhir', 'Tugas Akhir (pembimbing utama)', 'Dasbor Monitoring Kualitas Udara Kampus', 250_000),
    k('Seminar Proposal', 'Penguji seminar proposal', 'Seminar proposal: Sistem Pakar Diagnosis Tanaman Hias', 200_000)] },
  { id: 'E', nama: 'Dosen E', jabatan: 'Lektor Kepala', jurusan: TIK, status: 'terverifikasi', kegiatan: [
    k('Skripsi', 'Skripsi (pembimbing pendamping)', 'Klasifikasi Citra Daun dengan Convolutional Neural Network', 250_000),
    k('Tesis', 'Tesis (pembimbing utama)', 'Optimasi Penjadwalan Kuliah dengan Algoritma Genetika', 500_000),
    k('Seminar Proposal', 'Penguji seminar proposal tesis', 'Seminar proposal tesis: Deteksi Anomali Jaringan', 200_000)] },
  { id: 'F', nama: 'Dosen F', jabatan: 'Lektor Kepala', jurusan: TIK, status: 'menunggu', kegiatan: [
    k('Skripsi', 'Skripsi (pembimbing utama)', 'Aplikasi Kasir Berbasis Cloud untuk UMKM', 700_000),
    k('Pembimbing Akademik', 'Pembimbing Akademik', 'Bimbingan akademik mahasiswa kelas TI-1C (32 mahasiswa)', 650_000)] },
  { id: 'G', nama: 'Dosen G', jabatan: 'Lektor', jurusan: TIK, status: 'menunggu', kegiatan: [
    k('Pembimbing Akademik', 'Pembimbing Akademik', 'Bimbingan akademik mahasiswa kelas TI-7A (28 mahasiswa)', 1_100_000)] },
  { id: 'H', nama: 'Dosen H', jabatan: 'Lektor', jurusan: TIK, status: 'menunggu', kegiatan: [
    k('Seminar Proposal', 'Penguji seminar proposal', 'Seminar proposal: Prediksi Harga Saham dengan LSTM', 300_000),
    k('PKL/Magang', 'Pembimbing magang', 'Pembimbingan magang proyek perangkat lunak (2 mahasiswa)', 350_000)] },
]
