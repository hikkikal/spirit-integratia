<script setup>
import { jurusan, honorTerverifikasi } from '../../data/mock.js'
import { honorState } from '../../stores/honor.js'
import { useBase } from '../../lib/useBase.js'
import { angka, rupiah, persen } from '../../lib/format.js'

const base = useBase()
const totalDosen = jurusan.reduce((a, j) => a + j.dosen, 0)
const totalSks = jurusan.reduce((a, j) => a + j.sksLebih, 0)
const totalHonor = jurusan.reduce((a, j) => a + j.honor, 0)
// Demo: angka dashboard memakai ringkasan jurusan (180 dosen), bukan 8 dosen contoh.
const verifJurusan = jurusan.reduce((a, j) => a + j.terverifikasi, 0)
const belumHonor = totalHonor - honorTerverifikasi
</script>

<template>
  <h1 class="page-title">Dashboard</h1>
  <p class="page-sub">Ringkasan honor SKS lebih · {{ honorState.periode }}</p>

  <section class="grid stats">
    <div class="card"><div class="lbl">Total dosen</div><div class="val">{{ angka(totalDosen) }}</div></div>
    <div class="card"><div class="lbl">SKS lebih</div><div class="val">{{ angka(totalSks) }}</div></div>
    <div class="card"><div class="lbl">Total honor</div><div class="val">{{ rupiah(totalHonor) }}</div></div>
    <div class="card"><div class="lbl">Dosen terverifikasi</div><div class="val">{{ verifJurusan }} / {{ totalDosen }}</div></div>
  </section>

  <section class="card prog">
    <h2>Progres verifikasi</h2>
    <div class="bar"><span :style="{ width: persen(verifJurusan, totalDosen) + '%' }"></span></div>
    <p>{{ persen(verifJurusan, totalDosen) }}% terverifikasi · {{ totalDosen - verifJurusan }} dosen belum terverifikasi</p>
    <p>Honor terverifikasi {{ rupiah(honorTerverifikasi) }} · belum terverifikasi {{ rupiah(belumHonor) }}</p>
    <RouterLink :to="`${base}/data-honor`" class="btn">Lihat data honor</RouterLink>
  </section>

  <section class="card jur">
    <h2>Per jurusan</h2>
    <div class="scroll-x">
      <table class="tbl">
        <thead><tr><th>Jurusan</th><th class="num">Dosen</th><th class="num">Terverifikasi</th><th class="num">SKS lebih</th><th class="num">Honor</th></tr></thead>
        <tbody>
          <tr v-for="j in jurusan" :key="j.nama">
            <td>{{ j.nama }}</td><td class="num">{{ j.dosen }}</td>
            <td class="num">{{ j.terverifikasi }} ({{ persen(j.terverifikasi, j.dosen) }}%)</td>
            <td class="num">{{ j.sksLebih }}</td><td class="num">{{ rupiah(j.honor) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.stats { grid-template-columns: repeat(4, 1fr); margin-bottom: 16px; }
.lbl { color: #55554f; }
.val { font-size: 30px; font-weight: 600; margin-top: 6px; color: var(--color-primary-dark); }
.prog, .jur { margin-bottom: 16px; }
h2 { font-size: 20px; margin: 0 0 12px; }
.bar { height: 14px; background: #e3e2da; border-radius: 99px; overflow: hidden; margin-bottom: 10px; }
.bar span { display: block; height: 100%; background: var(--color-primary); }
.prog p { margin: 4px 0; }
.prog .btn { display: inline-block; margin-top: 12px; text-decoration: none; }
@media (max-width: 900px) { .stats { grid-template-columns: repeat(2, 1fr); } }
</style>
