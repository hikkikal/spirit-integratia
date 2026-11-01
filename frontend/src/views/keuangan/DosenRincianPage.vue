<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { honorState, tandaiTerverifikasi, siapkanPeriode } from '../../stores/honor.js'
import { getRincianDosen } from '../../services/data.js'
import { useBase } from '../../lib/useBase.js'
import { auth } from '../../stores/auth.js'
import RincianPeriode from '../../components/RincianPeriode.vue'
import StatusBadge from '../../components/StatusBadge.vue'
import PeriodeSelect from '../../components/PeriodeSelect.vue'

const props = defineProps({ id: String })
const base = useBase()
const rincian = ref(null)
const selesai = ref(false)
const galat = ref('')
async function muat() {
  galat.value = ''
  try {
    await siapkanPeriode()
    rincian.value = await getRincianDosen(props.id, honorState.periode)
  } catch (e) {
    rincian.value = null
    galat.value = e.message
  }
  selesai.value = true
}
onMounted(muat)
watch(() => [props.id, honorState.periode], muat)
const dosen = computed(() => rincian.value && { id: props.id, nama: rincian.value.profil.nama, status: rincian.value.ringkasan.status })
async function verif() {
  try {
    await tandaiTerverifikasi(props.id)
    await muat()
  } catch (e) {
    galat.value = e.message
  }
}
// Hanya Bidang Keuangan yang boleh memverifikasi. Backend tetap harus memeriksa ulang.
const bisaVerifikasi = computed(() => auth.user?.role === 'keuangan')
const kelas = computed(() => rincian.value?.kelas ?? [])
const minggu = computed(() => rincian.value?.minggu ?? [])
const detailTo = () => ({ path: `${base}/data-honor/${props.id}/detail`, query: { periode: honorState.periode } })
</script>

<template>
  <p v-if="!selesai" class="panel">Memuat…</p>
  <p v-else-if="!dosen" class="panel" role="alert">{{ galat || 'Dosen tidak ditemukan.' }}</p>
  <template v-else>
    <div class="top">
      <RouterLink :to="`${base}/data-honor`" class="kembali">◂ Kembali</RouterLink>
      <PeriodeSelect />
      <div class="verif">
        <StatusBadge :status="dosen.status" />
        <button v-if="bisaVerifikasi" class="btn" :disabled="dosen.status === 'terverifikasi'" @click="verif">
          {{ dosen.status === 'terverifikasi' ? 'Sudah terverifikasi' : 'Tandai terverifikasi' }}
        </button>
      </div>
    </div>

    <RincianPeriode
      :nama="rincian.profil.nama" :jabatan="rincian.profil.jabatan" :prodi="rincian.profil.prodi" :email="rincian.profil.email"
      :total-kelas="rincian.ringkasan.totalKelas" :terlaksana="rincian.ringkasan.sksTerlaksana" :wajib="rincian.ringkasan.sksWajib" :lebih="rincian.ringkasan.sksLebih"
      :minggu="minggu" :detail-to="detailTo"
    />
  </template>
</template>

<style scoped>
.top { display: flex; align-items: flex-end; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
.verif { margin-left: auto; display: flex; align-items: center; gap: 12px; }
.kembali { background: #ffee00; border: 1px solid #000; border-radius: 8px; padding: 6px 12px; color: #000; text-decoration: none; font-size: 16px; }
</style>
