import { computed, ref } from 'vue'
import { auth } from '../stores/auth.js'

// Popup koreksi data dosen (jabatan, golongan, tugas tambahan). Boleh: Keuangan dan Admin Input. Pimpinan hanya melihat.
export const PERAN_KOREKSI = ['keuangan', 'admin']

let urut = 0
export function useKoreksi(setelah = () => {}) {
  const bisa = computed(() => PERAN_KOREKSI.includes(auth.user?.role))
  const dibuka = ref(null)      // tetap terisi selama animasi tutup
  const tampil = ref(false)
  const buka = (id, nama) => { dibuka.value = { id, nama, n: ++urut }; tampil.value = true }
  const tutup = () => { tampil.value = false }
  return { bisa, dibuka, tampil, buka, tutup, setelah }
}
