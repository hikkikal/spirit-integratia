import { reactive } from 'vue'
import { dosen as dosenAwal, periodeOptions } from '../data/mock.js'

// State bersama antar halaman Keuangan (periode terpilih, status verifikasi, kunci periode).
// TODO(backend): pindahkan ke API; sementara disimpan di memori.
export const honorState = reactive({
  periode: periodeOptions[0],
  dosen: dosenAwal.map((d) => ({ ...d })),
  terkunci: {}, // { 'Ganjil 2026/2027': true }
  riwayat: [
    '10 Okt 2026 14:20 · Excel · Keuangan 1',
    '08 Okt 2026 09:05 · PDF · Keuangan 2',
  ],
})

export function tandaiTerverifikasi(id) {
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
