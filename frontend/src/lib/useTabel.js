import { computed, ref, watch } from 'vue'

// Sorting dan pagination di sisi browser untuk satu tabel.
//   sumber     : ref/computed berisi array baris (sudah terfilter)
//   pembanding : { kunci: (a, b) => angka }, hanya kolom yang terdaftar yang dapat diurutkan
// Klik kolom: naik -> turun -> urutan awal.
export function useTabel(sumber, pembanding = {}, ukuranAwal = 25) {
  const kunci = ref('')
  const arah = ref('') // '' | 'asc' | 'desc'
  const halaman = ref(1)
  const ukuran = ref(ukuranAwal)

  const urut = computed(() => {
    const data = sumber.value ?? []
    const f = pembanding[kunci.value]
    if (!f || !arah.value) return data
    const k = arah.value === 'asc' ? 1 : -1
    return [...data].sort((a, b) => k * f(a, b))
  })
  const total = computed(() => urut.value.length)
  const jumlahHalaman = computed(() => Math.max(1, Math.ceil(total.value / ukuran.value)))
  const baris = computed(() => urut.value.slice((halaman.value - 1) * ukuran.value, halaman.value * ukuran.value))
  const dari = computed(() => (total.value ? (halaman.value - 1) * ukuran.value + 1 : 0))
  const sampai = computed(() => Math.min(total.value, halaman.value * ukuran.value))

  function ganti(k) {
    if (!pembanding[k]) return
    if (kunci.value !== k) { kunci.value = k; arah.value = 'asc' }
    else if (arah.value === 'asc') arah.value = 'desc'
    else { kunci.value = ''; arah.value = '' }
    halaman.value = 1
  }
  const ke = (h) => { halaman.value = Math.min(Math.max(1, h), jumlahHalaman.value) }
  watch(sumber, () => { halaman.value = 1 })
  watch(ukuran, () => { halaman.value = 1 })

  return { kunci, arah, halaman, ukuran, baris, total, jumlahHalaman, dari, sampai, ganti, ke }
}

// Pembanding siap pakai.
export const teks = (f) => (a, b) => String(f(a) ?? '').localeCompare(String(f(b) ?? ''), 'id', { sensitivity: 'base', numeric: true })
export const angka = (f) => (a, b) => (Number(f(a)) || 0) - (Number(f(b)) || 0)
