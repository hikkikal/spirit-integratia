<script setup>
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { profil, riwayat, kelasPeriode, mingguPeriode } from '../../data/dosen.js'
import { useBase } from '../../lib/useBase.js'
import RincianPeriode from '../../components/RincianPeriode.vue'

const route = useRoute()
const dariQuery = riwayat.find((r) => r.periode === route.query.periode)
const periode = ref(dariQuery ? dariQuery.periode : riwayat[0].periode)
const data = computed(() => riwayat.find((r) => r.periode === periode.value))
const kelas = computed(() => kelasPeriode[periode.value] ?? [])
const minggu = computed(() => mingguPeriode[periode.value] ?? [])
const base = useBase()
// Detail dibuka dari halaman ini, jadi tombol Kembali di sana kembali ke sini (periode tetap).
const detailTo = () => ({ path: `${base}/detail`, query: { periode: periode.value } })
</script>

<template>
  <h1 class="page-title">Halo, {{ profil.nama }}</h1>
  <p class="page-sub">{{ profil.jabatan }} · {{ periode }}</p>

  <label class="field-sel sel">
    <span>Periode</span>
    <select v-model="periode"><option v-for="r in riwayat" :key="r.periode">{{ r.periode }}</option></select>
  </label>

  <RincianPeriode
    :nama="profil.nama" :jabatan="profil.jabatan" :prodi="profil.prodi" :email="profil.email"
    :total-kelas="kelas.length" :terlaksana="data.terlaksana" :wajib="data.sksWajib" :lebih="data.lebih"
    :minggu="minggu" :detail-to="detailTo"
  />
</template>

<style scoped>
.sel { margin-bottom: 16px; }
</style>
