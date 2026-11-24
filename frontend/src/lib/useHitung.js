import { computed, ref } from 'vue'
import { USE_MOCK } from '../services/api.js'
import { hitungUlang } from '../services/data.js'
import { honorState } from '../stores/honor.js'
import { auth } from '../stores/auth.js'
import { tanya } from './konfirmasi.js'

// Tombol "Hitung ulang": satu fungsi untuk honor pengajaran DAN non-pengajaran, dipakai di kedua halaman daftar.
// Hanya Keuangan, hanya mode API (data tiruan tidak punya mesin hitung).
export function useHitung(setelah) {
  const bisaHitung = computed(() => !USE_MOCK && auth.user?.role === 'keuangan')
  const menghitung = ref(false)
  const info = ref('')
  const galat = ref('')
  async function hitung() {
    if (menghitung.value) return
    if (!(await tanya({ judul: `Hitung ulang honor periode ${honorState.periode}?`, isi: 'Ini menghitung honor pengajaran DAN non-pengajaran sekaligus.\nData yang sudah terverifikasi atau terkunci tidak diubah.', ya: 'Hitung ulang' }))) return
    menghitung.value = true
    info.value = ''
    galat.value = ''
    try {
      const h = await hitungUlang(honorState.periode)
      const lewat = h.dilewati.terverifikasi + h.dilewati.terkunci + h.dilewati.disesuaikan
      const n = h.nonPengajaran
      info.value = `Pengajaran: ${h.dihitung} dosen dihitung ulang${lewat ? `, ${lewat} dilewati (terverifikasi/terkunci/disesuaikan manual)` : ''}.`
        + (n ? ` Non-pengajaran: ${n.dihitung} kegiatan dihitung${n.dilewati ? `, ${n.dilewati} dilewati (dosen sudah terverifikasi)` : ''}${n.tanpaTarif ? `, ${n.tanpaTarif} belum punya tarif` : ''}.` : '')
      if (h.tanpaTarif?.length) galat.value = `Tidak dihitung karena tarif belum diatur: ${h.tanpaTarif.join(', ')}.`
      await setelah?.()
    } catch (e) {
      galat.value = e.message || 'Gagal menghitung ulang.'
    }
    menghitung.value = false
  }
  return { bisaHitung, menghitung, info, galat, hitung }
}
