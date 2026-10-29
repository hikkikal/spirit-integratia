<script setup>
import { computed, ref } from 'vue'
import { logList } from '../../data/pengguna.js'

const cari = ref('')
const periode = ref('7')
const status = ref('')

const tampil = computed(() => {
  const q = cari.value.trim().toLowerCase()
  return logList.filter((l) => (!q || l.nama.toLowerCase().includes(q)) && (!status.value || l.status === status.value))
})
</script>

<template>
  <h1 class="page-title">Riwayat Aktivitas</h1>
  <p class="page-sub">Jejak audit seluruh aktivitas penting di sistem</p>

  <div class="filters">
    <label>Cari<input v-model="cari" type="search" placeholder="Nama / NIP" /></label>
    <label>Periode
      <select v-model="periode"><option value="7">7 Hari Terakhir</option><option value="30">30 Hari Terakhir</option><option value="0">Semua</option></select>
    </label>
    <label>Status
      <select v-model="status"><option value="">Semua</option><option>Berhasil</option><option>Gagal</option></select>
    </label>
  </div>

  <section class="panel">
    <h2>Daftar Aktivitas</h2>
    <div class="scroll-x">
      <table class="tbl tbl--line">
        <thead><tr><th>Waktu</th><th>Nama</th><th>Aksi</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-for="(l, i) in tampil" :key="i">
            <td>{{ l.waktu }}</td>
            <td>{{ l.nama }}<div class="sub">{{ l.role }}</div></td>
            <td><span class="tag tag--gray">{{ l.aksi }}</span></td>
            <td><span :class="l.status === 'Berhasil' ? 'badge-ok' : 'tag tag--bad'">{{ l.status }}</span></td>
          </tr>
          <tr v-if="!tampil.length"><td colspan="4">Belum ada aktivitas.</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
h2 { font-size: 18px; margin: 0 0 8px; }
.sub { font-size: 13px; color: #55554f; }
</style>
