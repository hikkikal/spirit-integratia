<script setup>
import { jurusan, honorTerverifikasi } from '../../data/mock.js'
import { honorState } from '../../stores/honor.js'
import { angka, rupiah, persen } from '../../lib/format.js'
import PeriodeSelect from '../../components/PeriodeSelect.vue'

const totalDosen = jurusan.reduce((a, j) => a + j.dosen, 0)
const totalSks = jurusan.reduce((a, j) => a + j.sksLebih, 0)
const totalHonor = jurusan.reduce((a, j) => a + j.honor, 0)
const verif = jurusan.reduce((a, j) => a + j.terverifikasi, 0)
const belumHonor = totalHonor - honorTerverifikasi
</script>

<template>
  <div class="head">
    <div>
      <h1 class="page-title">Dashboard<br />Laporan</h1>
      <p class="page-sub">Ringkasan honor SKS lebih · {{ honorState.periode }}</p>
    </div>
    <PeriodeSelect />
  </div>

  <section class="top3">
    <div class="panel">
      <div class="lbl">Total honor dosen terverifikasi</div>
      <div class="val val--ok">{{ rupiah(honorTerverifikasi) }}</div>
      <div>{{ verif }} dosen</div>
    </div>
    <div class="panel">
      <div class="lbl">Total honor dosen belum terverifikasi</div>
      <div class="val val--warn">{{ rupiah(belumHonor) }}</div>
      <div>{{ totalDosen - verif }} dosen</div>
    </div>
    <div class="panel">
      <div class="lbl">Progres verifikasi dosen</div>
      <div class="row"><span>Total dosen terverifikasi</span><span>{{ verif }}/{{ totalDosen }} dosen ({{ persen(verif, totalDosen) }}%)</span></div>
      <div class="bar"><span :style="{ width: persen(verif, totalDosen) + '%' }"></span></div>
    </div>
  </section>

  <section class="two">
    <div class="panel">
      <h2>Progres verifikasi per jurusan</h2>
      <div v-for="j in jurusan" :key="j.nama" class="jr">
        <div class="row"><span>{{ j.nama }}</span><span>{{ j.terverifikasi }}/{{ j.dosen }} dosen ({{ persen(j.terverifikasi, j.dosen) }}%)</span></div>
        <div class="bar"><span :style="{ width: persen(j.terverifikasi, j.dosen) + '%' }"></span></div>
      </div>
    </div>

    <div class="panel">
      <h2>Ringkasan per jurusan</h2>
      <div class="scroll-x">
        <table class="tbl tbl--line">
          <thead><tr><th>Jurusan</th><th class="num">Total dosen</th><th class="num">Total SKS lebih</th><th class="num">Total pengeluaran honor</th></tr></thead>
          <tbody>
            <tr v-for="j in jurusan" :key="j.nama">
              <td>{{ j.nama }}</td><td class="num">{{ j.dosen }}</td><td class="num">{{ j.sksLebih }}</td><td class="num">{{ rupiah(j.honor) }}</td>
            </tr>
            <tr class="total"><td>Total</td><td class="num">{{ angka(totalDosen) }}</td><td class="num">{{ angka(totalSks) }}</td><td class="num">{{ rupiah(totalHonor) }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
.top3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; margin-bottom: 28px; }
.lbl { font-size: 15px; }
.val { font-size: 34px; margin: 6px 0 10px; }
.val--ok { color: var(--color-primary); }
.val--warn { color: #d97a1c; }
.two { display: grid; grid-template-columns: 5fr 6fr; gap: 20px; align-items: start; }
h2 { font-size: 17px; margin: 0 0 20px; }
.row { display: flex; justify-content: space-between; gap: 12px; font-size: 15px; margin-top: 10px; }
.jr { margin-bottom: 14px; }
.jr .row { margin-top: 0; }
.bar { height: 14px; background: #e1e7f1; border-radius: 99px; overflow: hidden; margin-top: 3px; }
.bar span { display: block; height: 100%; background: #0e8277; border-radius: 99px; animation: growx .8s ease both; transform-origin: left; }
@keyframes growx { from { transform: scaleX(0); } to { transform: scaleX(1); } }
.total td { font-weight: 600; border-bottom: 0; }
@media (max-width: 1000px) { .top3, .two { grid-template-columns: 1fr; } }
</style>
