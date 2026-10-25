<script setup>
import { computed, ref } from 'vue'
import { riwayat, TARIF_REGULER } from '../../data/dosen.js'
import { hitungHonor } from '../../lib/honor.js'
import { rupiah } from '../../lib/format.js'
import { useBase } from '../../lib/useBase.js'

const base = useBase()
const baris = riwayat.map((r) => ({ ...r, ...hitungHonor(r.lebih, TARIF_REGULER) }))
const totalNetto = baris.reduce((a, b) => a + b.netto, 0)
const totalLebih = baris.reduce((a, b) => a + b.lebih, 0)
const terakhir = baris[0]

// Grafik batang: urutan lama ke baru, dengan periode terbaru disorot.
const grafik = [...baris].reverse()
const maks = Math.max(...grafik.map((b) => b.bruto))
const singkat = (p) => p.replace(/(\d{2})(\d{2})\/(\d{2})(\d{2})/, '$2/$4')
const aktif = ref(null)
const tinggi = (b) => Math.round((b.bruto / maks) * 100)
</script>

<template>
  <h1 class="page-title">Riwayat Honor</h1>
  <p class="page-sub">Ringkasan honor SKS lebih Anda dari periode ke periode</p>

  <section class="grid cards">
    <div class="stat"><div class="stat__lbl">Total honor</div><div class="stat__val">{{ rupiah(totalNetto) }}</div><div class="stat__sub">{{ baris.length }} periode · netto</div></div>
    <div class="stat"><div class="stat__lbl">Total SKS lebih</div><div class="stat__val">{{ totalLebih }}</div><div class="stat__sub">{{ baris.at(-1).periode }} – {{ terakhir.periode }}</div></div>
    <div class="stat"><div class="stat__lbl">Honor terakhir</div><div class="stat__val">{{ rupiah(terakhir.netto) }}</div><div class="stat__sub">{{ terakhir.periode }} · netto</div></div>
  </section>

  <section class="panel chart" aria-label="Grafik honor bruto per periode">
    <h2>Honor bruto per periode</h2>
    <div class="bars">
      <div v-for="b in grafik" :key="b.periode" class="col" @mouseenter="aktif = b.periode" @mouseleave="aktif = null">
        <span class="amt">{{ rupiah(b.bruto) }}</span>
        <span class="bar" :class="{ 'bar--last': b.periode === terakhir.periode }" :style="{ height: tinggi(b) + '%' }"></span>
        <span class="lbl">{{ b.periode.startsWith('Genap') ? 'Genap' : 'Ganjil' }} {{ singkat(b.periode.split(' ')[1]) }}</span>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="scroll-x">
      <table class="tbl tbl--line">
        <thead>
          <tr><th>Periode</th><th>SKS wajib</th><th>SKS terlaksana</th><th>SKS lebih</th><th>Honor bruto</th><th>Pajak</th><th>Honor netto</th><th>Detail</th></tr>
        </thead>
        <tbody>
          <tr v-for="b in baris" :key="b.periode">
            <td>{{ b.periode }}</td><td>{{ b.sksWajib }}</td><td>{{ b.terlaksana }}</td><td>{{ b.lebih }}</td>
            <td>{{ rupiah(b.bruto) }}</td><td>{{ rupiah(b.pajak) }}</td><td><strong>{{ rupiah(b.netto) }}</strong></td>
            <td><RouterLink :to="`${base}/detail`" class="btn btn--ghost sm">Lihat Detail</RouterLink></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.cards { grid-template-columns: repeat(3, 1fr); margin-bottom: 16px; }
.chart { margin-bottom: 16px; }
.bars { height: 260px; display: flex; align-items: flex-end; justify-content: center; gap: 28px; border-bottom: 1px solid #000; padding-top: 28px; }
.col { height: 100%; width: 90px; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; position: relative; }
.bar { width: 100%; background: #67d1c9; border-radius: 6px 6px 0 0; display: block; }
.bar--last { background: #0d9488; }
.amt { font-size: 13px; margin-bottom: 4px; white-space: nowrap; }
.lbl { position: absolute; top: calc(100% + 8px); font-size: 13px; white-space: nowrap; }
.chart { padding-bottom: 44px; }
@media (max-width: 900px) { .cards { grid-template-columns: 1fr; } .bars { gap: 10px; } .col { width: 60px; } }
.sm { padding: 6px 14px; white-space: nowrap; text-decoration: none; display: inline-block; }
</style>
