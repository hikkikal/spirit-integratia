<script setup>
import { computed } from 'vue'
import { honorState, tandaiTerverifikasi } from '../../stores/honor.js'
import { kelasPeriode, mingguPeriode } from '../../data/dosen.js'
import { useBase } from '../../lib/useBase.js'
import { auth } from '../../stores/auth.js'
import RincianPeriode from '../../components/RincianPeriode.vue'
import StatusBadge from '../../components/StatusBadge.vue'
import PeriodeSelect from '../../components/PeriodeSelect.vue'

const props = defineProps({ id: String })
const base = useBase()
const dosen = computed(() => honorState.dosen.find((d) => d.id === props.id))
// Hanya Bidang Keuangan yang boleh memverifikasi. Backend tetap harus memeriksa ulang.
const bisaVerifikasi = computed(() => auth.user?.role === 'keuangan')
// Rincian per minggu baru tersedia untuk Dosen B (data tiruan).
const punyaRincian = computed(() => props.id === 'B')
const kelas = computed(() => (punyaRincian.value ? kelasPeriode[honorState.periode] ?? [] : []))
const minggu = computed(() => (punyaRincian.value ? mingguPeriode[honorState.periode] ?? [] : []))
const detailTo = () => ({ path: `${base}/data-honor/${props.id}/detail`, query: { periode: honorState.periode } })
</script>

<template>
  <p v-if="!dosen" class="panel">Dosen tidak ditemukan.</p>
  <template v-else>
    <div class="top">
      <RouterLink :to="`${base}/data-honor`" class="kembali">◂ Kembali</RouterLink>
      <PeriodeSelect />
      <div class="verif">
        <StatusBadge :status="dosen.status" />
        <button v-if="bisaVerifikasi" class="btn" :disabled="dosen.status === 'terverifikasi'" @click="tandaiTerverifikasi(dosen.id)">
          {{ dosen.status === 'terverifikasi' ? 'Sudah terverifikasi' : 'Tandai terverifikasi' }}
        </button>
      </div>
    </div>

    <RincianPeriode
      :nama="dosen.nama" :jabatan="`${dosen.jabatan} (${dosen.golongan})`" :prodi="`D4 Teknik Informatika`" :email="`${dosen.nama.toLowerCase().replace(' ', '')}@pnj.ac.id`"
      :total-kelas="punyaRincian ? kelas.length : '-'" :terlaksana="dosen.sksWajib + dosen.sksLebih" :wajib="dosen.sksWajib" :lebih="dosen.sksLebih"
      :minggu="minggu" :detail-to="detailTo"
    />
  </template>
</template>

<style scoped>
.top { display: flex; align-items: flex-end; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
.verif { margin-left: auto; display: flex; align-items: center; gap: 12px; }
.kembali { background: #ffee00; border: 1px solid #000; border-radius: 8px; padding: 6px 12px; color: #000; text-decoration: none; font-size: 16px; }
</style>
