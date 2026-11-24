<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { LABEL_CEK } from '../../lib/perhatian.js'
import { honorState, muatDaftar } from '../../stores/honor.js'
import { USE_MOCK } from '../../services/api.js'
import { useHitung } from '../../lib/useHitung.js'
import { useTabel, teks, angka } from '../../lib/useTabel.js'
import { rupiah } from '../../lib/format.js'
import { useBase } from '../../lib/useBase.js'
import { useKoreksi } from '../../lib/useKoreksi.js'
import KoreksiDosenModal from '../../components/KoreksiDosenModal.vue'
import StatusBadge from '../../components/StatusBadge.vue'
import PeriodeSelect from '../../components/PeriodeSelect.vue'
import ThUrut from '../../components/ThUrut.vue'
import Pager from '../../components/Pager.vue'

const base = useBase()
// Tautan dari dashboard: ?cek=kunci menampilkan hanya data yang ditandai.
const route = useRoute()
const router = useRouter()
const cek = computed(() => String(route.query.cek || ''))
const hapusCek = () => router.replace({ query: { ...route.query, cek: undefined } })
const cari = ref('')
// Data tiruan hanya ada untuk Teknik Informatika dan Komputer, jadi itu pilihan awal (sesuai Figma).
const filterJurusan = ref(USE_MOCK ? 'Teknik Informatika dan Komputer' : '')
const jurusan = computed(() => [...new Set(honorState.dosen.map((d) => d.jurusan).filter(Boolean))].sort().map((nama) => ({ nama })))
onMounted(muatDaftar)

const { bisaHitung, menghitung, info: infoHitung, galat: galatHitung, hitung } = useHitung(muatDaftar)
watch(() => honorState.periode, muatDaftar)
const kor = useKoreksi(muatDaftar)

const hasil = computed(() =>
  honorState.dosen.filter(
    (d) =>
      d.nama.toLowerCase().includes(cari.value.trim().toLowerCase()) &&
      (!filterJurusan.value || d.jurusan === filterJurusan.value) &&
      (!cek.value || d.flag?.includes(cek.value)),
  ),
)
// Urut hanya untuk nama, SKS lebih, dan honor. 25 baris per halaman.
const tabel = useTabel(hasil, { nama: teks((d) => d.nama), sksLebih: angka((d) => d.sksLebih), honor: angka((d) => d.honor) })
</script>

<template>
  <div class="head">
    <div>
      <h1 class="title">Data Honor Dosen</h1>
      <p class="sub">Periode {{ honorState.periode }}</p>
    </div>
    <div class="filters">
      <label>Jurusan
        <select v-model="filterJurusan">
          <option value="">Semua jurusan</option>
          <option v-for="j in jurusan" :key="j.nama">{{ j.nama }}</option>
        </select>
      </label>
      <PeriodeSelect />
      <button v-if="bisaHitung" class="btn" type="button" :disabled="menghitung" @click="hitung">{{ menghitung ? 'Menghitung…' : 'Hitung ulang' }}</button>
    </div>
  </div>
  <p v-if="infoHitung" class="info" role="status">{{ infoHitung }}</p>
  <p v-if="galatHitung" class="galat" role="alert">{{ galatHitung }}</p>

  <p v-if="cek" class="cekbar">Menampilkan data yang ditandai: <strong>{{ LABEL_CEK[cek] || cek }}</strong> <button type="button" class="btn btn--ghost btn--sm" @click="hapusCek">Tampilkan semua</button></p>

  <section class="panel">
    <div class="bar">
      <h2>Daftar dosen</h2>
      <input v-model="cari" class="cari" type="search" placeholder="Cari nama dosen" aria-label="Cari nama dosen" />
    </div>
    <div class="scroll-x">
      <table class="tbl tbl--line">
        <thead>
          <tr><th>No</th><ThUrut :tabel="tabel" kunci="nama">Nama dosen</ThUrut><th>Jabatan</th><th class="c">SKS wajib</th><ThUrut :tabel="tabel" kunci="sksLebih" class="c">SKS Lebih</ThUrut><ThUrut :tabel="tabel" kunci="honor" class="r">Honor (bruto)</ThUrut><th>Simpulan asesor</th><th>Status verifikasi</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <tr v-for="(d, i) in tabel.baris.value" :key="d.id">
            <td>{{ tabel.dari.value + i }}</td><td><strong class="nama">{{ d.nama }}</strong></td><td>{{ d.jabatan }}</td>
            <td class="c">{{ d.sksWajib }}</td><td class="c"><span class="sks-badge" :class="{ 'sks-badge--nol': !d.sksLebih }">{{ d.sksLebih }}</span></td>
            <td class="r">{{ rupiah(d.honor) }}</td>
            <td><span class="simpulan" :title="d.simpulan || ''">{{ d.simpulan || '–' }}</span><span v-if="d.catatan" class="tanda" :title="d.catatan">⚑ ada catatan</span></td>
            <td><StatusBadge :status="d.status" /></td>
            <td class="aksi-sel" style="white-space:nowrap"><RouterLink :to="`${base}/data-honor/${d.id}`" class="btn btn--ghost btn--sm">Detail</RouterLink> <button v-if="kor.bisa.value" type="button" class="btn btn--ghost btn--sm" @click="kor.buka(d.id, d.nama)">Koreksi</button></td>
          </tr>
          <tr v-if="honorState.muat"><td colspan="9" class="kosong">Memuat…</td></tr>
          <tr v-else-if="honorState.galat"><td colspan="9" class="kosong" role="alert">{{ honorState.galat }}</td></tr>
          <tr v-else-if="!hasil.length"><td colspan="9" class="kosong">Tidak ada data yang cocok.</td></tr>
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
.filters input, .filters select { font: inherit; padding: 8px 12px; border: 0; border-radius: 4px; background: #e4ecea; min-width: 190px; }
.bar { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 18px; }
h2 { font-size: 20px; margin: 0 auto 0 0; }
.cari { font: inherit; font-size: 16px; padding: 8px 14px; border: 1px solid var(--color-primary); border-radius: 8px; min-width: 260px; background: #fff; }
.c { text-align: center; }
.r { text-align: right; }
.btn--sm { padding: 4px 18px; text-decoration: none; display: inline-block; font-size: 15px; }
.tbl td { height: 50px; }
.simpulan { max-width: 280px; font-size: 15px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.kosong { text-align: center; color: #4a5755; }
.info { margin: -12px 0 16px; color: #0b5e58; }
.cekbar { background: #fff4d6; border-radius: 8px; padding: 8px 14px; margin: 0 0 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.tanda { display: block; font-size: 15px; color: #8a5a00; margin-top: 2px; cursor: help; }
.galat { margin: -12px 0 16px; color: #b42318; }
</style>
