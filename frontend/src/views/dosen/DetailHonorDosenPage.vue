<script setup>
import DetailHonorPage from '../keuangan/DetailHonorPage.vue'
import { useBase } from '../../lib/useBase.js'

// Halaman detail memakai tampilan yang sama dengan Keuangan, hanya-baca untuk dosen.
// TODO(backend): dosen yang dilihat harus dari sesi login (GET /api/dosen/detail?periode=),
// bukan dari id di URL. Sekarang id "B" tetap karena data tiruan.
const base = useBase()
</script>

<template>
  <DetailHonorPage id="B" :back-to="`${base}/riwayat`" />
</template>
