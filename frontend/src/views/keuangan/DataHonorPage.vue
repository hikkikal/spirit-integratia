<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { honorState, muatDaftar } from '../../stores/honor.js'
import { USE_MOCK } from '../../services/api.js'
import { rupiah } from '../../lib/format.js'
import { useBase } from '../../lib/useBase.js'
import StatusBadge from '../../components/StatusBadge.vue'
import PeriodeSelect from '../../components/PeriodeSelect.vue'

const base = useBase()
const cari = ref('')
// Data tiruan hanya ada untuk Teknik Informatika dan Komputer, jadi itu pilihan awal (sesuai Figma).
const filterJurusan = ref(USE_MOCK ? 'Teknik Informatika dan Komputer' : '')
const jurusan = computed(() => [...new Set(honorState.dosen.map((d) => d.jurusan).filter(Boolean))].sort().map((nama) => ({ nama })))
onMounted(muatDaftar)
watch(() => honorState.periode, muatDaftar)

const hasil = computed(() =>
  honorState.dosen.filter(
    (d) =>
      d.nama.toLowerCase().includes(cari.value.trim().toLowerCase()) &&
      (!filterJurusan.value || d.jurusan === filterJurusan.value),
  ),
)
</script>

<template>
  <div class="head">
    <div>
      <h1 class="title">Data Honor Dosen</h1>
      <p class="sub">Periode {{ honorState.periode }}</p>
    </div>
    <div class="filters">
      <label>Cari Dosen<input v-model="cari" type="search" placeholder="Nama Dosen" /></label>
      <label>Jurusan
        <select v-model="filterJurusan">
          <option value="">Semua jurusan</option>
          <option v-for="j in jurusan" :key="j.nama">{{ j.nama }}</option>
        </select>
      </label>
      <PeriodeSelect />
    </div>
  </div>

  <section class="panel">
    <h2>Daftar dosen</h2>
    <div class="scroll-x">
      <table class="tbl tbl--line">
        <thead>
          <tr><th>No</th><th>Nama dosen</th><th>Jabatan</th><th class="c">SKS wajib</th><th class="c">SKS Lebih</th><th class="r">Honor (bruto)</th><th>Status verifikasi</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <tr v-for="(d, i) in hasil" :key="d.id">
            <td>{{ i + 1 }}</td><td>{{ d.nama }}</td><td>{{ d.jabatan }}</td>
            <td class="c">{{ d.sksWajib }}</td><td class="c">{{ d.sksLebih }}</td>
            <td class="r">{{ rupiah(d.honor) }}</td>
            <td><StatusBadge :status="d.status" /></td>
            <td><RouterLink :to="`${base}/data-honor/${d.id}`" class="btn btn--ghost btn--sm">Detail</RouterLink></td>
          </tr>
          <tr v-if="honorState.muat"><td colspan="8" class="kosong">Memuat…</td></tr>
          <tr v-else-if="honorState.galat"><td colspan="8" class="kosong" role="alert">{{ honorState.galat }}</td></tr>
          <tr v-else-if="!hasil.length"><td colspan="8" class="kosong">Tidak ada data yang cocok.</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap; margin-bottom: 28px; }
.title { font-size: 28px; font-weight: 500; margin: 14px 0 12px; }
.sub { font-size: 14px; margin: 0; }
.filters { display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; }
.filters label { display: grid; gap: 4px; font-size: 16px; }
.filters input, .filters select { font: inherit; padding: 8px 12px; border: 0; border-radius: 4px; background: #d9d9d9; min-width: 190px; }
h2 { font-size: 17px; margin: 0 0 22px; }
.c { text-align: center; }
.r { text-align: right; }
.btn--sm { padding: 4px 18px; text-decoration: none; display: inline-block; font-size: 14px; }
.tbl td { height: 50px; }
.kosong { text-align: center; color: #77776f; }
</style>
