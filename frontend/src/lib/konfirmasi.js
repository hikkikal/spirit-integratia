import { reactive } from 'vue'

// Pengganti window.confirm: dialog di dalam halaman, hasilnya Promise<boolean>.
// const ya = await tanya({ judul: 'Hitung ulang?', isi: '...', ya: 'Hitung ulang' })
export const dialog = reactive({ buka: false, judul: '', isi: '', ya: 'Ya', tidak: 'Batal', _selesai: null })

export function tanya({ judul, isi = '', ya = 'Ya', tidak = 'Batal' }) {
  return new Promise((selesai) => {
    Object.assign(dialog, { buka: true, judul, isi, ya, tidak, _selesai: selesai })
  })
}
export function jawab(hasil) {
  dialog.buka = false
  const s = dialog._selesai
  dialog._selesai = null
  s?.(hasil)
}
