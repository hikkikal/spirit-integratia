<script setup>
import { computed, ref, onMounted } from 'vue'
import { getTarif, getDokumenReferensi, bukaDokumen } from '../../services/data.js'
import { getTarifDaftar, getTarifNonPengajaran } from '../../services/tarif.js'
import { BATAS_100 as B100, BATAS_50 as B50, TARIF_PAJAK as PAJAK0 } from '../../lib/honor.js'
import { rupiah, tanggalPanjang, hariIni } from '../../lib/format.js'

// Tarif & Ketentuan untuk Bidang Keuangan dan Pimpinan. HANYA LIHAT: tarif diambil dari master tarif yang sama dengan
// halaman Master Tarif milik Admin Input, dan hanya Admin Input yang dapat mengubahnya.
const api = ref(null)
const daftar = ref([])
const tarifNp = ref([])
const muat = ref(true)
const galat = ref('')
onMounted(async () => {
  try { api.value = await getTarif() } catch { /* aturan memakai nilai bawaan */ }
  try { daftar.value = await getTarifDaftar() } catch (e) { galat.value = e.message }
  try { tarifNp.value = await getTarifNonPengajaran() } catch { /* bagian ini tampil kosong */ }
  muat.value = false
})

const A = computed(() => api.value?.aturan ?? {})
const BATAS_100 = computed(() => Number(A.value.batas_sks_100 ?? B100))
const BATAS_50 = computed(() => Number(A.value.batas_sks_50 ?? B50))
const wajib = computed(() => [
  ['Dosen biasa', A.value.sks_wajib_ds], ['Direktur', A.value.sks_wajib_dt1],
  ['Jabatan tambahan kelompok 2', A.value.sks_wajib_dt2], ['Jabatan tambahan kelompok 3', A.value.sks_wajib_dt3],
].filter(([, v]) => v !== undefined && v !== null))
const pajakKet = computed(() => api.value
  ? api.value.pajak.map((p) => `golongan ${p.golongan} ${p.tarif <= 1 ? p.tarif * 100 : p.tarif}%`).join(', ')
  : `${PAJAK0 * 100}%`)

// ---------- Tabel tarif (baca saja) ----------
const cari = ref('')
const filterJenis = ref('')
const filterJabatan = ref('')
const tampil = ref(50)
const aktif = (t) => t.sampai >= hariIni()
const jabatanOpsi = computed(() => [...new Set(daftar.value.map((t) => t.jabatan).filter(Boolean))].sort())
const hasil = computed(() => {
  const q = cari.value.trim().toLowerCase()
  return daftar.value.filter((t) =>
    (!q || `${t.jenis} ${t.kelas} ${t.jabatan}`.toLowerCase().includes(q)) &&
    (!filterJenis.value || t.jenis === filterJenis.value) &&
    (!filterJabatan.value || t.jabatan === filterJabatan.value))
})
const kerjaSama = computed(() => daftar.value.filter((t) => t.jenis === 'Kerja Sama'))
const mitra = computed(() => new Set(kerjaSama.value.map((t) => t.kelas.split(' - ').pop())).size)
const reguler = computed(() => daftar.value.filter((t) => t.jenis === 'Reguler').length)

// ---------- Dokumen referensi ----------
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
</script>

<template>
  <h1 class="page-title">Tarif & Ketentuan</h1>
  <p class="page-sub">Dasar perhitungan honor SKS lebih dan kegiatan pendidikan. Halaman ini hanya untuk dilihat; tarif diubah oleh Admin Input.</p>

  <section class="panel blok">
    <h2>Aturan SKS lebih mengajar</h2>
    <ol>
      <li>SKS lebih = SKS terlaksana − SKS wajib.</li>
      <li v-if="wajib.length">SKS wajib:
        <span v-for="([nama, v], i) in wajib" :key="nama">{{ nama }} {{ v }} SKS<span v-if="i < wajib.length - 1">; </span></span>.
      </li>
      <li>{{ BATAS_100 }} SKS lebih pertama dibayar 100%, {{ BATAS_50 }} SKS berikutnya dibayar 50%, selebihnya tidak dibayar.</li>
      <li>SKS wajib dipenuhi dari kelas bertarif terendah; SKS lebih dibayar mulai dari kelas bertarif tertinggi.</li>
      <li>Pajak sementara {{ pajakKet }} dari honor (belum dikonfirmasi).</li>
    </ol>
    <p class="sumber">Sumber: Keputusan Direktur No. 638/2026 dan Peraturan Direktur No. 11/2025.</p>
  </section>

  <section class="panel blok">
    <div class="head">
      <h2>Tarif honor SKS lebih mengajar</h2>
      <div class="filter">
        <label class="fld"><span>Cari</span><input v-model="cari" type="search" placeholder="Kelas, mitra, jabatan" /></label>
        <label class="fld"><span>Jenis Tarif</span>
          <select v-model="filterJenis"><option value="">Semua Jenis</option><option>Kerja Sama</option><option>Reguler</option></select>
        </label>
        <label class="fld"><span>Jabatan/Golongan</span>
          <select v-model="filterJabatan"><option value="">Semua jabatan</option><option v-for="j in jabatanOpsi" :key="j">{{ j }}</option></select>
        </label>
      </div>
    </div>
    <div class="kartu3">
      <div class="stat"><div class="stat__lbl">Total Tarif</div><div class="stat__val">{{ daftar.length }}</div></div>
      <div class="stat"><div class="stat__lbl">Kerja Sama</div><div class="stat__val">{{ kerjaSama.length }}</div><div class="stat__sub">{{ mitra }} mitra</div></div>
      <div class="stat"><div class="stat__lbl">Reguler</div><div class="stat__val">{{ reguler }}</div></div>
    </div>
    <p v-if="galat" class="galat" role="alert">{{ galat }}</p>
    <div class="scroll-x scroll-box">
      <table class="tbl tbl--line">
        <thead><tr><th>Jenis</th><th>Jenis Kelas</th><th>Jabatan / Golongan</th><th class="r">Honor per SKS</th><th>Berlaku s.d.</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-if="muat"><td colspan="6" class="kosong">Memuat…</td></tr>
          <tr v-else-if="!hasil.length"><td colspan="6" class="kosong">Tidak ada tarif yang cocok.</td></tr>
          <tr v-for="t in hasil.slice(0, tampil)" :key="t.id">
            <td>{{ t.jenis }}</td><td>{{ t.kelas }}</td><td>{{ t.jabatan }}</td>
            <td class="r">{{ rupiah(t.honor) }}</td>
            <td class="tgl">{{ tanggalPanjang(t.sampai) }}</td>
            <td><span class="st" :class="aktif(t) ? 'st--on' : 'st--off'">{{ aktif(t) ? 'AKTIF' : 'KEDALUWARSA' }}</span></td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-if="hasil.length > tampil" class="lagi">
      Menampilkan {{ tampil }} dari {{ hasil.length }} tarif.
      <button class="btn btn--ghost" type="button" @click="tampil += 100">Tampilkan lebih banyak</button>
    </p>
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
    <h2>Honor kegiatan di luar mengajar</h2>
    <p class="muted">Tarif bruto menurut SBM Internal PNJ No. 23/2026. Dibayar hanya bila status BKD di SISTER "Beban lebih".</p>
    <div class="scroll-x scroll-box">
      <table class="tbl tbl--line">
        <thead><tr><th>Kegiatan</th><th>Dasar</th><th class="r">Golongan IV</th><th class="r">Golongan III</th></tr></thead>
        <tbody>
          <tr v-for="t in tarifNp" :key="t.kode">
            <td>{{ t.nama }}<br v-if="t.keterangan" /><span v-if="t.keterangan" class="muted">{{ t.keterangan }}</span></td>
            <td>{{ t.satuan }}</td>
            <td class="r">{{ rupiah(t.golIV) }}</td>
            <td class="r">{{ rupiah(t.golIII) }}</td>
          </tr>
          <tr v-if="!tarifNp.length"><td colspan="4" class="kosong">Tarif belum tersedia.</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.blok { margin-bottom: 20px; }
h2 { font-size: 18px; margin: 0 0 10px; }
ol { margin: 0 0 12px; padding-left: 22px; display: grid; gap: 4px; }
.head { display: flex; justify-content: space-between; gap: 12px 24px; flex-wrap: wrap; align-items: flex-end; }
.filter { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.filter .fld input, .filter .fld select { border: 0; background: #e4ecea; border-radius: 4px; min-width: 170px; }
.kartu3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin: 14px 0; }
.stat__val { font-size: 26px; }
.muted, .sumber { color: #4a5755; font-size: 15px; }
.r { text-align: right; }
.kosong { text-align: center; color: #4a5755; }
.tgl { white-space: nowrap; }
.st { font-size: 15px; letter-spacing: .02em; }
.st--on { color: #0d8177; }
.st--off { color: #b42318; }
.lagi { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; color: #4a5755; font-size: 15px; }
.dok { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.dok li { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; border-top: 1px solid #ddd; padding-top: 10px; }
.dok li:first-child { border-top: 0; padding-top: 0; }
.badge { margin-left: 8px; font-size: 15px; background: #eee; border-radius: 10px; padding: 2px 8px; }
.galat { color: #a40000; font-size: 15px; }
@media (max-width: 900px) { .kartu3 { grid-template-columns: 1fr; } }
</style>
