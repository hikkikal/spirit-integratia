<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { honorState, tandaiTerverifikasi, siapkanPeriode } from '../../stores/honor.js'
import { getDetailHonor } from '../../services/data.js'
import { rupiah } from '../../lib/format.js'
import { useBase } from '../../lib/useBase.js'
import { auth } from '../../stores/auth.js'
import StatusBadge from '../../components/StatusBadge.vue'

// backTo: alamat halaman yang membuka detail ini (Kembali selalu ke sana).
// tampilKerjaSama: role Dosen menampilkan kartu Tarif Kerja Sama (mengikuti Figma).
// id kosong = detail milik dosen yang sedang login (role Dosen).
const props = defineProps({ id: String, backTo: String, periode: String, tampilKerjaSama: Boolean, milikSendiri: Boolean })
const router = useRouter()
const base = useBase()
// Hanya Bidang Keuangan yang boleh memverifikasi. Backend tetap harus memeriksa ulang.
const bisaVerifikasi = computed(() => auth.user?.role === 'keuangan')

const detail = ref(null)
const selesai = ref(false)
const galat = ref('')
const dosen = computed(() => detail.value)
const periodeAktif = computed(() => props.periode || honorState.periode)
async function muat() {
  galat.value = ''
  try {
    if (!props.periode) await siapkanPeriode()
    detail.value = await getDetailHonor(props.milikSendiri ? null : props.id, periodeAktif.value)
  } catch (e) {
    detail.value = null
    galat.value = e.message
  }
  selesai.value = true
}
onMounted(muat)
watch(() => [props.id, periodeAktif.value], muat)
async function verif() {
  try {
    await tandaiTerverifikasi(props.id)
    await muat()
  } catch (e) {
    galat.value = e.message
  }
}

const baris = computed(() => detail.value?.kelas ?? [])
const jumlah = (f) => baris.value.reduce((a, k) => a + k[f], 0)
const total = computed(() => ({
  wajib: jumlah('wajib'), lebih100: jumlah('lebih100'), lebih50: jumlah('lebih50'), lebih0: jumlah('lebih0'),
  subtotal: jumlah('subtotal'), pajak: jumlah('pajak'), honor: jumlah('honor'),
}))
const pecah = (n) => Number(n.toFixed(2)).toLocaleString('id-ID')
// Rumus bruto per kelas, dari angka yang sama dengan kolom lain pada baris itu.
const tarifKelas = (k) => k.tarif ?? (/^reguler/i.test(k.jenis) ? detail.value.tarifReguler : detail.value.tarifKerjaSama)
const rumus = (k) => (k.lebih100 + k.lebih50 > 0 ? `(${pecah(k.lebih100)} + ${pecah(k.lebih50)} × 0,5) × ${rupiah(tarifKelas(k))}` : '-')
const persenPajak = (k) => (k.subtotal ? pecah((k.pajak / k.subtotal) * 100) + '%' : '')
const sksTerlaksana = computed(() => detail.value?.sksTerlaksana ?? (total.value.wajib + total.value.lebih100 + total.value.lebih50 + total.value.lebih0))
function kembali() {
  router.push(props.backTo || `${base}/data-honor/${props.id}`)
}
</script>

<template>
  <p v-if="!selesai" class="panel">Memuat…</p>
  <p v-else-if="!dosen" class="panel" role="alert">{{ galat || 'Rincian kelas dosen ini belum tersedia (menunggu perhitungan).' }}</p>
  <template v-else>
    <h1 class="title">Detail honor:<br />{{ dosen.nama }}</h1>
    <button class="kembali" @click="kembali">◂ Kembali</button>

    <section class="cards">
      <div class="kartu"><span>Jabatan</span><strong>{{ dosen.jabatan }} ({{ dosen.golongan }})</strong></div>
      <div class="kartu"><span>SKS wajib</span><strong>{{ dosen.sksWajib }}</strong></div>
      <div class="kartu"><span>SKS lebih</span><strong>{{ dosen.sksLebih }}</strong></div>
      <div class="kartu"><span>Tarif Reguler</span><strong>{{ rupiah(detail.tarifReguler) }} / SKS</strong></div>
      <div v-if="tampilKerjaSama" class="kartu"><span>Tarif Kerja Sama</span><strong>{{ rupiah(detail.tarifKerjaSama) }} / SKS</strong></div>
      <div class="kartu"><span>Status</span><StatusBadge :status="dosen.status" /></div>
      <button v-if="bisaVerifikasi" class="btn verif" :disabled="dosen.status === 'terverifikasi'" @click="verif">
        {{ dosen.status === 'terverifikasi' ? 'Sudah terverifikasi' : 'Tandai terverifikasi' }}
      </button>
    </section>

    <section class="panel tabel">
      <div class="scroll-x">
        <table class="tbl tbl--line">
          <thead>
            <tr>
              <th class="c">No</th><th>Kelas</th><th>Jenis kelas</th>
              <th class="c">SKS wajib<br />(100%)</th><th class="c">SKS lebih<br />(100%)</th><th class="c">SKS lebih<br />(50%)</th><th class="c">SKS lebih<br />(0%)</th>
              <th class="c">Tarif<br />per SKS</th><th>Perhitungan bruto</th><th class="c">Subtotal<br />honor bruto</th><th class="c">Pajak</th><th class="c">Honor</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(k, i) in baris" :key="k.mk">
              <td class="c">{{ i + 1 }}</td><td>{{ k.mk }}</td><td>{{ k.jenis }}</td>
              <td class="c">{{ k.wajib }}</td><td class="c">{{ k.lebih100 }}</td><td class="c">{{ k.lebih50 }}</td><td class="c">{{ k.lebih0 }}</td>
              <td class="c">{{ rupiah(tarifKelas(k)) }}</td><td class="rumus-sel">{{ rumus(k) }}</td>
              <td class="c">{{ rupiah(k.subtotal) }}</td>
              <td class="c">{{ rupiah(k.pajak) }}<br /><small v-if="k.subtotal">{{ persenPajak(k) }}</small></td><td class="c">{{ rupiah(k.honor) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td></td><td colspan="2">Total</td>
              <td class="c">{{ total.wajib }}</td><td class="c">{{ total.lebih100 }}</td><td class="c">{{ total.lebih50 }}</td><td class="c">{{ total.lebih0 }}</td>
              <td></td><td></td>
              <td class="c">{{ rupiah(total.subtotal) }}</td><td class="c">{{ rupiah(total.pajak) }}</td><td class="c">{{ rupiah(total.honor) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
    <section class="panel rumus">
      <h2>Cara perhitungan</h2>
      <ol class="alur">
        <li>SKS terlaksana: <strong>{{ pecah(sksTerlaksana) }}</strong> − SKS wajib <strong>{{ pecah(dosen.sksWajib) }}</strong> = SKS lebih <strong>{{ pecah(dosen.sksLebih) }}</strong>.</li>
        <li>SKS wajib dipenuhi kelas Reguler terlebih dahulu, lalu Kerja Sama.</li>
        <li>SKS lebih: 6 SKS pertama dibayar 100%, 4 SKS berikutnya 50%, selebihnya tidak dibayar (0%).</li>
        <li>Bruto = (SKS lebih 100% + SKS lebih 50% × 0,5) × tarif per SKS. Pajak sesuai golongan, netto = bruto − pajak.</li>
      </ol>
    </section>
    <p class="note">Periode {{ periodeAktif }} · {{ detail.pajakKet }}.</p>
  </template>
</template>

<style scoped>
.title { font-size: 28px; font-weight: 500; margin: 0 0 12px; line-height: 1.2; }
.kembali { background: #ffee00; border: 1px solid #000; border-radius: 8px; padding: 6px 12px; font: inherit; cursor: pointer; margin-bottom: 20px; }
.cards { display: flex; gap: 26px; flex-wrap: wrap; align-items: stretch; margin-bottom: 20px; }
.kartu { background: #f2f5f9; border: 1px solid #000; border-radius: 8px; padding: 10px 12px; display: grid; gap: 4px; align-content: center; font-family: system-ui, sans-serif; font-size: 14px; min-height: 62px; }
.kartu strong { font-size: 14px; }
.verif { margin-left: auto; align-self: center; }
.tabel { padding: 10px 14px; }
.tbl :is(th, td) { font-size: 14px; padding: 12px 10px; }
.tbl .c { text-align: center; }
.tbl tfoot td { border-bottom: 0; border-top: 2px solid #000; padding-top: 14px; }
.tbl tbody td { height: 58px; }
.kosong { color: #77776f; }
.note { font-size: 14px; color: #77776f; }
.rumus { margin-top: 20px; padding: 14px 16px; }
.rumus h2 { font-size: 18px; font-weight: 500; margin: 0 0 8px; }
.alur { margin: 0; padding-left: 20px; display: grid; gap: 4px; font-size: 14px; }
.rumus small, .tbl small { color: #555; font-size: 12px; }
.rumus-sel { white-space: nowrap; }
</style>
