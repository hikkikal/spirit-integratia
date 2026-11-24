<script setup>
import { onMounted, ref } from 'vue'
import { getTarifNp, simpanTarifNp } from '../services/admin.js'
import { rupiah } from '../lib/format.js'
import ModalAdmin from './ModalAdmin.vue'

// Tarif honor kegiatan non-pengajaran (SBM Internal PNJ 23/2026). Baris tetap; hanya nominal yang dapat diubah.
const daftar = ref([])
const muat = ref(true)
const galat = ref('')
onMounted(async () => {
  try { daftar.value = await getTarifNp() } catch (e) { galat.value = e.message }
  muat.value = false
})

const modal = ref(false)
const form = ref({})
const err = ref({})
const simpan = ref(false)
const galatSimpan = ref('')
function buka(t) { form.value = { ...t, keterangan: t.keterangan ?? '' }; err.value = {}; galatSimpan.value = ''; modal.value = true }
function periksa() {
  const e = {}
  const ok = (v) => Number.isInteger(Number(v)) && Number(v) > 0 && String(v) !== ''
  if (!ok(form.value.golIV)) e.golIV = 'Isi angka rupiah bulat lebih dari 0.'
  if (!ok(form.value.golIII)) e.golIII = 'Isi angka rupiah bulat lebih dari 0.'
  else if (!e.golIV && Number(form.value.golIII) > Number(form.value.golIV)) e.golIII = 'Tidak boleh lebih besar dari golongan IV.'
  err.value = e
  return !Object.keys(e).length
}
async function kirim() {
  if (!periksa() || simpan.value) return
  simpan.value = true
  galatSimpan.value = ''
  try {
    const baru = await simpanTarifNp({ ...form.value, golIV: Number(form.value.golIV), golIII: Number(form.value.golIII) })
    const i = daftar.value.findIndex((x) => x.kode === baru.kode)
    if (i >= 0) daftar.value[i] = baru
    modal.value = false
  } catch (e) {
    galatSimpan.value = e.message
  }
  simpan.value = false
}
</script>

<template>
  <section class="panel blok">
    <h2>Tarif honor kegiatan di luar mengajar</h2>
    <p class="muted">Menurut SBM Internal PNJ No. 23/2026. Daftar kegiatannya tetap; yang dapat diubah hanya nominalnya, dan berlaku pada "Hitung ulang" berikutnya oleh Bidang Keuangan.</p>
    <p v-if="galat" class="galat" role="alert">{{ galat }}</p>
    <div class="scroll-x scroll-box">
      <table class="tbl tbl--line">
        <thead><tr><th>Kegiatan</th><th>Dasar</th><th class="r">Golongan IV</th><th class="r">Golongan III</th><th class="c">Aksi</th></tr></thead>
        <tbody>
          <tr v-if="muat"><td colspan="5" class="kosong">Memuat…</td></tr>
          <tr v-else-if="!daftar.length"><td colspan="5" class="kosong">Tarif belum tersedia. Jalankan db/migrasi_3.sql.</td></tr>
          <tr v-for="t in daftar" :key="t.kode">
            <td>{{ t.nama }}<br v-if="t.keterangan" /><span v-if="t.keterangan" class="muted">{{ t.keterangan }}</span></td>
            <td>{{ t.satuan }}</td>
            <td class="r">{{ rupiah(t.golIV) }}</td>
            <td class="r">{{ rupiah(t.golIII) }}</td>
            <td class="c"><button class="ikon" :aria-label="`Ubah tarif ${t.nama}`" @click="buka(t)">
              <svg viewBox="0 0 24 24"><path d="M4 20l1-4L16.5 4.5a2.1 2.1 0 0 1 3 3L8 19l-4 1zM14 7l3 3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <ModalAdmin :buka="modal" judul="Tarif Non-Pengajaran" :sub="form.nama" @close="modal = false">
    <form class="form" novalidate @submit.prevent="kirim">
      <div class="dua">
        <label class="fld" :class="{ 'fld--err': err.golIV }"><span>Golongan IV (Rp {{ form.satuan }})</span>
          <input v-model="form.golIV" type="number" inputmode="numeric" min="1" step="1" /><small v-if="err.golIV" class="fld__err">{{ err.golIV }}</small>
        </label>
        <label class="fld" :class="{ 'fld--err': err.golIII }"><span>Golongan III (Rp {{ form.satuan }})</span>
          <input v-model="form.golIII" type="number" inputmode="numeric" min="1" step="1" /><small v-if="err.golIII" class="fld__err">{{ err.golIII }}</small>
        </label>
      </div>
      <label class="fld"><span>Keterangan (opsional)</span><input v-model="form.keterangan" type="text" maxlength="200" /></label>
      <p v-if="galatSimpan" class="fld__err" role="alert">{{ galatSimpan }}</p>
      <div class="aksi">
        <button type="button" class="btn btn--ghost" @click="modal = false">Batal</button>
        <button type="submit" class="btn" :disabled="simpan">{{ simpan ? 'Menyimpan…' : 'Simpan' }}</button>
      </div>
    </form>
  </ModalAdmin>
</template>

<style scoped>
.blok { margin-top: 24px; }
h2 { font-size: 18px; margin: 0 0 10px; }
.muted { color: #4a5755; font-size: 15px; }
.galat { color: var(--color-error); }
.r { text-align: right; }
.c { text-align: center; }
.kosong { text-align: center; color: #4a5755; }
.ikon { width: 34px; height: 34px; border: 1px solid #0d8177; border-radius: 6px; background: #fff; color: #000; cursor: pointer; display: inline-grid; place-items: center; }
.ikon:hover { background: #e6f2f0; }
.ikon svg { width: 20px; height: 20px; }
.form { display: grid; gap: 18px; }
.dua { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.aksi { display: flex; justify-content: flex-end; gap: 14px; margin-top: 8px; }
.aksi .btn { padding: 9px 28px; border-radius: 6px; }
@media (max-width: 560px) { .dua { grid-template-columns: 1fr; } }
</style>
