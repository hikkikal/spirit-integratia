<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { LABEL_CEK } from '../../lib/perhatian.js'
import { honorState, siapkanPeriode } from '../../stores/honor.js'
import { USE_MOCK } from '../../services/api.js'
import { useHitung } from '../../lib/useHitung.js'
import { useTabel, teks, angka } from '../../lib/useTabel.js'
import { getNpDaftar } from '../../services/nonpengajaran.js'
import { JENIS_KEGIATAN } from '../../data/nonpengajaran.js'
import { rupiah } from '../../lib/format.js'
import { useBase } from '../../lib/useBase.js'
import { useKoreksi } from '../../lib/useKoreksi.js'
import KoreksiDosenModal from '../../components/KoreksiDosenModal.vue'
import StatusBadge from '../../components/StatusBadge.vue'
import PeriodeSelect from '../../components/PeriodeSelect.vue'
import ThUrut from '../../components/ThUrut.vue'
import Pager from '../../components/Pager.vue'

// Daftar honor kegiatan non-pengajaran (bimbingan, penguji, magang). Dipakai Keuangan dan Pimpinan.
const base = useBase()
// Tautan dari dashboard: ?cek=kunci menampilkan hanya data yang ditandai.
const route = useRoute()
const router = useRouter()
const cek = computed(() => String(route.query.cek || ''))
const hapusCek = () => router.replace({ query: { ...route.query, cek: undefined } })
const cari = ref('')
const jenis = ref([]) // boleh pilih lebih dari satu; kosong = semua
const filterJurusan = ref(USE_MOCK ? 'Teknik Informatika dan Komputer' : '')
const semua = ref([]) // tanpa filter jenis/kata kunci, untuk pilihan jurusan
const baris = ref([])
const muat = ref(false)
const galat = ref('')

let nomor = 0
async function muatData() {
  const saya = ++nomor
  muat.value = true
  galat.value = ''
  try {
    await siapkanPeriode()
    const p = honorState.periode
    const data = await getNpDaftar({ periode: p })
    if (saya !== nomor) return // permintaan yang lebih baru sedang berjalan
    semua.value = data
    baris.value = data
  } catch (e) {
    if (saya !== nomor) return
    galat.value = e.message || 'Gagal memuat data.'
    semua.value = []
    baris.value = []
  } finally {
    if (saya === nomor) muat.value = false
  }
}
onMounted(muatData)
watch(() => honorState.periode, muatData)
const kor = useKoreksi(muatData)
const { bisaHitung, menghitung, info: infoHitung, galat: galatHitung, hitung } = useHitung(muatData)

const jurusan = computed(() => [...new Set(semua.value.map((d) => d.jurusan).filter(Boolean))].sort())
// Cari nama dosen dan pilihan jenis kegiatan (boleh banyak) dikerjakan di browser; periode diambil dari server.
const cocokJenis = (d) => !jenis.value.length || d.jenis.some((j) => jenis.value.includes(j))
const ubahJenis = (j) => { jenis.value = jenis.value.includes(j) ? jenis.value.filter((x) => x !== j) : [...jenis.value, j] }
const hasil = computed(() => baris.value.filter((d) => cocokJenis(d) && (!filterJurusan.value || d.jurusan === filterJurusan.value) && d.nama.toLowerCase().includes(cari.value.trim().toLowerCase()) && (!cek.value || d.flag?.includes(cek.value))))
const tabel = useTabel(hasil, { nama: teks((d) => d.nama), honor: angka((d) => d.honor) })
const ringkas = computed(() => ({
  dosen: hasil.value.length,
  kegiatan: hasil.value.reduce((a, d) => a + d.jumlahKegiatan, 0),
  menunggu: hasil.value.filter((d) => d.status !== 'terverifikasi').length,
}))
const tampil = (d) => ({ awal: d.jenis.slice(0, 2), sisa: Math.max(0, d.jenis.length - 2) })
</script>

<template>
  <div class="head">
    <div>
      <h1 class="title">Data Honor Dosen: Non-Pengajaran</h1>
      <p class="sub">Periode {{ honorState.periode }} · Pembimbingan dan kegiatan non-kelas</p>
    </div>
    <div class="filters">
      <label>Jurusan
        <select v-model="filterJurusan">
          <option value="">Semua jurusan</option>
          <option v-for="j in jurusan" :key="j">{{ j }}</option>
        </select>
      </label>
      <PeriodeSelect />
      <button v-if="bisaHitung" class="btn" type="button" :disabled="menghitung" @click="hitung">{{ menghitung ? 'Menghitung…' : 'Hitung ulang' }}</button>
    </div>
  </div>
  <p v-if="infoHitung" class="info" role="status">{{ infoHitung }}</p>
  <p v-if="galatHitung" class="galat" role="alert">{{ galatHitung }}</p>

  <section class="cards" aria-label="Ringkasan">
    <div class="kartu"><span>Dosen dengan kegiatan</span><strong>{{ ringkas.dosen }}</strong></div>
    <div class="kartu"><span>Total kegiatan bimbingan</span><strong>{{ ringkas.kegiatan }}</strong></div>
    <div class="kartu"><span>Menunggu verifikasi</span><strong>{{ ringkas.menunggu }}</strong></div>
  </section>

  <p v-if="cek" class="cekbar">Menampilkan data yang ditandai: <strong>{{ LABEL_CEK[cek] || cek }}</strong> <button type="button" class="btn btn--ghost btn--sm" @click="hapusCek">Tampilkan semua</button></p>

  <section class="panel">
    <div class="bar">
      <h2>Data Honor Non-Pengajaran</h2>
      <input v-model="cari" class="cari" type="search" placeholder="Cari nama dosen" aria-label="Cari nama dosen" />
      <div class="chips" role="group" aria-label="Jenis kegiatan">
        <button type="button" class="chip" :class="{ 'chip--on': !jenis.length }" :aria-pressed="!jenis.length" @click="jenis = []">Semua</button>
        <button v-for="j in JENIS_KEGIATAN" :key="j" type="button" class="chip" :class="{ 'chip--on': jenis.includes(j) }" :aria-pressed="jenis.includes(j)" @click="ubahJenis(j)">{{ j }}</button>
      </div>
    </div>
    <div class="scroll-x">
      <table class="tbl tbl--line">
        <thead>
          <tr><th>No</th><ThUrut :tabel="tabel" kunci="nama">Nama dosen</ThUrut><th>Jenis kegiatan</th><ThUrut :tabel="tabel" kunci="honor" class="r">Honor (bruto)</ThUrut><th>Simpulan asesor</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <tr v-for="(d, i) in tabel.baris.value" :key="d.id">
            <td>{{ tabel.dari.value + i }}</td>
            <td><strong class="nama">{{ d.nama }}</strong><br /><small>{{ d.jabatan }} · {{ d.jumlahKegiatan }} Kegiatan</small></td>
            <td>
              <span class="np-tag" v-for="j in tampil(d).awal" :key="j">{{ j }}</span>
              <span v-if="tampil(d).sisa" class="np-tag">+{{ tampil(d).sisa }} lainnya</span>
            </td>
            <td class="r">{{ rupiah(d.honor) }}</td>
            <td><span class="simpulan" :title="d.simpulan || ''">{{ d.simpulan || '–' }}</span></td>
            <td><StatusBadge :status="d.status" /></td>
            <td class="aksi-sel" style="white-space:nowrap"><RouterLink :to="`${base}/non-pengajaran/${d.id}`" class="btn btn--ghost btn--sm">Detail</RouterLink> <button v-if="kor.bisa.value" type="button" class="btn btn--ghost btn--sm" @click="kor.buka(d.id, d.nama)">Koreksi</button></td>
          </tr>
          <tr v-if="muat"><td colspan="7" class="kosong">Memuat…</td></tr>
          <tr v-else-if="galat"><td colspan="7" class="kosong" role="alert">{{ galat }}</td></tr>
          <tr v-else-if="!hasil.length"><td colspan="7" class="kosong">Tidak ada kegiatan yang cocok.</td></tr>
        </tbody>
      </table>
    </div>
    <Pager :tabel="tabel" />
  </section>
  <KoreksiDosenModal v-if="kor.dibuka.value" :key="kor.dibuka.value.n" :tampil="kor.tampil.value" :dosen-id="kor.dibuka.value.id" :nama="kor.dibuka.value.nama" @close="kor.tutup" @berubah="kor.setelah" />
</template>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap; margin-bottom: 28px; }
.title { font-size: 28px; font-weight: 500; margin: 14px 0 12px; }
.sub { font-size: 15px; margin: 0; }
.filters { display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; }
.filters label { display: grid; gap: 4px; font-size: 16px; }
.filters select { font: inherit; padding: 8px 12px; border: 0; border-radius: 4px; background: #e4ecea; min-width: 190px; }
.cards { display: flex; gap: 26px; flex-wrap: wrap; margin-bottom: 28px; }
.kartu { background: #ffffff; border: 1px solid #a9bcb8; border-radius: 10px; padding: 14px 16px; display: grid; gap: 6px; min-width: 230px; font-size: 15px; }
.kartu strong { font-size: 26px; font-weight: 700; }
.bar { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 18px; }
h2 { font-size: 17px; margin: 0 auto 0 0; }
.cari { font: inherit; font-size: 15px; padding: 6px 12px; border: 1px solid var(--color-primary); border-radius: 8px; min-width: 200px; background: #fff; }
.chips { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.chip { font: inherit; font-size: 15px; padding: 4px 14px; border-radius: 999px; border: 1px solid var(--color-primary); background: #fff; color: var(--color-primary); cursor: pointer; }
.chip--on { background: var(--color-primary); color: #fff; }
.chip--on::before { content: '✓ '; }
.np-tag { display: inline-block; font-size: 15px; border: 1px solid #a9bcb8; border-radius: 999px; padding: 3px 14px; margin: 2px 6px 2px 0; background: #fff; }
.nama { font-weight: 500; }
small { color: #444; font-size: 15px; }
.r { text-align: right; }
.btn--sm { padding: 4px 18px; text-decoration: none; display: inline-block; font-size: 15px; }
.kosong { text-align: center; color: #4a5755; }
.info { margin: -12px 0 16px; color: #0b5e58; }
.cekbar { background: #fff4d6; border-radius: 8px; padding: 8px 14px; margin: 0 0 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.galat { margin: -12px 0 16px; color: #b42318; }
.simpulan { max-width: 280px; font-size: 15px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
</style>
