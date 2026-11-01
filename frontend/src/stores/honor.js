import { reactive } from 'vue'
import { USE_MOCK } from '../services/api.js'
import { getPeriode, getDaftarDosen, verifikasi } from '../services/data.js'
import { periodeOptions } from '../data/mock.js'

// State bersama antar halaman Keuangan (periode terpilih, daftar dosen, kunci periode).
// Mode API: daftar dosen dimuat dari backend per periode. Kunci periode & riwayat ekspor masih lokal (belum ada API).
export const honorState = reactive({
  periode: periodeOptions[0],
  periodeOptions: [...periodeOptions],
  dosen: [],
  muat: false,
  galat: '',
  terkunci: {}, // { 'Ganjil 2026/2027': true }
  riwayat: [
    '10 Okt 2026 14:20 · Excel · Keuangan 1',
    '08 Okt 2026 09:05 · PDF · Keuangan 2',
  ],
})

let janjiPeriode = null
// Muat daftar periode sekali; pilih periode pertama bila pilihan sekarang tidak ada.
export function siapkanPeriode() {
  if (!janjiPeriode) {
    janjiPeriode = getPeriode().then((p) => {
      honorState.periodeOptions = p
      if (!p.includes(honorState.periode) && p.length) honorState.periode = p[0]
    })
  }
  return janjiPeriode
}

export async function muatDaftar() {
  honorState.muat = true
  honorState.galat = ''
  try {
    await siapkanPeriode()
    honorState.dosen = await getDaftarDosen(honorState.periode)
  } catch (e) {
    honorState.galat = e.message || 'Gagal memuat data.'
    honorState.dosen = []
  } finally {
    honorState.muat = false
  }
}

export async function tandaiTerverifikasi(id) {
  if (!USE_MOCK) await verifikasi(id, honorState.periode)
  const d = honorState.dosen.find((x) => x.id === id)
  if (d) d.status = 'terverifikasi'
}

export function kunciPeriode(periode) {
  honorState.terkunci[periode] = true
}

const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
const dua = (n) => String(n).padStart(2, '0')

export function catatEkspor(format, oleh) {
  const t = new Date()
  const waktu = `${dua(t.getDate())} ${BULAN[t.getMonth()]} ${t.getFullYear()} ${dua(t.getHours())}:${dua(t.getMinutes())}`
  honorState.riwayat.unshift(`${waktu} · ${format} · ${oleh}`)
}
