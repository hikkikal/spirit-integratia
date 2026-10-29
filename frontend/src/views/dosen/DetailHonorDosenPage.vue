<script setup>
import { useRoute } from 'vue-router'
import DetailHonorPage from '../keuangan/DetailHonorPage.vue'
import { useBase } from '../../lib/useBase.js'

// Tampilan sama dengan Keuangan, hanya-baca untuk dosen, plus kartu Tarif Kerja Sama.
// Detail dibuka dari Rincian Kelas, jadi Kembali ke Rincian Kelas pada periode yang sama.
// TODO(backend): dosen yang dilihat harus dari sesi login (GET /api/dosen/detail?periode=),
// bukan dari id di URL. Sekarang id "B" tetap karena data tiruan.
const base = useBase()
const route = useRoute()
const periode = route.query.periode || 'Genap 2025/2026'
</script>

<template>
  <DetailHonorPage id="B" :periode="periode" tampil-kerja-sama :back-to="`${base}/rincian?periode=${encodeURIComponent(periode)}`" />
</template>
