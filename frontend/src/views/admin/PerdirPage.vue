<script setup>
import { computed, onMounted, ref } from 'vue'
import { getPerdir, simpanPerdir, ubahStatusPerdir, hapusPerdir } from '../../services/admin.js'
import { tanggalPanjang, ukuranFile, hariIni } from '../../lib/format.js'
import ModalAdmin from '../../components/ModalAdmin.vue'

const MAKS = 20 * 1024 * 1024
const daftar = ref([])
const muat = ref(true)
const galat = ref('')
const cari = ref('')
const filter = ref('semua') // semua | berlaku | tidak

async function ambil() {
  try {
    daftar.value = await getPerdir()
  } catch (e) {
    galat.value = e.message
  }
  muat.value = false
}
onMounted(ambil)

const nBerlaku = computed(() => daftar.value.filter((p) => p.berlaku).length)
const hasil = computed(() => {
  const q = cari.value.trim().toLowerCase()
  return daftar.value.filter(
    (p) =>
      (!q || `${p.nomor} ${p.judul}`.toLowerCase().includes(q)) &&
      (filter.value === 'semua' || (filter.value === 'berlaku') === p.berlaku),
  )
})

async function ubah(p) {
  galat.value = ''
  try {
    await ubahStatusPerdir(p.id, !p.berlaku)
    await ambil()
  } catch (e) {
    galat.value = e.message
  }
}

// ---------- Hapus (dengan konfirmasi) ----------
const hapus = ref(null)
const menghapus = ref(false)
async function konfirmasiHapus() {
  menghapus.value = true
  try {
    await hapusPerdir(hapus.value.id)
    hapus.value = null
    await ambil()
  } catch (e) {
    galat.value = e.message
    hapus.value = null
  }
  menghapus.value = false
}

// ---------- Upload ----------
const modal = ref(false)
const form = ref({ nomor: '', tanggal: '', judul: '', berkas: null })
const err = ref({})
const simpan = ref(false)
const galatSimpan = ref('')
const input = ref(null)

function bukaUpload() {
  form.value = { nomor: '', tanggal: hariIni(), judul: '', berkas: null }
  err.value = {}
  galatSimpan.value = ''
  modal.value = true
}
function pilihBerkas(e) {
  const f = e.target.files?.[0]
  e.target.value = ''
  if (!f) return
  if (!/\.pdf$/i.test(f.name) || (f.type && f.type !== 'application/pdf')) {
    err.value = { ...err.value, berkas: 'Berkas harus berformat PDF.' }
    return
  }
  if (f.size > MAKS) {
    err.value = { ...err.value, berkas: 'Ukuran berkas melebihi 20 MB.' }
    return
  }
  err.value = { ...err.value, berkas: '' }
  form.value.berkas = f
}
function periksa() {
  const f = form.value
  const e = { berkas: err.value.berkas || '' }
  if (!f.nomor.trim()) e.nomor = 'Nomor peraturan wajib diisi.'
  else if (daftar.value.some((p) => p.nomor.toLowerCase() === f.nomor.trim().toLowerCase())) e.nomor = 'Nomor peraturan ini sudah terdaftar.'
  if (!f.tanggal) e.tanggal = 'Tanggal wajib diisi.'
  if (!f.judul.trim()) e.judul = 'Judul dokumen wajib diisi.'
  if (!f.berkas && !e.berkas) e.berkas = 'Lampirkan berkas PDF.'
  err.value = e
  return !Object.values(e).some(Boolean)
}
async function kirim() {
  if (!periksa() || simpan.value) return
  simpan.value = true
  galatSimpan.value = ''
  try {
    await simpanPerdir({ ...form.value, nomor: form.value.nomor.trim(), judul: form.value.judul.trim() })
    modal.value = false
    await ambil()
  } catch (e) {
    galatSimpan.value = e.message || 'Gagal menyimpan.'
  }
  simpan.value = false
}
</script>

<template>
  <div class="head">
    <div>
      <h1 class="page-title">Dokumen Peraturan Direktur (Perdir)</h1>
      <p class="page-sub">Dokumen peraturan yang menjadi dasar hukum perhitungan honorarium</p>
    </div>
    <button class="btn upload" @click="bukaUpload">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V5M7.5 9.5L12 5l4.5 4.5M4 15v4h16v-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
      Upload Perdir baru
    </button>
  </div>

  <section class="kartu3">
    <div class="stat"><div class="stat__lbl">Total dokumen</div><div class="stat__val">{{ daftar.length }}</div><div class="stat__sub">Perdir dan keputusan direktur</div></div>
    <div class="stat"><div class="stat__lbl">Berlaku</div><div class="stat__val stat__val--ok">{{ nBerlaku }}</div><div class="stat__sub">Dasar perhitungan</div></div>
    <div class="stat"><div class="stat__lbl">Tidak Berlaku</div><div class="stat__val stat__val--off">{{ daftar.length - nBerlaku }}</div><div class="stat__sub">Disimpan sebagai arsip</div></div>
  </section>

  <p v-if="galat" class="galat" role="alert">{{ galat }}</p>
  <section class="panel">
    <div class="bar">
      <h2>Daftar dokumen</h2>
      <div class="kontrol">
        <input v-model="cari" class="cari" type="search" placeholder="Cari nomor atau judul" aria-label="Cari nomor atau judul" />
        <button v-for="o in [['semua', 'Semua'], ['berlaku', 'Berlaku'], ['tidak', 'Tidak Berlaku']]" :key="o[0]" class="chip" :class="{ on: filter === o[0] }" :aria-pressed="filter === o[0]" @click="filter = o[0]">{{ o[1] }}</button>
      </div>
    </div>
    <div class="scroll-x">
      <table class="tbl tbl--line">
        <thead><tr><th>Nomor Peraturan</th><th>Judul</th><th>Tanggal Penetapan</th><th>Dokumen Terlampir</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          <tr v-if="muat"><td colspan="6" class="kosong">Memuat…</td></tr>
          <tr v-else-if="!hasil.length"><td colspan="6" class="kosong">Tidak ada dokumen yang cocok.</td></tr>
          <tr v-for="p in hasil" :key="p.id">
            <td>{{ p.nomor }}</td>
            <td class="judul">{{ p.judul }}</td>
            <td>{{ tanggalPanjang(p.tanggal) }}</td>
            <td class="berkas"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l4 4v14H6zM14 3v4h4M9 13h6M9 17h6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round" /></svg>{{ p.berkas }}</td>
            <td><span class="lencana" :class="p.berlaku ? 'lencana--on' : 'lencana--off'">{{ p.berlaku ? 'Berlaku' : 'Tidak berlaku' }}</span></td>
            <td class="aksi">
              <button class="btn btn--ghost btn--kecil" @click="ubah(p)">{{ p.berlaku ? 'Tandai tidak berlaku' : 'Tandai berlaku' }}</button>
              <button class="hps" :aria-label="`Hapus ${p.nomor}`" @click="hapus = p">
                <svg viewBox="0 0 24 24"><path d="M5 7h14M10 7V4h4v3M7 7l1 13h8l1-13M10 11v6M14 11v6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <ModalAdmin v-if="modal" judul="Upload Perdir" sub="Isi data dan lampirkan berkas PDF" @close="modal = false">
    <form class="form" novalidate @submit.prevent="kirim">
      <div class="dua">
        <label class="fld" :class="{ 'fld--err': err.nomor }"><span>Nomor Peraturan</span>
          <input v-model="form.nomor" type="text" placeholder="Contoh: SK-DIR/024/2026" /><small v-if="err.nomor" class="fld__err">{{ err.nomor }}</small>
        </label>
        <label class="fld" :class="{ 'fld--err': err.tanggal }"><span>Berlaku Mulai</span>
          <input v-model="form.tanggal" type="date" /><small v-if="err.tanggal" class="fld__err">{{ err.tanggal }}</small>
        </label>
      </div>
      <label class="fld" :class="{ 'fld--err': err.judul }"><span>Judul Dokumen</span>
        <input v-model="form.judul" type="text" placeholder="Contoh: Standar Tarif Honorarium SKS TA 2027/2028" /><small v-if="err.judul" class="fld__err">{{ err.judul }}</small>
      </label>
      <div class="fld" :class="{ 'fld--err': err.berkas }">
        <span>File lampiran (PDF, maks. 20 MB)</span>
        <input ref="input" class="tersembunyi" type="file" accept="application/pdf,.pdf" @change="pilihBerkas" />
        <div v-if="form.berkas" class="file">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l4 4v14H6zM14 3v4h4M9 13h6M9 17h6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round" /></svg>
          <span class="nm">{{ form.berkas.name }}</span><span>{{ ukuranFile(form.berkas.size) }}</span>
          <button type="button" class="lepas" @click="form.berkas = null">Hapus</button>
        </div>
        <button v-else type="button" class="pilih" @click="input.click()">Pilih berkas PDF…</button>
        <small v-if="err.berkas" class="fld__err">{{ err.berkas }}</small>
      </div>
      <p v-if="galatSimpan" class="fld__err" role="alert">{{ galatSimpan }}</p>
      <div class="aksi2">
        <button type="button" class="btn btn--ghost" @click="modal = false">Batal</button>
        <button type="submit" class="btn" :disabled="simpan">{{ simpan ? 'Menyimpan…' : 'Simpan' }}</button>
      </div>
    </form>
  </ModalAdmin>

  <ModalAdmin v-if="hapus" judul="Hapus dokumen?" :sub="`${hapus.nomor} akan dihapus dari daftar.`" @close="hapus = null">
    <p class="peringatan">Tindakan ini tidak dapat dibatalkan. Jika dokumen hanya sudah tidak berlaku, gunakan “Tandai tidak berlaku” agar tetap tersimpan sebagai arsip.</p>
    <div class="aksi2">
      <button class="btn btn--ghost" @click="hapus = null">Batal</button>
      <button class="btn btn--bahaya" :disabled="menghapus" @click="konfirmasiHapus">{{ menghapus ? 'Menghapus…' : 'Hapus' }}</button>
    </div>
  </ModalAdmin>
</template>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
.upload { display: inline-flex; align-items: center; gap: 10px; background: #0d8177; }
.upload svg { width: 20px; height: 20px; }
.kartu3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin: 8px 0 18px; }
.stat__val { font-size: 28px; }
.stat__val--ok { color: #0d8177; }
.stat__val--off { color: #b42318; }
.galat { color: var(--color-error); }
.bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 8px; }
.bar h2 { margin: 0; font-size: 17px; }
.kontrol { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.cari { font: inherit; font-size: 14px; padding: 6px 12px; border: 1px solid #000; border-radius: 4px; background: #fff; min-width: 200px; }
.chip { font: inherit; font-size: 14px; padding: 6px 16px; border-radius: 999px; border: 1px solid #0d8177; background: #fff; color: #0d8177; cursor: pointer; }
.chip.on { background: #0d8177; color: #fff; }
.kosong { text-align: center; color: #777; }
.judul { min-width: 220px; max-width: 320px; }
.berkas { white-space: nowrap; }
.berkas svg { width: 22px; height: 22px; vertical-align: -6px; margin-right: 8px; }
.lencana { display: inline-block; border-radius: 4px; padding: 2px 12px; font-size: 13px; white-space: nowrap; }
.lencana--on { background: #8dfcb3; color: #0a5928; }
.lencana--off { border: 1px solid #b42318; color: #b42318; background: #fff; }
.aksi { white-space: nowrap; }
.aksi .btn--kecil { margin-right: 12px; }
.hps { width: 32px; height: 32px; border: 1px solid #b42318; border-radius: 6px; background: #fff; color: #b42318; cursor: pointer; vertical-align: middle; display: inline-grid; place-items: center; }
.hps:hover { background: #fdeaea; }
.hps svg { width: 18px; height: 18px; }
.form { display: grid; gap: 18px; }
.dua { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.tersembunyi { display: none; }
.pilih { font: inherit; border: 1px dashed #0d8177; background: #f0f8f7; color: #0d8177; border-radius: 6px; padding: 14px; cursor: pointer; }
.pilih:hover { background: #e2f2f0; }
.file { display: flex; align-items: center; gap: 12px; background: #b6dedb; border-radius: 6px; padding: 12px 16px; }
.file svg { width: 22px; height: 22px; color: #0d8177; flex: none; }
.file .nm { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lepas { background: none; border: 0; color: #b42318; font: inherit; cursor: pointer; }
.aksi2 { display: flex; justify-content: flex-end; gap: 14px; margin-top: 6px; }
.aksi2 .btn { padding: 9px 28px; border-radius: 6px; }
.btn--bahaya { background: #b42318; }
.btn--bahaya:hover { background: #8e1b12; }
.peringatan { color: #444; margin: 0 0 18px; }
@media (max-width: 900px) { .kartu3 { grid-template-columns: 1fr; } }
@media (max-width: 560px) { .dua { grid-template-columns: 1fr; } }
</style>
