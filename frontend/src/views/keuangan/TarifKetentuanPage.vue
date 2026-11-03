<script setup>
import { computed, ref, onMounted } from 'vue'
import { TARIF_KERJA_SAMA, tarifPendidikan } from '../../data/mock.js'
import { TARIF_REGULER } from '../../data/dosen.js'
import { BATAS_100 as B100, BATAS_50 as B50, TARIF_PAJAK as PAJAK0 } from '../../lib/honor.js'
import { getTarif, getDokumenReferensi, bukaDokumen } from '../../services/data.js'
import { rupiah } from '../../lib/format.js'

// Mode API: tarif, pajak, dan batas diambil dari master tarif di basis data.
const api = ref(null)
onMounted(async () => {
  try { api.value = await getTarif() } catch { /* tetap pakai nilai bawaan */ }
})
const cari = (jenis) => api.value?.tarif.find((t) => t.jenis === jenis && (t.golongan === 'Semua' || t.golongan === 'III'))?.perSks
const tarifReguler = computed(() => cari('Reguler') ?? TARIF_REGULER)
const tarifKerjaSama = computed(() => cari('Kerja Sama') ?? TARIF_KERJA_SAMA)
const BATAS_100 = computed(() => Number(api.value?.aturan?.batas_sks_100 ?? B100))
const BATAS_50 = computed(() => Number(api.value?.aturan?.batas_sks_50 ?? B50))
const pajakKet = computed(() => api.value
  ? api.value.pajak.map((p) => `Gol ${p.golongan} ${p.tarif <= 1 ? p.tarif * 100 : p.tarif}%`).join(', ')
  : `${PAJAK0 * 100}%`)

const dokumen = ref([])
const galatDok = ref('')
onMounted(async () => {
  try { dokumen.value = await getDokumenReferensi() } catch { dokumen.value = [] }
})
const dokumenUrut = computed(() => [...dokumen.value].sort((a, b) => Number(b.berlaku) - Number(a.berlaku)))
async function buka(d) {
  galatDok.value = ''
  try { await bukaDokumen(d.id) } catch (e) { galatDok.value = e.message }
}

// Halaman baca-saja. Belum ada desain Figma untuk halaman ini; isi mengikuti aturan yang
// dipakai perhitungan. TODO(backend): ambil dari tabel master tarif supaya selalu terbaru.
</script>

<template>
  <h1 class="page-title">Tarif & Ketentuan</h1>
  <p class="page-sub">Dasar perhitungan honor SKS lebih dan kegiatan pendidikan</p>

  <section class="panel blok">
    <h2>Aturan SKS lebih mengajar</h2>
    <ol>
      <li>SKS lebih = SKS terlaksana − SKS wajib (menurut jabatan fungsional).</li>
      <li>{{ BATAS_100 }} SKS lebih pertama dibayar 100%.</li>
      <li>{{ BATAS_50 }} SKS lebih berikutnya dibayar 50%.</li>
      <li>SKS lebih sisanya tidak dibayar.</li>
      <li>Pajak sementara {{ pajakKet }} dari honor bruto (belum dikonfirmasi).</li>
    </ol>
    <div class="tarif">
      <div class="kartu"><span>Tarif reguler (Gol III)</span><strong>{{ rupiah(tarifReguler) }} / SKS</strong></div>
      <div class="kartu"><span>Tarif kerja sama</span><strong>{{ rupiah(tarifKerjaSama) }} / SKS</strong></div>
    </div>
    <p class="sumber">Sumber: Keputusan Direktur No. 638/2026 dan Peraturan Direktur No. 11/2025.</p>
  </section>

  <section v-if="dokumen.length" class="panel blok">
    <h2>Dokumen referensi</h2>
    <p class="muted">Peraturan yang menjadi dasar tarif. Dibuka dalam tab baru.</p>
    <ul class="dok">
      <li v-for="d in dokumenUrut" :key="d.id">
        <div>
          <strong>{{ d.nomor }}</strong>
          <span v-if="!d.berlaku" class="badge">Tidak berlaku</span><br />
          <span class="muted">{{ d.judul }}</span>
        </div>
        <button class="btn" :disabled="!d.adaBerkas" :title="d.adaBerkas ? d.berkas : 'Berkas belum diunggah'" @click="buka(d)">
          {{ d.adaBerkas ? 'Lihat dokumen' : 'Berkas belum diunggah' }}
        </button>
      </li>
    </ul>
    <p v-if="galatDok" class="galat" role="alert">{{ galatDok }}</p>
  </section>

  <section class="panel blok">
    <h2>Honor kegiatan unsur pendidikan (di luar mengajar)</h2>
    <p class="muted">Honor dibayarkan jika penugasan melampaui BKD. Tarif bruto, per orang.</p>
    <div class="scroll-x">
      <table class="tbl tbl--line">
        <thead><tr><th>Kegiatan</th><th>Dasar hitung</th><th class="r">Gol IV</th><th class="r">Gol III</th><th>Catatan</th></tr></thead>
        <tbody>
          <tr v-for="t in tarifPendidikan" :key="t.kegiatan">
            <td>{{ t.kegiatan }}</td><td>{{ t.satuan }} × tarif</td>
            <td class="r">{{ rupiah(t.gol4) }}</td><td class="r">{{ rupiah(t.gol3) }}</td><td>{{ t.catatan || '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <p class="sumber">Sumber: SK Standar Biaya Masukan Internal PNJ No. 23 Tahun 2026. Program kerja sama mengikuti tarif pada PKS masing-masing.</p>
  </section>
</template>

<style scoped>
.blok { margin-bottom: 20px; }
h2 { font-size: 18px; margin: 0 0 10px; }
ol { margin: 0 0 16px; padding-left: 22px; display: grid; gap: 4px; }
.tarif { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 10px; }
.kartu { background: #fff; border: 1px solid #000; border-radius: 8px; padding: 10px 14px; display: grid; gap: 4px; }
.muted, .sumber { color: #55554f; font-size: 14px; }
.r { text-align: right; }
.tbl { font-size: 15px; }
.dok { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.dok li { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; border-top: 1px solid #ddd; padding-top: 10px; }
.dok li:first-child { border-top: 0; padding-top: 0; }
.badge { margin-left: 8px; font-size: 12px; background: #eee; border-radius: 10px; padding: 2px 8px; }
.galat { color: #a40000; font-size: 14px; }
</style>
