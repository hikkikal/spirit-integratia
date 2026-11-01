<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { getProfilSaya, getRiwayat, getKelasSaya } from '../../services/data.js'
import { useBase } from '../../lib/useBase.js'
import RincianPeriode from '../../components/RincianPeriode.vue'

const route = useRoute()
const profil = ref(null)
const riwayat = ref([])
const periode = ref('')
const kelas = ref([])
const minggu = ref([])
const galat = ref('')
const selesai = ref(false)
const data = computed(() => riwayat.value.find((r) => r.periode === periode.value))
async function muatKelas() {
  if (!periode.value) return
  try {
    const d = await getKelasSaya(periode.value)
    kelas.value = d.kelas
    minggu.value = d.minggu
  } catch (e) { galat.value = e.message }
}
onMounted(async () => {
  try {
    ;[profil.value, riwayat.value] = await Promise.all([getProfilSaya(), getRiwayat()])
    const dariQuery = riwayat.value.find((r) => r.periode === route.query.periode)
    periode.value = dariQuery ? dariQuery.periode : riwayat.value[0]?.periode ?? ''
    await muatKelas()
  } catch (e) {
    galat.value = e.message
  }
  selesai.value = true
})
watch(periode, muatKelas)
const base = useBase()
// Detail dibuka dari halaman ini, jadi tombol Kembali di sana kembali ke sini (periode tetap).
const detailTo = () => ({ path: `${base}/detail`, query: { periode: periode.value } })
</script>

<template>
  <p v-if="!selesai">Memuat…</p>
  <p v-else-if="!data" role="alert">{{ galat || 'Belum ada data honor untuk akun ini.' }}</p>
  <template v-else>
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
</template>

<style scoped>
.sel { margin-bottom: 16px; }
</style>
