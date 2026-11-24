<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { getLogAktivitas, getLogAksi } from '../../services/super.js'

const cari = ref('')
const periode = ref('7')
const status = ref('')
const aksi = ref('')
const daftarAksi = ref([])
const tampil = ref([])
const muat = ref(true)
const galat = ref('')

let nomor = 0 // hanya jawaban permintaan terbaru yang dipakai
async function ambil() {
  const saya = ++nomor
  muat.value = true
  galat.value = ''
  try {
    const hasil = await getLogAktivitas({ hari: Number(periode.value), status: status.value, q: cari.value, aksi: aksi.value })
    if (saya === nomor) tampil.value = hasil
  } catch (e) {
    if (saya === nomor) galat.value = e.message
  }
  if (saya === nomor) muat.value = false
}
let tunggu
watch([periode, status, aksi], ambil)
watch(cari, () => {
  clearTimeout(tunggu)
  tunggu = setTimeout(ambil, 300)
})
onMounted(() => { ambil(); getLogAksi().then((a) => { daftarAksi.value = a }).catch(() => {}) })
onUnmounted(() => clearTimeout(tunggu))
</script>

<template>
  <h1 class="page-title">Riwayat Aktivitas</h1>
  <p class="page-sub">Jejak audit seluruh aktivitas penting di sistem</p>

  <div class="filters">
    <label>Cari<input v-model="cari" type="search" placeholder="Nama / NIP" /></label>
    <label>Periode
      <select v-model="periode"><option value="7">7 Hari Terakhir</option><option value="30">30 Hari Terakhir</option><option value="0">Semua</option></select>
    </label>
    <label>Aksi
      <select v-model="aksi"><option value="">Semua aksi</option><option v-for="a in daftarAksi" :key="a" :value="a">{{ a }}</option></select>
    </label>
    <label>Status
      <select v-model="status"><option value="">Semua</option><option>Berhasil</option><option>Gagal</option></select>
    </label>
  </div>

  <section class="panel">
    <h2>Daftar Aktivitas</h2>
    <div class="scroll-x">
      <table class="tbl tbl--line">
        <thead><tr><th>Waktu</th><th>Nama</th><th>Aksi</th><th>Keterangan</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-for="(l, i) in tampil" :key="i">
            <td>{{ l.waktu }}</td>
            <td>{{ l.nama }}<div class="sub">{{ l.role }}</div></td>
            <td><span class="tag tag--gray">{{ l.aksi }}</span></td>
            <td class="ket">{{ l.keterangan || '–' }}</td>
            <td><span :class="l.status === 'Berhasil' ? 'badge-ok' : 'tag tag--bad'">{{ l.status }}</span></td>
          </tr>
          <tr v-if="muat"><td colspan="5">Memuat…</td></tr>
          <tr v-else-if="galat"><td colspan="5" class="galat" role="alert">{{ galat }}</td></tr>
          <tr v-else-if="!tampil.length"><td colspan="5">Belum ada aktivitas.</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
h2 { font-size: 18px; margin: 0 0 8px; }
.sub { font-size: 15px; color: #4a5755; }
.galat { color: #b3261e; }
.ket { max-width: 460px; overflow-wrap: anywhere; }
</style>
