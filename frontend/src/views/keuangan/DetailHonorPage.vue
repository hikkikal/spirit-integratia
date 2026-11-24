<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { honorState, siapkanPeriode } from '../../stores/honor.js'
import { getDetailHonor } from '../../services/data.js'
import { rupiah } from '../../lib/format.js'
import { useBase } from '../../lib/useBase.js'
import { useKoreksi } from '../../lib/useKoreksi.js'
import KoreksiDosenModal from '../../components/KoreksiDosenModal.vue'
import StatusBadge from '../../components/StatusBadge.vue'
import ProfilHonorCard from '../../components/ProfilHonorCard.vue'

// backTo: alamat halaman yang membuka detail ini (Kembali selalu ke sana).
// tampilKerjaSama: tidak dipakai lagi (kartu tarif di bagian atas dihapus); prop dibiarkan agar pemanggil lama tidak error.
// id kosong = detail milik dosen yang sedang login (role Dosen).
const props = defineProps({ id: String, backTo: String, periode: String, tampilKerjaSama: Boolean, milikSendiri: Boolean })
const router = useRouter()
const base = useBase()

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
const kor = useKoreksi(muat)
watch(() => [props.id, periodeAktif.value], muat)
const catatanTampil = computed(() => {
  const c = detail.value?.catatan
  if (!c) return ''
  return props.milikSendiri ? 'Sebagian data Anda sedang diperiksa oleh Bidang Keuangan, jadi angka di bawah dapat berubah.' : c
})
const baris = computed(() => detail.value?.kelas ?? [])
const jumlah = (f) => baris.value.reduce((a, k) => a + k[f], 0)
const total = computed(() => ({
  wajib: jumlah('wajib'), lebih100: jumlah('lebih100'), lebih50: jumlah('lebih50'), lebih0: jumlah('lebih0'),
  subtotal: jumlah('subtotal'), pajak: jumlah('pajak'), honor: jumlah('honor'),
}))
const pecah = (n) => Number(n.toFixed(2)).toLocaleString('id-ID')
// Perhitungan per kelas ditulis apa adanya dan hanya bagian yang ada: "4 × Rp130.000", atau "2 × Rp130.000 + 2 × Rp65.000"
// bila ada SKS lebih yang dibayar 50%. Angka 0 tidak ditampilkan supaya mudah dibaca. Angka dari kolom yang sama pada baris itu.
const tarifKelas = (k) => k.tarif ?? (/^reguler/i.test(k.jenis) ? detail.value.tarifReguler : detail.value.tarifKerjaSama)
const setengah = (t) => (Number.isInteger(t / 2) ? rupiah(t / 2) : `${rupiah(t)} × 50%`)
const rumus = (k) => {
  const t = tarifKelas(k)
  const bagian = []
  if (k.lebih100 > 0) bagian.push(`${pecah(k.lebih100)} × ${rupiah(t)}`)
  if (k.lebih50 > 0) bagian.push(`${pecah(k.lebih50)} × ${setengah(t)}`)
  return bagian.length ? bagian.join(' + ') : '-'
}
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
    <div class="bar-atas">
      <button class="kembali" @click="kembali">◂ Kembali</button>
      <button v-if="kor.bisa.value && id" class="btn btn--ghost btn--sm" type="button" @click="kor.buka(id, dosen.nama)">Koreksi data dosen</button>
    </div>

    <ProfilHonorCard :nama="dosen.nama" :sub="[`${dosen.jabatan} (${dosen.golongan})`, dosen.jurusan].filter(Boolean).join(' · ')" :catatan="catatanTampil" :catatan-judul="milikSendiri ? 'Informasi' : 'Perlu pengecekan'">
      <div class="ket"><span>SKS wajib</span><strong>{{ dosen.sksWajib }}</strong></div>
      <div class="ket"><span>SKS lebih</span><strong><span class="sks-badge">{{ dosen.sksLebih }}</span></strong></div>
      <div class="ket"><span>SKS terlaksana</span><strong>{{ pecah(sksTerlaksana) }}</strong></div>
      <div class="ket"><span>Status</span><StatusBadge :status="dosen.status" /></div>
    </ProfilHonorCard>
    <p v-if="galat" class="galat" role="alert">{{ galat }}</p>

    <section class="panel tabel">
      <div class="scroll-x">
        <table class="tbl tbl--line">
          <thead>
            <tr>
              <th class="c">No</th><th>Kelas</th><th>Program studi</th><th>Jenis kelas</th>
              <th class="c">SKS wajib<br />(100%)</th><th class="c">SKS lebih<br />(100%)</th><th class="c">SKS lebih<br />(50%)</th><th class="c">SKS lebih<br />(0%)</th>
              <th class="c">Tarif<br />per SKS</th><th>Perhitungan bruto</th><th class="c">Subtotal<br />honor bruto</th><th class="c">Pajak</th><th class="c">Honor</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(k, i) in baris" :key="k.mk">
              <td class="c">{{ i + 1 }}</td><td>{{ k.mk }}</td><td>{{ k.prodi || '–' }}</td><td>{{ k.jenis }}</td>
              <td class="c">{{ k.wajib }}</td>
              <td class="c"><span class="sks-badge" :class="{ 'sks-badge--nol': !Number(k.lebih100) }">{{ k.lebih100 }}</span></td>
              <td class="c"><span class="sks-badge" :class="{ 'sks-badge--nol': !Number(k.lebih50) }">{{ k.lebih50 }}</span></td>
              <td class="c"><span class="sks-badge" :class="{ 'sks-badge--nol': !Number(k.lebih0) }">{{ k.lebih0 }}</span></td>
              <td class="c">{{ rupiah(tarifKelas(k)) }}</td><td class="rumus-sel">{{ rumus(k) }}</td>
              <td class="c">{{ rupiah(k.subtotal) }}</td>
              <td class="c">{{ rupiah(k.pajak) }}<br /><small v-if="k.subtotal">{{ persenPajak(k) }}</small></td><td class="c">{{ rupiah(k.honor) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td></td><td colspan="3">Total</td>
              <td class="c">{{ total.wajib }}</td>
              <td class="c"><span class="sks-badge" :class="{ 'sks-badge--nol': !Number(total.lebih100) }">{{ total.lebih100 }}</span></td>
              <td class="c"><span class="sks-badge" :class="{ 'sks-badge--nol': !Number(total.lebih50) }">{{ total.lebih50 }}</span></td>
              <td class="c"><span class="sks-badge" :class="{ 'sks-badge--nol': !Number(total.lebih0) }">{{ total.lebih0 }}</span></td>
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
        <li>SKS wajib dipenuhi dari kelas bertarif terendah; SKS lebih dibayar mulai dari kelas bertarif tertinggi (Perdir 11/2025 Pasal 11 ayat 4).</li>
        <li>SKS lebih: 6 SKS pertama dibayar 100%, 4 SKS berikutnya 50%, selebihnya tidak dibayar (0%).</li>
        <li>Honor kelas = SKS lebih × tarif per SKS. SKS lebih yang dibayar 50% dihitung setengah tarif. Pajak sesuai golongan, honor bersih = honor kelas − pajak.</li>
      </ol>
    </section>
    <p class="note">Periode {{ periodeAktif }} · {{ detail.pajakKet }}.</p>
  </template>
  <KoreksiDosenModal v-if="kor.dibuka.value" :key="kor.dibuka.value.n" :tampil="kor.tampil.value" :dosen-id="kor.dibuka.value.id" :nama="kor.dibuka.value.nama" @close="kor.tutup" @berubah="kor.setelah" />
</template>

<style scoped>
.title { font-size: 28px; font-weight: 500; margin: 0 0 12px; line-height: 1.2; }
.kembali { background: #fff; color: var(--color-primary); border: 1px solid var(--color-primary); border-radius: 8px; padding: 8px 16px; font: inherit; text-decoration: none; cursor: pointer; }
.kembali:hover { background: #e3efed; }
.cards { display: flex; gap: 26px; flex-wrap: wrap; align-items: stretch; margin-bottom: 20px; }
.kartu { background: #ffffff; border: 1px solid #a9bcb8; border-radius: 8px; padding: 10px 12px; display: grid; gap: 4px; align-content: center; font-family: system-ui, sans-serif; font-size: 15px; min-height: 62px; }
.kartu strong { font-size: 15px; }
.verif { margin-left: auto; align-self: center; }
.tabel { padding: 10px 14px; }
.tbl :is(th, td) { font-size: 15px; padding: 12px 10px; }
.tbl .c { text-align: center; }
.tbl tfoot td { border-bottom: 0; border-top: 2px solid #000; padding-top: 14px; }
.tbl tbody td { height: 58px; }
.kosong { color: #4a5755; }
.note { font-size: 15px; color: #4a5755; }
.rumus { margin-top: 20px; padding: 14px 16px; }
.rumus h2 { font-size: 18px; font-weight: 500; margin: 0 0 8px; }
.alur { margin: 0; padding-left: 20px; display: grid; gap: 4px; font-size: 15px; }
.rumus small, .tbl small { color: #3f4c4a; font-size: 15px; }
.rumus-sel { white-space: nowrap; }
.galat { color: #b42318; }
</style>
