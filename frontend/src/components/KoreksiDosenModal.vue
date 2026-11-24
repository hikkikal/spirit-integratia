<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import ModalAdmin from './ModalAdmin.vue'
import { honorState } from '../stores/honor.js'
import { getKoreksi, ubahJabatan, ubahGolongan, tambahTugas, hapusTugas, pulihkanTugas, kelompokTugas } from '../services/koreksi.js'

// Popup data dosen: jabatan fungsional, golongan, dan tugas tambahan. Koreksi satu per satu, alasan wajib, tercatat di log.
const props = defineProps({ dosenId: { type: String, required: true }, nama: String, tampil: { type: Boolean, default: true } })
const emit = defineEmits(['close', 'berubah'])

const d = ref(null)
const muat = ref(true)
const galat = ref('')
const hasil = ref(null)        // ringkasan hitung ulang setelah menyimpan
const proses = ref(false)
const aktif = ref(null)        // { jenis: 'jabatan'|'golongan'|'tambah'|'hapus'|'pulihkan', tugas? }
const f = reactive({ jabatan: '', golongan: '', nama: '', kelompok: '', unit: '', mulai: '', selesai: '', alasan: '' })

const periode = () => honorState.periode
async function ambil() {
  muat.value = true
  galat.value = ''
  try { d.value = await getKoreksi(props.dosenId, periode()) } catch (e) { galat.value = e.message }
  muat.value = false
}
onMounted(ambil)

function buka(jenis, tugas = null) {
  aktif.value = { jenis, tugas }
  galat.value = ''
  f.alasan = ''
  f.jabatan = d.value?.jabatan.manual ? d.value.jabatan.id ?? '' : ''
  f.golongan = d.value?.golongan.manual ? d.value.golongan.nilai ?? '' : ''
  f.nama = ''; f.unit = ''; f.mulai = ''; f.selesai = ''
  f.kelompok = jenis === 'kelompok' && tugas?.kelompokManual ? String(tugas.kelompok) : ''
}
const batal = () => { aktif.value = null; galat.value = '' }
const alasanCukup = computed(() => f.alasan.trim().length >= 5)
const siap = computed(() => alasanCukup.value && (aktif.value?.jenis !== 'tambah' || (f.nama.trim().length >= 3 && f.kelompok)) && (aktif.value?.jenis !== 'kelompok' || f.kelompok || aktif.value.tugas?.sumber !== 'manual'))
// Nama tugas SISTER yang belum dipetakan: jadi petunjuk (placeholder) saat menambah tugas baru.
const petunjukNama = computed(() => {
  const n = (d.value?.tugas ?? []).filter((t) => t.sumber === 'sister' && !t.kelompok).map((t) => t.jenis)
  return n.length ? `Tulis seperti di SISTER, mis. ${n[0]}` : 'Nama tugas, mis. Kepala Laboratorium'
})

async function simpan() {
  if (!siap.value || proses.value) return
  proses.value = true
  galat.value = ''
  const a = f.alasan.trim()
  const { jenis, tugas } = aktif.value
  try {
    const r = jenis === 'jabatan' ? await ubahJabatan(props.dosenId, f.jabatan, a, periode())
      : jenis === 'golongan' ? await ubahGolongan(props.dosenId, f.golongan, a, periode())
      : jenis === 'tambah' ? await tambahTugas(props.dosenId, { nama: f.nama.trim(), kelompok: f.kelompok, unit: f.unit, mulai: f.mulai, selesai: f.selesai }, a, periode())
      : jenis === 'kelompok' ? await kelompokTugas(props.dosenId, tugas.id, f.kelompok, a, periode())
      : jenis === 'hapus' ? await hapusTugas(props.dosenId, tugas.id, a, periode())
      : await pulihkanTugas(props.dosenId, tugas.id, a, periode())
    d.value = r.data
    hasil.value = r.hitung
    aktif.value = null
    emit('berubah')
  } catch (e) {
    galat.value = e.message
  }
  proses.value = false
}

const tanggal = (t) => (t.mulai || t.selesai ? `${t.mulai ?? '…'} s/d ${t.selesai ?? 'sekarang'}` : 'tanpa tanggal')
const labelAksi = { jabatan: 'Ubah jabatan fungsional', golongan: 'Ubah golongan', tambah: 'Tambah tugas tambahan', kelompok: 'Tentukan kelompok tugas', hapus: 'Hapus / abaikan tugas', pulihkan: 'Berlakukan kembali tugas' }
</script>

<template>
  <ModalAdmin :buka="tampil" :judul="`Data dosen: ${nama || d?.nama || ''}`" :sub="`Koreksi manual. Perubahan dicatat di log dan honor periode ${honorState.periode} dihitung ulang.`" @close="emit('close')">
    <p v-if="muat">Memuat…</p>
    <p v-else-if="!d" class="galat" role="alert">{{ galat || 'Data dosen tidak ditemukan.' }}</p>
    <template v-else>
      <div v-if="hasil" class="info" role="status">
        <strong>Tersimpan.</strong>
        <template v-if="hasil.dihitungUlang"> Honor periode {{ hasil.periode }} sudah dihitung ulang.</template>
        <template v-if="hasil.verifikasiDireset"> Verifikasi dikembalikan, mohon verifikasi ulang.</template>
        <ul v-if="hasil.catatan?.length"><li v-for="c in hasil.catatan" :key="c">{{ c }}</li></ul>
      </div>

      <section class="blok">
        <div class="baris">
          <div>
            <h3>Jabatan fungsional</h3>
            <p class="nilai"><template v-if="d.jabatan.nama">{{ d.jabatan.nama }}</template><span v-else class="kosong">Belum ditentukan</span>
              <span v-if="d.jabatan.manual" class="tag tag--gray">dikoreksi manual</span></p>
          </div>
          <button class="btn btn--ghost btn--sm" type="button" :disabled="!!aktif" @click="buka('jabatan')">Ubah</button>
        </div>
      </section>

      <section class="blok">
        <div class="baris">
          <div>
            <h3>Golongan</h3>
            <p class="nilai"><template v-if="d.golongan.nilai">Gol {{ d.golongan.nilai }}</template><span v-else class="kosong">Belum ditentukan</span>
              <span v-if="d.golongan.manual" class="tag tag--gray">dikoreksi manual</span>
              <span v-else-if="d.golongan.nilai" class="tag tag--off">turunan dari jabatan</span></p>
          </div>
          <button class="btn btn--ghost btn--sm" type="button" :disabled="!!aktif" @click="buka('golongan')">Ubah</button>
        </div>
      </section>

      <section class="blok">
        <div class="baris">
          <h3>Tugas tambahan</h3>
          <button class="btn btn--ghost btn--sm" type="button" :disabled="!!aktif" @click="buka('tambah')">+ Tambah</button>
        </div>
        <p v-if="!d.tugas.length" class="nilai"><span class="kosong">Belum ada tugas tambahan</span> (dihitung sebagai Dosen Biasa, SKS wajib 10)</p>
        <ul v-else class="tugas">
          <li v-for="t in d.tugas" :key="t.id" :class="{ mati: t.diabaikan }">
            <div>
              <strong>{{ t.jenis }}</strong>
              <span v-if="t.kelompok" class="tag tag--off">kelompok {{ t.kelompok }}<template v-if="t.kelompokManual"> (manual)</template></span>
              <span v-else class="tag tag--bad">kelompok belum ditentukan</span>
              <span class="tag" :class="t.sumber === 'manual' ? 'tag--gray' : ''">{{ t.sumber === 'manual' ? 'manual' : 'SISTER' }}</span>
              <span v-if="t.diabaikan" class="tag tag--bad">diabaikan</span>
              <div class="sub">{{ t.unit ? t.unit + ' · ' : '' }}{{ tanggal(t) }}</div>
              <div v-if="t.diabaikan && t.alasanDiabaikan" class="sub">Alasan diabaikan: {{ t.alasanDiabaikan }}</div>
              <div v-else-if="t.alasan" class="sub">Alasan: {{ t.alasan }}</div>
            </div>
            <div class="aksi-baris">
              <button v-if="t.diabaikan" class="btn btn--ghost btn--sm" type="button" :disabled="!!aktif" @click="buka('pulihkan', t)">Berlakukan</button>
              <template v-else>
                <button class="btn btn--ghost btn--sm" type="button" :disabled="!!aktif" @click="buka('kelompok', t)">{{ t.kelompok ? 'Ubah kelompok' : 'Tentukan kelompok' }}</button>
                <button class="btn btn--ghost btn--sm" type="button" :disabled="!!aktif" @click="buka('hapus', t)">{{ t.sumber === 'manual' ? 'Hapus' : 'Abaikan' }}</button>
              </template>
            </div>
          </li>
        </ul>
      </section>

      <form v-if="aktif" class="form" @submit.prevent="simpan">
        <h3>{{ labelAksi[aktif.jenis] }}<template v-if="aktif.tugas">: {{ aktif.tugas.jenis }}</template></h3>
        <label v-if="aktif.jenis === 'jabatan'">Jabatan fungsional
          <select v-model="f.jabatan"><option value="">Kembali ke data SISTER</option><option v-for="o in d.opsiJabatan" :key="o.id" :value="o.id">{{ o.nama }}</option></select>
        </label>
        <label v-if="aktif.jenis === 'golongan'">Golongan
          <select v-model="f.golongan"><option value="">Turunan dari jabatan</option><option v-for="g in d.opsiGolongan" :key="g" :value="g">Gol {{ g }}</option></select>
        </label>
        <label v-if="aktif.jenis === 'kelompok'">Kelompok tugas
          <select v-model="f.kelompok">
            <option v-if="aktif.tugas.sumber !== 'manual'" value="">Ikut pemetaan jenis tugas</option>
            <option v-else value="" disabled>Pilih kelompok</option>
            <option v-for="k in d.opsiKelompok" :key="k" :value="String(k)">Kelompok {{ k }}</option>
          </select>
        </label>
        <template v-if="aktif.jenis === 'tambah'">
          <label>Nama tugas tambahan
            <input v-model="f.nama" type="text" maxlength="200" required :placeholder="petunjukNama" />
          </label>
          <label>Kelompok
            <select v-model="f.kelompok" required><option value="" disabled>Pilih kelompok</option><option v-for="k in d.opsiKelompok" :key="k" :value="String(k)">Kelompok {{ k }}</option></select>
          </label>
          <label>Unit kerja (opsional)<input v-model="f.unit" type="text" maxlength="200" /></label>
          <div class="dua">
            <label>Mulai (opsional)<input v-model="f.mulai" type="date" /></label>
            <label>Selesai (opsional)<input v-model="f.selesai" type="date" /></label>
          </div>
        </template>
        <p v-if="aktif.jenis === 'hapus'" class="sub">{{ aktif.tugas.sumber === 'manual' ? 'Tugas manual ini akan dihapus.' : 'Tugas dari SISTER ini tidak akan dipakai dalam perhitungan, tetapi datanya tetap ada dan bisa diberlakukan kembali.' }}</p>
        <label>Alasan (wajib, min. 5 huruf)
          <textarea v-model="f.alasan" rows="2" maxlength="500" required placeholder="Mis. SK pengangkatan No. … tanggal …" />
        </label>
        <p v-if="galat" class="galat" role="alert">{{ galat }}</p>
        <div class="aksi">
          <button class="btn" type="submit" :disabled="!siap || proses">{{ proses ? 'Menyimpan…' : 'Simpan' }}</button>
          <button class="btn btn--ghost" type="button" :disabled="proses" @click="batal">Batal</button>
        </div>
      </form>
      <p v-else-if="galat" class="galat" role="alert">{{ galat }}</p>

      <p v-if="d.koreksiTerakhir" class="sub akhir">Koreksi jabatan/golongan terakhir oleh {{ d.koreksiTerakhir.oleh || '-' }}, {{ d.koreksiTerakhir.waktu }}.</p>
    </template>
  </ModalAdmin>
</template>

<style scoped>
h3 { font-size: 17px; margin: 0 0 4px; }
.blok { border: 1px solid var(--color-line); border-radius: 10px; padding: 12px 16px; margin-bottom: 12px; }
.baris { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.nilai { margin: 0; font-size: 17px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.kosong { color: var(--color-muted); font-style: italic; }
.tugas { list-style: none; margin: 8px 0 0; padding: 0; display: grid; gap: 8px; }
.tugas li { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; border-top: 1px solid #d3dedb; padding-top: 8px; }

.aksi-baris { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
.tugas li.mati strong { text-decoration: line-through; color: var(--color-muted); }
.tag { margin-left: 6px; }
.sub { font-size: 15px; color: var(--color-muted); margin-top: 2px; }
.info { background: #e3f4ea; border: 1px solid #9fd3b3; border-radius: 10px; padding: 10px 14px; margin-bottom: 12px; }
.info ul { margin: 6px 0 0; padding-left: 20px; }
.form { background: #f3f7f6; border: 1px solid var(--color-primary); border-radius: 10px; padding: 14px 16px; display: grid; gap: 10px; }
.form label { display: grid; gap: 4px; font-size: 15px; }
.form input, .form select, .form textarea { font: inherit; padding: 8px 12px; border: 1px solid #c3d0cd; border-radius: 6px; background: #fff; }
.dua { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.aksi { display: flex; gap: 10px; flex-wrap: wrap; }
.galat { color: var(--color-error); }
.akhir { margin: 10px 0 0; }
@media (max-width: 560px) { .dua { grid-template-columns: 1fr; } .tugas li, .baris { flex-wrap: wrap; } }
</style>
