<script setup>
import { useRoute } from 'vue-router'
import DetailHonorPage from '../keuangan/DetailHonorPage.vue'
import { useBase } from '../../lib/useBase.js'

// Tampilan sama dengan Keuangan, hanya-baca untuk dosen, plus kartu Tarif Kerja Sama.
// Detail dibuka dari Rincian Kelas, jadi Kembali ke Rincian Kelas pada periode yang sama.
// Dosen yang dilihat selalu dari sesi login (GET /api/dosen/honor), bukan dari id di URL.
const base = useBase()
const route = useRoute()
const periode = route.query.periode || ''
</script>

<template>
  <DetailHonorPage milik-sendiri :periode="periode" tampil-kerja-sama :back-to="`${base}/rincian?periode=${encodeURIComponent(periode)}`" />
</template>
