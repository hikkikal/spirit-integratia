<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { honorState, siapkanPeriode, tandaiTerverifikasi } from '../../stores/honor.js'
import { auth } from '../../stores/auth.js'
import { getRincianDosen } from '../../services/data.js'
import { useBase } from '../../lib/useBase.js'
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
// Verifikasi dilakukan di halaman Detail Honor (satu tempat saja); halaman ini hanya menampilkan statusnya.
const kelas = computed(() => rincian.value?.kelas ?? [])
const minggu = computed(() => rincian.value?.minggu ?? [])
const detailTo = () => ({ path: `${base}/data-honor/${props.id}/detail`, query: { periode: honorState.periode } })
const pertemuanTo = (m) => ({ path: `${base}/data-honor/${props.id}/pertemuan`, query: { periode: honorState.periode, ...(m ? { minggu: m.no } : {}) } })
// Verifikasi dilakukan di halaman ini, bersama kartu total honor. Hanya Bidang Keuangan; backend memeriksa ulang.
const bisaVerifikasi = computed(() => auth.user?.role === 'keuangan')
const memverifikasi = ref(false)
async function verif() {
  memverifikasi.value = true
  galat.value = ''
  try {
    await tandaiTerverifikasi(props.id)
    await muat()
  } catch (e) {
    galat.value = e.message
  }
  memverifikasi.value = false
}
</script>

<template>
  <p v-if="!selesai" class="panel">Memuat…</p>
  <p v-else-if="!dosen" class="panel" role="alert">{{ galat || 'Dosen tidak ditemukan.' }}</p>
  <template v-else>
    <div class="top">
      <RouterLink :to="`${base}/data-honor`" class="kembali">◂ Kembali</RouterLink>
      <PeriodeSelect />
    </div>

    <RincianPeriode
      :nama="rincian.profil.nama" :jabatan="rincian.profil.jabatan" :prodi="rincian.profil.prodi" :email="rincian.profil.email"
      :total-kelas="rincian.ringkasan.totalKelas" :terlaksana="rincian.ringkasan.sksTerlaksana" :wajib="rincian.ringkasan.sksWajib" :lebih="rincian.ringkasan.sksLebih" :catatan="rincian.ringkasan.catatan"
      :minggu="minggu" :detail-to="detailTo" :pertemuan-to="pertemuanTo" :dosen-id="id" :periode="honorState.periode"
    >
      <template #total-aksi>
        <StatusBadge :status="dosen.status" />
        <button v-if="bisaVerifikasi && dosen.status !== 'terverifikasi'" class="btn" type="button" :disabled="memverifikasi" @click="verif">{{ memverifikasi ? 'Memverifikasi…' : 'Verifikasi honor' }}</button>
      </template>
    </RincianPeriode>
    <p v-if="galat" class="galat" role="alert">{{ galat }}</p>
  </template>
</template>

<style scoped>
.top { display: flex; align-items: flex-end; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
.verif { margin-left: auto; display: flex; align-items: center; gap: 12px; }
.kembali { background: #fff; color: var(--color-primary); border: 1px solid var(--color-primary); border-radius: 8px; padding: 8px 16px; font: inherit; text-decoration: none; cursor: pointer; margin-bottom: 20px; }
.kembali:hover { background: #e3efed; }
</style>
