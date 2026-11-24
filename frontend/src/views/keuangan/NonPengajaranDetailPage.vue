<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { honorState, siapkanPeriode } from '../../stores/honor.js'
import { getNpDetail, verifikasiNp } from '../../services/nonpengajaran.js'
import { rupiah } from '../../lib/format.js'
import { useBase } from '../../lib/useBase.js'
import { useKoreksi } from '../../lib/useKoreksi.js'
import KoreksiDosenModal from '../../components/KoreksiDosenModal.vue'
import { auth } from '../../stores/auth.js'
import StatusBadge from '../../components/StatusBadge.vue'
import ProfilHonorCard from '../../components/ProfilHonorCard.vue'
import DetailKegiatanDialog from '../../components/DetailKegiatanDialog.vue'

// Detail honor non-pengajaran satu dosen. Hanya Keuangan yang dapat memverifikasi (backend memeriksa ulang).
// milikSendiri: dosen melihat miliknya sendiri (id dari sesi), tanpa tombol Kembali dan tanpa verifikasi.
const props = defineProps({ id: String, milikSendiri: Boolean })
const router = useRouter()
const base = useBase()
const bisaVerifikasi = computed(() => auth.user?.role === 'keuangan' && !props.milikSendiri)

const detail = ref(null)
const selesai = ref(false)
const galat = ref('')
const proses = ref(false)
async function muat() {
  galat.value = ''
  try {
    await siapkanPeriode()
    detail.value = await getNpDetail(props.milikSendiri ? null : props.id, honorState.periode)
  } catch (e) {
    detail.value = null
    galat.value = e.message
  }
  selesai.value = true
}
onMounted(muat)
const kor = useKoreksi(muat)
watch(() => [props.id, honorState.periode], muat)

async function verif() {
  if (proses.value) return
  proses.value = true
  galat.value = ''
  try {
    await verifikasiNp(props.id, honorState.periode)
    await muat()
  } catch (e) {
    galat.value = e.message
  }
  proses.value = false
}
const sudah = computed(() => detail.value?.status === 'terverifikasi')

// Rincian satu kegiatan (mengikuti Figma "Detail Kegiatan"). Baris tambahan dari SISTER (k.detail) tampil bila sudah tersedia.
const pilih = ref(null)       // tetap terisi selama animasi tutup
const terbuka = ref(false)
function buka(k) {
  pilih.value = k
  terbuka.value = true
}
const tutup = () => { terbuka.value = false }
</script>

<template>
  <p v-if="!selesai" class="panel">Memuat…</p>
  <p v-else-if="!detail" class="panel" role="alert">{{ galat || 'Belum ada kegiatan non-pengajaran untuk dosen ini pada periode ini.' }}</p>
  <template v-else>
    <div class="bar-atas">
      <button v-if="!milikSendiri" class="kembali" type="button" @click="router.push(`${base}/non-pengajaran`)">← Kembali</button>
      <button v-if="kor.bisa.value && id" class="btn btn--ghost btn--sm" type="button" @click="kor.buka(id, detail.nama)">Koreksi data dosen</button>
    </div>

    <ProfilHonorCard :nama="detail.nama" :sub="[detail.jabatan, detail.jurusan].filter(Boolean).join(' · ')">
      <template #aksi>
        <button v-if="bisaVerifikasi" class="btn" type="button" :disabled="sudah || proses || !detail.totalKegiatan" @click="verif">
          {{ sudah ? 'Sudah terverifikasi' : proses ? 'Memproses…' : 'Tandai terverifikasi' }}
        </button>
      </template>
      <div class="ket"><span>Total kegiatan</span><strong>{{ detail.totalKegiatan }}</strong></div>
      <div class="ket"><span>Total honor (bruto)</span><strong>{{ rupiah(detail.totalHonor) }}</strong></div>
      <div class="ket"><span>Status</span><StatusBadge :status="detail.status" /></div>
    </ProfilHonorCard>
    <p v-if="galat" class="galat" role="alert">{{ galat }}</p>

    <section class="panel tabel">
      <div class="scroll-x">
        <table class="tbl tbl--line">
          <thead>
            <tr><th>No</th><th>Judul</th><th>Jenis kegiatan</th><th>Kategori kegiatan</th><th>Program studi</th><th>Perguruan tinggi</th><th class="r">Mhs</th><th>Status BKD</th><th class="r">Honor bruto</th></tr>
          </thead>
          <tbody>
            <tr v-for="k in detail.kegiatan" :key="k.no" class="klik" tabindex="0" :aria-label="`Lihat rincian: ${k.judul}`" @click="buka(k)" @keydown.enter="buka(k)">
              <td>{{ k.no }}</td><td>{{ k.judul }}</td>
              <td><span class="np-tag">{{ k.jenis }}</span></td>
              <td>{{ k.kategori }}</td><td>{{ k.prodi || '-' }}</td><td>{{ k.kampus || '-' }}</td>
              <td class="r">{{ k.jumlahMhs ?? '–' }}</td>
              <td><span v-if="k.statBkd" class="stat" :class="{ 'stat--lebih': k.statBkd === 'Beban lebih' }">{{ k.statBkd }}</span><span v-else class="kosong" title="Tidak ditemukan di BKD SISTER">–</span></td>
              <td class="r">{{ rupiah(k.honor) }}<br v-if="k.catatan" /><small v-if="k.catatan" class="cat">{{ k.catatan }}</small></td>
            </tr>
            <tr v-if="!detail.kegiatan.length"><td colspan="9" class="kosong">Belum ada kegiatan pada periode ini.</td></tr>
          </tbody>
          <tfoot>
            <tr><td colspan="8"><strong>Total : {{ detail.totalKegiatan }} Kegiatan</strong></td><td class="r"><strong>{{ rupiah(detail.totalHonor) }}</strong></td></tr>
          </tfoot>
        </table>
      </div>
    </section>
    <DetailKegiatanDialog :buka="terbuka" :kegiatan="pilih" :nama="detail.nama" :periode="honorState.periode" @close="tutup" />
    <p class="note">Periode {{ honorState.periode }} · honor bruto bertanda – belum dihitung dari tarif SBM. Status BKD – berarti kegiatan tidak ditemukan di BKD SISTER; "Beban lebih" = yang berhak honor menurut Kepdir 638/2026.</p>
  </template>
  <KoreksiDosenModal v-if="kor.dibuka.value" :key="kor.dibuka.value.n" :tampil="kor.tampil.value" :dosen-id="kor.dibuka.value.id" :nama="kor.dibuka.value.nama" @close="kor.tutup" @berubah="kor.setelah" />
</template>

<style scoped>
.title { font-size: 28px; font-weight: 500; margin: 0 0 12px; line-height: 1.2; }
.kembali { background: #fff; border: 1px solid var(--color-primary); color: var(--color-primary); border-radius: 8px; padding: 8px 16px; font: inherit; cursor: pointer; }
.cards { display: flex; gap: 22px; flex-wrap: wrap; align-items: stretch; margin-bottom: 20px; }
.kartu { background: #ffffff; border: 1px solid #a9bcb8; border-radius: 10px; padding: 10px 14px; display: grid; gap: 4px; align-content: center; font-size: 15px; min-height: 62px; }
.kartu strong { font-size: 18px; font-weight: 500; }
.aksi { margin-left: auto; align-self: center; }
.aksi:disabled { background: #e4ecea; color: #333; cursor: default; }
.tabel { padding: 10px 14px; }
.tbl :is(th, td) { font-size: 15px; padding: 14px 10px; }
.tbl tfoot td { border-top: 2px solid #000; }
.np-tag { display: inline-block; font-size: 15px; border: 1px solid #a9bcb8; border-radius: 999px; padding: 3px 14px; background: #fff; white-space: nowrap; flex: none; }
.r { text-align: right; }
.stat { display: inline-block; font-size: 15px; border: 1px solid #6b7a77; border-radius: 999px; padding: 2px 10px; background: #fff; color: #3f4c4a; white-space: nowrap; }
.stat--lebih { border-color: var(--color-primary); background: var(--color-primary); color: #fff; }
.kosong { text-align: center; color: #4a5755; }
.galat { color: #b42318; }
.klik { cursor: pointer; }
.klik:hover, .klik:focus-visible { background: #e8f1ef; outline: none; }
.note { color: #4a5755; font-size: 15px; }
</style>
