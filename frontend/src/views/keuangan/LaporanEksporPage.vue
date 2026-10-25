<script setup>
import { computed } from 'vue'
import { jurusan } from '../../data/mock.js'
import { honorState, catatEkspor, kunciPeriode } from '../../stores/honor.js'
import { auth } from '../../stores/auth.js'
import { rupiah, persen } from '../../lib/format.js'

const terkunci = computed(() => !!honorState.terkunci[honorState.periode])
const totalDosen = jurusan.reduce((a, j) => a + j.dosen, 0)
const verif = jurusan.reduce((a, j) => a + j.terverifikasi, 0)
const lengkap = computed(() => verif === totalDosen)

// TODO(backend): unduh file Excel/PDF sungguhan dari API. Sekarang hanya mencatat riwayat.
function ekspor(format) {
  catatEkspor(format, auth.user?.name ?? 'Keuangan')
}
</script>

<template>
  <h1 class="page-title">Laporan & Ekspor</h1>
  <p class="page-sub">{{ honorState.periode }}</p>

  <section class="card block">
    <h2>Ringkasan per jurusan</h2>
    <div class="scroll-x">
      <table class="tbl">
        <thead><tr><th>Jurusan</th><th class="num">Dosen</th><th class="num">Terverifikasi</th><th class="num">Honor</th></tr></thead>
        <tbody>
          <tr v-for="j in jurusan" :key="j.nama">
            <td>{{ j.nama }}</td><td class="num">{{ j.dosen }}</td>
            <td class="num">{{ persen(j.terverifikasi, j.dosen) }}%</td><td class="num">{{ rupiah(j.honor) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card block">
    <h2>Ekspor laporan</h2>
    <p v-if="!lengkap" class="warn">Masih {{ totalDosen - verif }} dosen belum terverifikasi. Ekspor tetap bisa dibuat sebagai draf.</p>
    <div class="row">
      <button class="btn" @click="ekspor('Excel')">Ekspor Excel</button>
      <button class="btn btn--ghost" @click="ekspor('PDF')">Ekspor PDF</button>
      <button class="btn btn--ghost" :disabled="terkunci || !lengkap" @click="kunciPeriode(honorState.periode)">
        {{ terkunci ? 'Periode terkunci' : 'Kunci periode' }}
      </button>
    </div>
  </section>

  <section class="card block">
    <h2>Riwayat ekspor</h2>
    <ul><li v-for="(r, i) in honorState.riwayat" :key="i">{{ r }}</li></ul>
  </section>
</template>

<style scoped>
.block { margin-bottom: 16px; }
h2 { font-size: 20px; margin: 0 0 12px; }
.row { display: flex; gap: 12px; flex-wrap: wrap; }
.warn { color: #8a5a00; margin-top: 0; }
ul { margin: 0; padding-left: 20px; line-height: 1.8; }
</style>
