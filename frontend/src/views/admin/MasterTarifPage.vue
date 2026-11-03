<script setup>
import { computed, onMounted, ref } from 'vue'
import { getTarif, simpanTarif } from '../../services/admin.js'
import { JABATAN_OPSI } from '../../data/admin.js'
import { rupiah, tanggalPanjang, hariIni } from '../../lib/format.js'
import ModalAdmin from '../../components/ModalAdmin.vue'

const daftar = ref([])
const muat = ref(true)
const galat = ref('')
const cari = ref('')
const filterJenis = ref('')
const filterJabatan = ref('')

async function ambil() {
  muat.value = true
  try {
    daftar.value = await getTarif()
  } catch (e) {
    galat.value = e.message
  }
  muat.value = false
}
onMounted(ambil)

const aktif = (t) => t.sampai >= hariIni()
const hasil = computed(() => {
  const q = cari.value.trim().toLowerCase()
  return daftar.value.filter(
    (t) =>
      (!q || `${t.jenis} ${t.kelas} ${t.jabatan}`.toLowerCase().includes(q)) &&
      (!filterJenis.value || t.jenis === filterJenis.value) &&
      (!filterJabatan.value || t.jabatan === filterJabatan.value),
  )
})
const kerjaSama = computed(() => daftar.value.filter((t) => t.jenis === 'Kerja Sama'))
const mitra = computed(() => new Set(kerjaSama.value.map((t) => t.kelas.split(' - ').pop())).size)
const reguler = computed(() => daftar.value.filter((t) => t.jenis === 'Reguler').length)

// ---------- Modal tambah/ubah ----------
const modal = ref(false)
const form = ref({})
const err = ref({})
const simpan = ref(false)
const galatSimpan = ref('')

function bukaTambah() {
  form.value = { id: '', jenis: 'Kerja Sama', kelas: '', jabatan: '', honor: '', mulai: hariIni(), sampai: '' }
  err.value = {}
  galatSimpan.value = ''
  modal.value = true
}
function bukaUbah(t) {
  form.value = { ...t }
  err.value = {}
  galatSimpan.value = ''
  modal.value = true
}
function pilihJenis(j) {
  form.value.jenis = j
  if (j === 'Reguler') form.value.kelas = 'Reguler'
  else if (form.value.kelas === 'Reguler') form.value.kelas = ''
}
function periksa() {
  const f = form.value
  const e = {}
  if (!f.kelas.trim()) e.kelas = 'Jenis kelas wajib diisi.'
  if (!f.jabatan) e.jabatan = 'Pilih jabatan/golongan.'
  const h = Number(f.honor)
  if (!f.honor || !Number.isFinite(h) || h <= 0 || !Number.isInteger(h)) e.honor = 'Isi angka rupiah bulat lebih dari 0.'
  if (!f.mulai) e.mulai = 'Tanggal mulai wajib diisi.'
  if (!f.sampai) e.sampai = 'Tanggal berakhir wajib diisi.'
  else if (f.mulai && f.sampai < f.mulai) e.sampai = 'Harus sama dengan atau setelah tanggal mulai.'
  err.value = e
  return !Object.keys(e).length
}
async function kirim() {
  if (!periksa() || simpan.value) return
  simpan.value = true
  galatSimpan.value = ''
  try {
    await simpanTarif({ ...form.value, kelas: form.value.kelas.trim(), honor: Number(form.value.honor) })
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
      <h1 class="page-title">Master Tarif SKS</h1>
      <p class="page-sub">Tarif honor per SKS menurut jenis kelas dan jabatan/golongan</p>
    </div>
    <div class="filter">
      <label class="fld"><span>Cari Master Tarif</span><input v-model="cari" type="search" placeholder="Jenis/jabatan" /></label>
      <label class="fld"><span>Jenis Tarif</span>
        <select v-model="filterJenis"><option value="">Semua Jenis</option><option>Kerja Sama</option><option>Reguler</option></select>
      </label>
      <label class="fld"><span>Jabatan/Golongan</span>
        <select v-model="filterJabatan"><option value="">Semua jabatan</option><option v-for="j in JABATAN_OPSI" :key="j">{{ j }}</option></select>
      </label>
      <button class="btn tambah" @click="bukaTambah">+ Tambah Tarif</button>
    </div>
  </div>

  <section class="kartu3">
    <div class="stat"><div class="stat__lbl">Total Tarif</div><div class="stat__val">{{ daftar.length }}</div><div class="stat__sub">Seluruh jenis kelas</div></div>
    <div class="stat"><div class="stat__lbl">Kerja Sama</div><div class="stat__val">{{ kerjaSama.length }}</div><div class="stat__sub">{{ mitra }} PT Mitra</div></div>
    <div class="stat"><div class="stat__lbl">Reguler</div><div class="stat__val">{{ reguler }}</div><div class="stat__sub">Program Reguler</div></div>
  </section>

  <p v-if="galat" class="galat" role="alert">{{ galat }}</p>
  <section class="panel">
    <div class="scroll-x">
      <table class="tbl tbl--line">
        <thead><tr><th>Jenis</th><th>Jenis Kelas</th><th>Jabatan / Golongan</th><th class="r">Honor per SKS</th><th>Berlaku s.d.</th><th>Status</th><th class="c">Aksi</th></tr></thead>
        <tbody>
          <tr v-if="muat"><td colspan="7" class="kosong">Memuat…</td></tr>
          <tr v-else-if="!hasil.length"><td colspan="7" class="kosong">Tidak ada tarif yang cocok.</td></tr>
          <tr v-for="t in hasil" :key="t.id">
            <td>{{ t.jenis }}</td><td>{{ t.kelas }}</td><td>{{ t.jabatan }}</td>
            <td class="r">{{ rupiah(t.honor) }}</td>
            <td class="tgl"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3" fill="none" stroke="currentColor" stroke-width="1.8" /><path d="M3 10h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>{{ tanggalPanjang(t.sampai) }}</td>
            <td><span class="st" :class="aktif(t) ? 'st--on' : 'st--off'">{{ aktif(t) ? 'AKTIF' : 'KEDALUWARSA' }}</span></td>
            <td class="c"><button class="ikon" :aria-label="`Ubah tarif ${t.kelas}`" @click="bukaUbah(t)">
              <svg viewBox="0 0 24 24"><path d="M4 20l1-4L16.5 4.5a2.1 2.1 0 0 1 3 3L8 19l-4 1zM14 7l3 3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <ModalAdmin v-if="modal" judul="Master Tarif" :sub="form.id ? 'Ubah master tarif' : 'Tambahkan master tarif'" @close="modal = false">
    <form class="form" novalidate @submit.prevent="kirim">
      <div class="fld">
        <span>Jenis Tarif</span>
        <div class="seg" role="group" aria-label="Jenis Tarif">
          <button type="button" :class="{ on: form.jenis === 'Kerja Sama' }" :aria-pressed="form.jenis === 'Kerja Sama'" @click="pilihJenis('Kerja Sama')">Kerja Sama</button>
          <button type="button" :class="{ on: form.jenis === 'Reguler' }" :aria-pressed="form.jenis === 'Reguler'" @click="pilihJenis('Reguler')">Reguler</button>
        </div>
      </div>
      <label class="fld" :class="{ 'fld--err': err.kelas }"><span>Jenis Kelas</span>
        <input v-model="form.kelas" type="text" :placeholder="form.jenis === 'Reguler' ? 'Reguler' : 'Contoh: Teknik Informatika - CCIT'" />
        <small v-if="err.kelas" class="fld__err">{{ err.kelas }}</small>
      </label>
      <div class="dua">
        <label class="fld" :class="{ 'fld--err': err.jabatan }"><span>Jabatan / Golongan</span>
          <select v-model="form.jabatan"><option value="" disabled>Pilih Jabatan</option><option v-for="j in JABATAN_OPSI" :key="j">{{ j }}</option></select>
          <small v-if="err.jabatan" class="fld__err">{{ err.jabatan }}</small>
        </label>
        <label class="fld" :class="{ 'fld--err': err.honor }"><span>Honor per SKS (Rp)</span>
          <input v-model="form.honor" type="number" inputmode="numeric" min="1" step="1" placeholder="Contoh: 1500000" />
          <small v-if="err.honor" class="fld__err">{{ err.honor }}</small>
        </label>
      </div>
      <div class="dua">
        <label class="fld" :class="{ 'fld--err': err.mulai }"><span>Berlaku Mulai</span>
          <input v-model="form.mulai" type="date" /><small v-if="err.mulai" class="fld__err">{{ err.mulai }}</small>
        </label>
        <label class="fld" :class="{ 'fld--err': err.sampai }"><span>Berlaku Sampai (Expired)</span>
          <input v-model="form.sampai" type="date" /><small v-if="err.sampai" class="fld__err">{{ err.sampai }}</small>
        </label>
      </div>
      <p v-if="galatSimpan" class="fld__err" role="alert">{{ galatSimpan }}</p>
      <div class="aksi">
        <button type="button" class="btn btn--ghost" @click="modal = false">Batal</button>
        <button type="submit" class="btn" :disabled="simpan">{{ simpan ? 'Menyimpan…' : 'Simpan' }}</button>
      </div>
    </form>
  </ModalAdmin>
</template>

<style scoped>
.head { display: flex; justify-content: space-between; gap: 16px 24px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 8px; }
.head .page-sub { margin-bottom: 8px; }
.filter { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.filter .fld input, .filter .fld select { border: 0; background: #d9d9d9; border-radius: 4px; min-width: 190px; }
.tambah { background: #0d8177; padding: 11px 22px; }
.kartu3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin: 18px 0; }
.stat__val { font-size: 28px; }
.galat { color: var(--color-error); }
.r { text-align: right; }
.c { text-align: center; }
.kosong { text-align: center; color: #777; }
.tgl { white-space: nowrap; }
.tgl svg { width: 22px; height: 22px; vertical-align: -6px; margin-right: 8px; }
.st { font-size: 13px; letter-spacing: .02em; }
.st--on { color: #0d8177; }
.st--off { color: #b42318; }
.ikon { width: 34px; height: 34px; border: 1px solid #0d8177; border-radius: 6px; background: #fff; color: #000; cursor: pointer; display: inline-grid; place-items: center; }
.ikon:hover { background: #e6f2f0; }
.ikon svg { width: 20px; height: 20px; }
.form { display: grid; gap: 18px; }
.dua { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.seg { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #0d8177; border-radius: 6px; overflow: hidden; }
.seg button { font: inherit; border: 0; background: #fff; color: #0d8177; padding: 10px; cursor: pointer; }
.seg button.on { background: #0d8177; color: #fff; }
.aksi { display: flex; justify-content: flex-end; gap: 14px; margin-top: 8px; }
.aksi .btn { padding: 9px 28px; border-radius: 6px; }
@media (max-width: 900px) { .kartu3 { grid-template-columns: 1fr; } }
@media (max-width: 560px) { .dua { grid-template-columns: 1fr; } }
</style>
