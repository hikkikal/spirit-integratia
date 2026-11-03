// Data tiruan Admin Input. Nilai tarif dan nomor peraturan hanya contoh (placeholder dari desain),
// bukan angka resmi. Diganti panggilan API lewat services/admin.js saat backend siap.
import { reactive } from 'vue'

export const SEMESTER_AKTIF = 'Ganjil 2026/2027 (20261)'

export const JABATAN_OPSI = [
  'Guru Besar IV/d - Pembina Utama Madya',
  'Guru Besar IV/e - Pembina Utama',
  'Lektor Kepala IV/a - Pembina',
  'Lektor Kepala IV/b - Pembina Tingkat I',
  'Lektor III/c - Penata',
  'Lektor III/d - Penata Tingkat I',
  'Asisten Ahli III/a - Penata Muda',
  'Asisten Ahli III/b - Penata Muda Tingkat I',
]

export const koneksiSister = reactive({
  status: 'Terhubung',
  layanan: 'SISTER Web Service',
  metode: 'API (REST)',
  terakhir: '25 Okt 2026, 20:58',
  dijalankan: 'Admin Input',
})

// Endpoint yang ditarik tiap sinkronisasi, beserta atribut yang diambil (sesuai pemetaan SISTER).
export const ENDPOINT_SISTER = [
  { nama: 'BKD Akhir', path: '/bkd/laporan_akhir_bkd', atribut: ['id_smt', 'sks_kinerja_ajar', 'sks_lebih_ajar', 'id_jabfung', 'simpulan_asesor'] },
  { nama: 'BKD Pengajaran', path: '/bkd/ajar', atribut: ['id_smt', 'id_katgiat', 'judul_keg', 'nm_kat', 'stat_kegiatan', 'nilai'] },
  { nama: 'List Data Pengajaran', path: '/pengajaran', atribut: ['id_pengajaran', 'id_sdm', 'id_semester', 'kode_matakuliah', 'sks', 'id_kelas'] },
  { nama: 'Detail Pengajaran', path: '/pengajaran/{id}', atribut: ['mata_kuliah', 'kelas', 'sks', 'sks_mata_kuliah', 'tatap_muka_rencana', 'tatap_muka_realisasi'] },
  { nama: 'Pegawai di Perguruan Tinggi', path: '/referensi/sdm', atribut: ['id_sdm', 'nama_sdm', 'nidn', 'nip', 'nuptk', 'id_stat_pegawai', 'jenis_sdm'] },
  { nama: 'Jabatan Fungsional', path: '/referensi/jabatan_fungsional', atribut: ['id_jabfung', 'nm_jabfung'] },
]

const DTK = [1.2, 2.1, 2.1, 4.6, 4.6, 0.7]
const REKORD = [142, 318, 256, 372, 43, 8]

// Membuat rincian log satu sinkronisasi. berhenti = nomor endpoint (1..6) yang gagal, atau 0 bila semua berhasil.
export function buatLog({ id, mulai, durasi, berhenti = 0, pesan = '' }) {
  const endpoint = ENDPOINT_SISTER.map((e, i) => {
    const no = i + 1
    const status = !berhenti || no < berhenti ? 'berhasil' : no === berhenti ? 'gagal' : 'dilewati'
    return {
      no, nama: e.nama, path: e.path, status,
      atributStatus: e.atribut.map((a) => ({ nama: a, status: status === 'berhasil' ? 'terambil' : status })),
      detik: status === 'berhasil' ? DTK[i] : status === 'gagal' ? 90 : null,
      rekord: status === 'berhasil' ? REKORD[i] : 0,
      keterangan: status === 'gagal' ? pesan : status === 'dilewati' ? 'Dilewati karena proses berhenti' : '',
    }
  })
  const ok = endpoint.filter((e) => e.status === 'berhasil')
  return {
    id, mulai, durasi, semester: SEMESTER_AKTIF, status: berhenti ? 'gagal' : 'berhasil',
    endpointBerhasil: ok.length, endpointTotal: endpoint.length, totalRekord: ok.reduce((a, e) => a + e.rekord, 0), endpoint,
    pesanGagal: berhenti ? `Sinkronisasi berhenti di endpoint ${berhenti} (${ENDPOINT_SISTER[berhenti - 1].nama}). ${pesan}` : '',
  }
}

const PESAN_TIMEOUT = 'Request ke SISTER tidak dibalas dalam 30 detik (HTTP 504 Gateway Timeout). Sudah dicoba ulang 3 kali. Tidak ada atribut yang tersimpan dari endpoint ini.'

export const logSinkron = reactive([
  buatLog({ id: 's5', mulai: '25 Okt 2026, 20:58', durasi: '14 detik' }),
  buatLog({ id: 's4', mulai: '24 Okt 2026, 20:58', durasi: '15 detik' }),
  buatLog({ id: 's3', mulai: '23 Okt 2026, 20:58', durasi: '1 menit 52 detik', berhenti: 4, pesan: PESAN_TIMEOUT }),
  buatLog({ id: 's2', mulai: '22 Okt 2026, 20:58', durasi: '13 detik' }),
  buatLog({ id: 's1', mulai: '21 Okt 2026, 20:58', durasi: '14 detik' }),
])
export { PESAN_TIMEOUT }

export const tarifList = reactive([
  { id: 't1', jenis: 'Kerja Sama', kelas: 'Teknik Informatika - CCIT', jabatan: 'Guru Besar IV/d - Pembina Utama Madya', honor: 300000, mulai: '2026-01-01', sampai: '2026-12-31' },
  { id: 't2', jenis: 'Kerja Sama', kelas: 'Teknik Informatika - CCIT', jabatan: 'Lektor Kepala IV/a - Pembina', honor: 280000, mulai: '2026-01-01', sampai: '2026-12-31' },
  { id: 't3', jenis: 'Kerja Sama', kelas: 'Akuntansi - PT Mitra B', jabatan: 'Lektor III/c - Penata', honor: 250000, mulai: '2026-01-01', sampai: '2026-12-31' },
  { id: 't4', jenis: 'Kerja Sama', kelas: 'Akuntansi - PT Mitra B', jabatan: 'Lektor Kepala IV/a - Pembina', honor: 270000, mulai: '2026-01-01', sampai: '2026-12-31' },
  { id: 't5', jenis: 'Kerja Sama', kelas: 'Teknik Mesin - PT Mitra C', jabatan: 'Lektor III/c - Penata', honor: 250000, mulai: '2026-01-01', sampai: '2026-12-31' },
  { id: 't6', jenis: 'Kerja Sama', kelas: 'Teknik Mesin - PT Mitra C', jabatan: 'Asisten Ahli III/b - Penata Muda Tingkat I', honor: 230000, mulai: '2026-01-01', sampai: '2026-12-31' },
  { id: 't7', jenis: 'Reguler', kelas: 'Reguler', jabatan: 'Lektor III/c - Penata', honor: 130000, mulai: '2026-01-01', sampai: '2026-12-31' },
  { id: 't8', jenis: 'Reguler', kelas: 'Reguler', jabatan: 'Lektor Kepala IV/a - Pembina', honor: 150000, mulai: '2025-01-01', sampai: '2025-12-31' },
])

export const perdirList = reactive([
  { id: 'p1', nomor: 'SK-DIR/023/2026', judul: 'Standar Tarif Honorarium SKS TA 2026/2027', tanggal: '2026-08-01', berkas: 'SK_Tarif_2026.pdf', berlaku: true },
  { id: 'p2', nomor: 'Keputusan Direktur No. 638/2026', judul: 'Petunjuk Teknis Penghitungan dan Pembayaran Honorarium Kelebihan Beban Kerja Dosen', tanggal: '2026-09-15', berkas: 'Kepdir_638_2026.pdf', berlaku: true },
  { id: 'p3', nomor: 'Perdir No. 11/2025', judul: 'Pelaksanaan Pembelajaran Teori dan Praktik Program Diploma, Sarjana Terapan, dan Pascasarjana', tanggal: '2025-07-01', berkas: 'Perdir_11_2025.pdf', berlaku: true },
  { id: 'p4', nomor: 'SK-DIR/015/2025', judul: 'Standar Tarif Honorarium SKS TA 2025/2026', tanggal: '2025-08-01', berkas: 'SK_Tarif_2025.pdf', berlaku: false },
])
