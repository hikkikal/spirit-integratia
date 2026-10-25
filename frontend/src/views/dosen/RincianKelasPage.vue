<script setup>
import { computed, ref } from 'vue'
import { profil, riwayat, kelasPeriode, mingguPeriode } from '../../data/dosen.js'
import { rupiah } from '../../lib/format.js'
import { useBase } from '../../lib/useBase.js'

const periode = ref(riwayat[0].periode)
const data = computed(() => riwayat.find((r) => r.periode === periode.value))
const kelas = computed(() => kelasPeriode[periode.value] ?? [])
const minggu = computed(() => mingguPeriode[periode.value] ?? [])
const base = useBase()
</script>

<template>
  <h1 class="page-title">Halo, {{ profil.nama }}</h1>
  <p class="page-sub">{{ profil.jabatan }} · {{ periode }} · Sumber: LKD versi 1</p>

  <label class="field-sel sel">
    <span>Periode</span>
    <select v-model="periode"><option v-for="r in riwayat" :key="r.periode">{{ r.periode }}</option></select>
  </label>

  <section class="panel prof">
    <h2>{{ profil.nama }}</h2>
    <p>{{ profil.jabatan }} · {{ profil.prodi }}</p>
    <p>{{ profil.email }}</p>
  </section>

  <section class="grid cards">
    <div class="stat"><div class="stat__lbl">Total kelas</div><div class="stat__val">{{ kelas.length }}</div><div class="stat__sub">{{ kelas.length }} mata kuliah</div></div>
    <div class="stat"><div class="stat__lbl">SKS terlaksana</div><div class="stat__val">{{ data.terlaksana }}</div></div>
    <div class="stat"><div class="stat__lbl">SKS wajib</div><div class="stat__val">{{ data.sksWajib }}</div><div class="stat__sub">{{ profil.jabatan }}</div></div>
    <div class="stat"><div class="stat__lbl">SKS lebih</div><div class="stat__val">{{ data.lebih }}</div><div class="stat__sub">{{ data.terlaksana }}-{{ data.sksWajib }}</div></div>
  </section>

  <section class="panel">
    <p v-if="!minggu.length" class="kosong">Rincian per minggu periode ini belum tersedia.</p>
    <div v-else class="scroll-x">
      <table class="tbl tbl--line">
        <thead>
          <tr><th>Minggu</th><th>Total SKS</th><th>WM</th><th>Tarif Perdir</th><th>Rincian Bruto Perdir</th><th>Pajak</th><th>Netto</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <tr v-for="m in minggu" :key="m.label">
            <td>{{ m.label }}<br />Perkuliahan</td>
            <td>{{ m.totalSks }}</td><td>{{ m.wm }}</td>
            <td><div v-for="t in m.tarif" :key="t">{{ t }}</div></td>
            <td>
              <div v-for="r in m.rincian" :key="r">{{ r }}</div>
              <div>Total: {{ rupiah(m.total) }}</div>
            </td>
            <td>{{ rupiah(m.pajak) }}</td><td>{{ rupiah(m.netto) }}</td>
            <td><RouterLink :to="`${base}/detail`" class="btn btn--ghost sm">Lihat Detail</RouterLink></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.sel { margin-bottom: 16px; }
.prof { margin-bottom: 16px; }
.prof h2 { font-size: 30px; margin: 0; }
.prof p { margin: 4px 0; }
.cards { grid-template-columns: repeat(4, 1fr); margin-bottom: 16px; }
.sm { padding: 6px 14px; white-space: nowrap; text-decoration: none; display: inline-block; }
.tbl td { vertical-align: middle; font-size: 14px; }
.kosong { color: #77776f; }
@media (max-width: 900px) { .cards { grid-template-columns: repeat(2, 1fr); } }
</style>
