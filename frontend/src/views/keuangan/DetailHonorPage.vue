<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { honorState, tandaiTerverifikasi } from '../../stores/honor.js'
import { detailKelas, TARIF_KERJA_SAMA } from '../../data/mock.js'
import { hitungBaris } from '../../lib/honor.js'
import { rupiah } from '../../lib/format.js'
import { useBase } from '../../lib/useBase.js'
import { auth } from '../../stores/auth.js'
import StatusBadge from '../../components/StatusBadge.vue'

// backTo: alamat halaman yang membuka detail ini (Kembali selalu ke sana).
// tampilKerjaSama: role Dosen menampilkan kartu Tarif Kerja Sama (mengikuti Figma).
const props = defineProps({ id: String, backTo: String, periode: String, tampilKerjaSama: Boolean })
const router = useRouter()
const base = useBase()
// Hanya Bidang Keuangan yang boleh memverifikasi. Backend tetap harus memeriksa ulang.
const bisaVerifikasi = computed(() => auth.user?.role === 'keuangan')

const dosen = computed(() => honorState.dosen.find((d) => d.id === props.id))
const detail = computed(() => detailKelas[props.id])

const baris = computed(() =>
  (detail.value?.kelas ?? []).map((k) => ({ ...k, ...hitungBaris(k, detail.value.tarifReguler) })),
)
const jumlah = (f) => baris.value.reduce((a, k) => a + k[f], 0)
const total = computed(() => ({
  wajib: jumlah('wajib'), lebih100: jumlah('lebih100'), lebih50: jumlah('lebih50'), lebih0: jumlah('lebih0'),
  subtotal: jumlah('subtotal'), pajak: jumlah('pajak'), honor: jumlah('honor'),
}))
function kembali() {
  router.push(props.backTo || `${base}/data-honor/${props.id}`)
}
</script>

<template>
  <p v-if="!dosen" class="panel">Dosen tidak ditemukan.</p>
  <template v-else>
    <h1 class="title">Detail honor:<br />{{ dosen.nama }}</h1>
    <button class="kembali" @click="kembali">◂ Kembali</button>

    <section class="cards">
      <div class="kartu"><span>Jabatan</span><strong>{{ dosen.jabatan }} ({{ dosen.golongan }})</strong></div>
      <div class="kartu"><span>SKS wajib</span><strong>{{ dosen.sksWajib }}</strong></div>
      <div class="kartu"><span>SKS lebih</span><strong>{{ dosen.sksLebih }}</strong></div>
      <div class="kartu"><span>Tarif Reguler</span><strong>{{ rupiah(detail?.tarifReguler ?? 130000) }} / SKS</strong></div>
      <div v-if="tampilKerjaSama" class="kartu"><span>Tarif Kerja Sama</span><strong>{{ rupiah(TARIF_KERJA_SAMA) }} / SKS</strong></div>
      <div class="kartu"><span>Status</span><StatusBadge :status="dosen.status" /></div>
      <button v-if="bisaVerifikasi" class="btn verif" :disabled="dosen.status === 'terverifikasi'" @click="tandaiTerverifikasi(dosen.id)">
        {{ dosen.status === 'terverifikasi' ? 'Sudah terverifikasi' : 'Tandai terverifikasi' }}
      </button>
    </section>

    <section class="panel tabel">
      <p v-if="!detail" class="kosong">Rincian kelas dosen ini belum tersedia (menunggu data dari SISTER).</p>
      <div v-else class="scroll-x">
        <table class="tbl tbl--line">
          <thead>
            <tr>
              <th class="c">No</th><th>Kelas</th><th>Jenis kelas</th>
              <th class="c">SKS wajib<br />(100%)</th><th class="c">SKS lebih<br />(100%)</th><th class="c">SKS lebih<br />(50%)</th><th class="c">SKS lebih<br />(0%)</th>
              <th class="c">Subtotal<br />honor bruto</th><th class="c">Pajak</th><th class="c">Honor</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(k, i) in baris" :key="k.mk">
              <td class="c">{{ i + 1 }}</td><td>{{ k.mk }}</td><td>{{ k.jenis }}</td>
              <td class="c">{{ k.wajib }}</td><td class="c">{{ k.lebih100 }}</td><td class="c">{{ k.lebih50 }}</td><td class="c">{{ k.lebih0 }}</td>
              <td class="c">{{ rupiah(k.subtotal) }}</td><td class="c">{{ rupiah(k.pajak) }}</td><td class="c">{{ rupiah(k.honor) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td></td><td colspan="2">Total</td>
              <td class="c">{{ total.wajib }}</td><td class="c">{{ total.lebih100 }}</td><td class="c">{{ total.lebih50 }}</td><td class="c">{{ total.lebih0 }}</td>
              <td class="c">{{ rupiah(total.subtotal) }}</td><td class="c">{{ rupiah(total.pajak) }}</td><td class="c">{{ rupiah(total.honor) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
    <p v-if="detail" class="note">Periode {{ periode || honorState.periode }} · pajak 5% (sementara, belum dikonfirmasi).</p>
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
</style>
