<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { honorState, siapkanPeriode } from '../../stores/honor.js'
import { getAcademia } from '../../services/academia.js'
import { tanggalPanjang } from '../../lib/format.js'
import { useBase } from '../../lib/useBase.js'

// Pertemuan dan pencocokan kelas dengan SISTER, untuk satu minggu (atau semua kelas bila minggu tidak dipilih).
// id kosong = milik dosen yang sedang login. Alamat: .../pertemuan?periode=...&minggu=N
const props = defineProps({ id: String, periode: String, minggu: String })
const router = useRouter()
const base = useBase()
const data = ref(null)
const selesai = ref(false)
const galat = ref('')
const periodeAktif = computed(() => props.periode || honorState.periode)

async function muat() {
  galat.value = ''
  try {
    if (!props.periode) await siapkanPeriode()
    data.value = await getAcademia(props.id || null, periodeAktif.value)
  } catch (e) {
    data.value = null
    galat.value = e.message
  }
  selesai.value = true
}
onMounted(muat)
watch(() => [props.id, periodeAktif.value], muat)

const mg = computed(() => (props.minggu ? (data.value?.minggu ?? []).find((m) => String(m.no) === String(props.minggu)) : null))
const norm = (x) => String(x ?? '').toUpperCase().replace(/[^A-Z0-9]/g, '')
const kunci = (mk, kelas) => `${norm(mk)}|${norm(kelas)}`
const baris = computed(() => {
  const semua = data.value?.banding ?? []
  if (!mg.value) return semua
  const set = new Set(mg.value.kelas.map((k) => kunci(k.mk, k.kelas)))
  return semua.filter((b) => set.has(kunci(b.mk, b.kelas)))
})
const rentang = (m) => (m?.dari ? (m.dari === m.sampai ? tanggalPanjang(m.dari) : `${tanggalPanjang(m.dari)} – ${tanggalPanjang(m.sampai)}`) : '')
const LABEL = { cocok: 'Cocok', selisih: 'Berbeda', hanya_academia: 'Hanya di data akademik', hanya_sister: 'Hanya di SISTER' }
const nilai = (v) => (v === null || v === undefined ? '–' : v)
const lebihAkd = (b, i) => b.lebih?.academia?.[i] ?? null
const lebihKita = (b, i) => b.lebih?.kita?.[i] ?? null
const lebihBeda = (b, i) => lebihAkd(b, i) !== null && lebihKita(b, i) !== null && Math.abs(lebihAkd(b, i) - lebihKita(b, i)) > 0.005
const kembaliKe = () => router.push({ path: props.id ? `${base}/data-honor/${props.id}` : `${base}/rincian`, query: { periode: periodeAktif.value } })
const jumlahCocok = computed(() => baris.value.filter((b) => b.status === 'cocok').length)
</script>

<template>
  <p v-if="!selesai" class="panel">Memuat…</p>
  <p v-else-if="!data" class="panel" role="alert">{{ galat || 'Data per minggu belum diambil untuk periode ini. Kembali ke Rincian lalu pilih Ambil data.' }}</p>
  <template v-else>
    <div class="bar-atas"><button class="kembali" type="button" @click="kembaliKe">◂ Kembali</button></div>

    <h1 class="judul">Pertemuan{{ mg ? ` · ${mg.label}` : '' }}</h1>
    <p v-if="mg" class="sub">{{ rentang(mg) }}<template v-if="mg.kategori"> · {{ mg.kategori }}</template> · {{ periodeAktif }}</p>
    <p v-else class="sub">Semua kelas · {{ periodeAktif }}</p>

    <p class="ringkas">
      <span class="lencana ok">{{ jumlahCocok }} cocok</span>
      <span class="lencana beda">{{ baris.length - jumlahCocok }} perlu diperiksa</span>
    </p>

    <section class="panel">
      <div class="scroll-x">
        <table class="tbl">
          <thead>
            <tr>
              <th class="c" rowspan="2">No</th><th rowspan="2">Program studi</th><th rowspan="2">Jenis kelas</th><th class="c" rowspan="2">Kelas</th><th rowspan="2">Mata kuliah</th>
              <th class="c" rowspan="2">SKS</th><th class="c" rowspan="2">Jumlah dosen</th>
              <th class="c" colspan="3">Kelebihan SKS</th>
              <th class="c" colspan="2">Pertemuan rencana</th><th class="c" colspan="2">Pertemuan terealisasi</th>
              <th rowspan="2">Status</th>
            </tr>
            <tr class="sub-th">
              <th class="c">100%</th><th class="c">50%</th><th class="c">Tidak dibayar</th>
              <th class="c">Akademik</th><th class="c">SISTER</th><th class="c">Akademik</th><th class="c">SISTER</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(b, i) in baris" :key="i">
              <td class="c">{{ i + 1 }}</td>
              <td>{{ b.info?.prodi || '–' }}</td>
              <td>{{ b.info?.jenis || '–' }}</td>
              <td class="c"><strong>{{ b.kelas }}</strong></td>
              <td>{{ b.mk }}</td>
              <td class="c">{{ nilai(b.academia?.sks ?? b.sister?.sks) }}</td>
              <td class="c">{{ nilai(b.info?.jml_dosen) }}</td>
              <td v-for="n in [0, 1, 2]" :key="n" class="c">
                <span v-if="lebihAkd(b, n)" class="sks-badge" :class="{ beda: lebihBeda(b, n) }">{{ lebihAkd(b, n) }}</span><span v-else>–</span>
                <div v-if="lebihBeda(b, n)" class="ket">honor: {{ lebihKita(b, n) }}</div>
              </td>
              <td class="c">{{ nilai(b.academia?.rencana) }}</td><td class="c">{{ nilai(b.sister?.rencana) }}</td>
              <td class="c">{{ nilai(b.academia?.realisasi) }}</td><td class="c">{{ nilai(b.sister?.realisasi) }}</td>
              <td>
                <span class="lencana" :class="b.status === 'cocok' ? 'ok' : 'beda'">{{ LABEL[b.status] }}</span>
                <div v-for="s in b.selisih" :key="s" class="ket">{{ s }}</div>
              </td>
            </tr>
            <tr v-if="!baris.length"><td colspan="15" class="kosong">Tidak ada kelas pada minggu ini.</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </template>
</template>

<style scoped>
.bar-atas { margin-bottom: 12px; }
.kembali { background: #fff; color: var(--color-primary); border: 1px solid var(--color-primary); border-radius: 8px; padding: 8px 16px; font: inherit; text-decoration: none; cursor: pointer; }
.kembali:hover { background: #e3efed; }
.judul { font-size: 30px; font-weight: 700; margin: 8px 0 4px; }
.sub { color: #444; margin: 0 0 12px; font-size: 17px; }
.ringkas { display: flex; gap: 8px; flex-wrap: wrap; margin: 0 0 12px; }
.c { text-align: center; }
.ket { color: #555; font-size: 14px; }
.sub-th th { font-weight: 600; font-size: 15px; background: #eef4f3; }
.lencana { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: 15px; font-weight: 600; white-space: nowrap; }
.lencana.ok { background: #c8efd7; color: #0a5928; }
.lencana.beda { background: #fdecc8; color: #8a4b00; }
.sks-badge { display: inline-block; min-width: 56px; padding: 2px 10px; border-radius: 999px; background: #c8efd7; color: #0a5928; font-weight: 700; font-size: 15px; }
.sks-badge.beda { background: #fdecc8; color: #8a4b00; }
</style>
