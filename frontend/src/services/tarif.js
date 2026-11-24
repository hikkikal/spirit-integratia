// Daftar lengkap master tarif untuk halaman Tarif & Ketentuan (Keuangan dan Pimpinan), hanya-baca.
// Sumbernya sama dengan halaman Master Tarif milik Admin Input; perubahan tarif tetap hanya dari Admin Input.
import { api, USE_MOCK } from './api.js'
import { tarifList } from '../data/admin.js'

export async function getTarifDaftar() {
  if (USE_MOCK) return tarifList.map((t) => ({ ...t }))
  return (await api('/tarif/daftar')).data
}

// Tarif honor kegiatan non-pengajaran (SBM Internal PNJ 23/2026), hanya-baca.
const NP_MOCK = [
  ['skripsi_penguji_proposal', 'Penguji Proposal Skripsi/Tugas Akhir', 'per mahasiswa', 50000, 44750],
  ['skripsi_penguji', 'Penguji Skripsi/Tugas Akhir', 'per mahasiswa', 75000, 67100],
  ['skripsi_pembimbing', 'Pembimbing Skripsi/Tugas Akhir', 'per mahasiswa', 350000, 310000, 'Dibimbing lebih dari 1 dosen: pembimbing 1 = 60%, pembimbing 2 = 40%'],
  ['tesis_penguji_proposal', 'Penguji Seminar Proposal Tesis', 'per mahasiswa', 50000, 44750],
  ['tesis_penguji_hasil', 'Penguji Seminar Hasil Penelitian Tesis', 'per mahasiswa', 200000, 178950],
  ['tesis_pembimbing_utama', 'Pembimbing Tesis Utama', 'per mahasiswa', 750000, 671100, 'Minimal Doktor (S3)'],
  ['tesis_pembimbing_pendamping', 'Pembimbing Tesis Pendamping', 'per mahasiswa', 500000, 447300, 'Minimal Doktor (S3)'],
  ['pkl_pembimbing', 'Pembimbing Magang/Praktik Lapangan/On Job Training', 'per mahasiswa', 200000, 178900],
  ['pembimbing_akademik', 'Pembimbing Akademik', 'per mahasiswa per semester', 20000, 17900],
].map(([kode, nama, satuan, golIV, golIII, keterangan = null]) => ({ kode, nama, satuan, golIV, golIII, keterangan }))

export async function getTarifNonPengajaran() {
  if (USE_MOCK) return NP_MOCK.map((t) => ({ ...t }))
  return (await api('/tarif/non-pengajaran')).data
}
