<script setup>
import { computed, ref } from 'vue'
import { profil, riwayat, kelasPeriode, TARIF_REGULER } from '../../data/dosen.js'
import { hitungHonor } from '../../lib/honor.js'
import { rupiah } from '../../lib/format.js'

const periode = ref(riwayat[0].periode)
const data = computed(() => riwayat.find((r) => r.periode === periode.value))
const hon = computed(() => hitungHonor(data.value.lebih, TARIF_REGULER))
const kelas = computed(() => kelasPeriode[periode.value] ?? [])
const sksKelas = computed(() => kelas.value.reduce((a, k) => a + k.sks, 0))
</script>

<template>
  <h1 class="page-title">Halo, {{ profil.nama }}</h1>
  <p class="page-sub">{{ profil.jabatan }} · {{ periode }} · Sumber: LKD versi 1</p>

  <div class="bar">
    <label class="field-sel">
      <span>Periode</span>
      <select v-model="periode">
        <option v-for="r in riwayat" :key="r.periode">{{ r.periode }}</option>
      </select>
    </label>
    <span v-if="data.status === 'terverifikasi'" class="badge-ok">Terverifikasi Keuangan</span>
  </div>

  <section class="grid cards">
    <div class="stat"><div class="stat__lbl">SKS wajib</div><div class="stat__val">{{ data.sksWajib }}</div></div>
    <div class="stat"><div class="stat__lbl">SKS terlaksana</div><div class="stat__val">{{ data.terlaksana }}</div></div>
    <div class="stat">
      <div class="stat__lbl">SKS lebih</div><div class="stat__val">{{ data.lebih }}</div>
      <div class="stat__sub">{{ data.terlaksana }} − {{ data.sksWajib }}</div>
    </div>
    <div class="stat">
      <div class="stat__lbl">Honor bruto</div><div class="stat__val">{{ rupiah(hon.bruto) }}</div>
      <div class="stat__sub">Tarif reguler {{ rupiah(TARIF_REGULER) }}/SKS</div>
    </div>
  </section>

  <section class="panel block">
    <h2>Perhitungan honor</h2>
    <div class="scroll-x">
      <table class="tbl tbl--line">
        <thead><tr><th>Komponen</th><th>SKS</th><th>Tarif</th><th>Honor</th></tr></thead>
        <tbody>
          <tr><td>{{ hon.sks100 }} SKS pertama (100%)</td><td>{{ hon.sks100 }}</td><td>{{ rupiah(TARIF_REGULER) }}</td><td>{{ rupiah(hon.sks100 * TARIF_REGULER) }}</td></tr>
          <tr><td>{{ hon.sks50 }} SKS berikutnya (50%)</td><td>{{ hon.sks50 }}</td><td>{{ rupiah(TARIF_REGULER / 2) }}</td><td>{{ rupiah(hon.sks50 * TARIF_REGULER / 2) }}</td></tr>
          <tr><td>{{ hon.sks0 }} SKS sisa (tidak dibayar)</td><td>{{ hon.sks0 }}</td><td>–</td><td>Rp0</td></tr>
          <tr class="tot"><td>Total</td><td>{{ data.lebih }}</td><td></td><td>{{ rupiah(hon.bruto) }}</td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel block">
    <h2>Rincian kelas ({{ sksKelas || data.terlaksana }} SKS)</h2>
    <p v-if="!kelas.length" class="kosong">Rincian kelas periode ini belum tersedia.</p>
    <div v-else class="scroll-x">
      <table class="tbl tbl--line">
        <thead><tr><th>Mata kuliah</th><th>Kelas</th><th>Prodi</th><th>SKS</th><th>Sumber di LKD</th></tr></thead>
        <tbody>
          <tr v-for="k in kelas" :key="k.mk">
            <td>{{ k.mk }}</td><td>{{ k.kelas }}</td><td>{{ k.prodi }}</td><td>{{ k.sks }}</td><td>{{ k.sumber }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.bar { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
.cards { grid-template-columns: repeat(4, 1fr); margin-bottom: 16px; }
.block { margin-bottom: 16px; }
.tot td { font-weight: 700; }
.kosong { color: #77776f; }
@media (max-width: 900px) { .cards { grid-template-columns: repeat(2, 1fr); } }
</style>
