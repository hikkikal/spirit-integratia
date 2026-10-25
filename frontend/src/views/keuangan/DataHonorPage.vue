<script setup>
import { computed, ref } from 'vue'
import { honorState } from '../../stores/honor.js'
import { jurusan } from '../../data/mock.js'
import { rupiah } from '../../lib/format.js'
import { useBase } from '../../lib/useBase.js'
import StatusBadge from '../../components/StatusBadge.vue'

const base = useBase()
const cari = ref('')
const filterJurusan = ref('')
const filterStatus = ref('')

const hasil = computed(() =>
  honorState.dosen.filter(
    (d) =>
      d.nama.toLowerCase().includes(cari.value.trim().toLowerCase()) &&
      (!filterJurusan.value || d.jurusan === filterJurusan.value) &&
      (!filterStatus.value || d.status === filterStatus.value),
  ),
)
</script>

<template>
  <h1 class="page-title">Data Honor Dosen</h1>
  <p class="page-sub">{{ honorState.periode }}</p>

  <div class="filters">
    <input v-model="cari" class="input" type="search" placeholder="Cari nama dosen" aria-label="Cari nama dosen" />
    <select v-model="filterJurusan" class="input" aria-label="Jurusan">
      <option value="">Semua jurusan</option>
      <option v-for="j in jurusan" :key="j.nama">{{ j.nama }}</option>
    </select>
    <select v-model="filterStatus" class="input" aria-label="Status">
      <option value="">Semua status</option>
      <option value="terverifikasi">Terverifikasi</option>
      <option value="menunggu">Menunggu</option>
    </select>
  </div>

  <div class="card scroll-x">
    <table class="tbl">
      <thead>
        <tr><th>Nama</th><th>Jurusan</th><th class="num">SKS wajib</th><th class="num">SKS lebih</th><th class="num">Honor</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <tr v-for="d in hasil" :key="d.id">
          <td>{{ d.nama }}<div class="sub">{{ d.jabatan }}</div></td>
          <td>{{ d.jurusan }}</td>
          <td class="num">{{ d.sksWajib }}</td>
          <td class="num">{{ d.sksLebih }}</td>
          <td class="num">{{ rupiah(d.honor) }}</td>
          <td><StatusBadge :status="d.status" /></td>
          <td><RouterLink :to="`${base}/data-honor/${d.id}`">Detail</RouterLink></td>
        </tr>
        <tr v-if="!hasil.length"><td colspan="7" class="kosong">Tidak ada data yang cocok.</td></tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.filters { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
.filters input { flex: 1; min-width: 220px; }
.sub { font-size: 13px; color: #77776f; }
.kosong { text-align: center; color: #77776f; }
a { color: var(--color-primary); font-weight: 600; }
</style>
