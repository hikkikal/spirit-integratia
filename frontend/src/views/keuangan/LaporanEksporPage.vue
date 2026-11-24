<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { getDashboard } from '../../services/data.js'
import { JENIS, getLaporan, getRiwayatEkspor, unduhLaporan } from '../../services/laporan.js'
import { honorState, kunciPeriode, siapkanPeriode } from '../../stores/honor.js'
import { auth } from '../../stores/auth.js'
import { angka, rupiah } from '../../lib/format.js'
import { useTabel, teks, angka as bandingAngka } from '../../lib/useTabel.js'
import ThUrut from '../../components/ThUrut.vue'
import Pager from '../../components/Pager.vue'
import { tanya } from '../../lib/konfirmasi.js'

// Pimpinan hanya melihat laporan. Ekspor, kunci periode, dan riwayat ekspor hanya untuk Bidang Keuangan
// (backend juga menolak ekspor dari peran lain).
const bisaEkspor = computed(() => auth.user?.role === 'keuangan')
const jenis = ref('gabungan')
const filterJurusan = ref('')
const filterStatus = ref('terverifikasi') // bawaan: hanya yang sudah terverifikasi
const terkunci = computed(() => !!honorState.terkunci[honorState.periode])

const jurusanOpsi = ref([])
const hasil = ref({ kolom: [], baris: [], total: {} })
const riwayat = ref([])
const memuat = ref(false)
const galat = ref('')
const info = ref('')
const mengunduh = ref('')
const menunggu = ref(0)

async function muat() {
  galat.value = ''
  memuat.value = true
  try {
    await siapkanPeriode()
    const p = honorState.periode
    const [h, d] = await Promise.all([getLaporan({ periode: p, jenis: jenis.value, jurusan: filterJurusan.value, status: filterStatus.value }), getDashboard(p)])
    hasil.value = h
    jurusanOpsi.value = d.laporan.map((j) => j.nama)
    menunggu.value = d.laporan.reduce((a, j) => a + j.menunggu, 0)
    if (bisaEkspor.value) riwayat.value = await getRiwayatEkspor()
  } catch (e) {
    galat.value = e.message
  } finally {
    memuat.value = false
  }
}
onMounted(muat)
watch([() => honorState.periode, jenis, filterJurusan, filterStatus], muat)

const kolom = computed(() => hasil.value.kolom)
const tabel = useTabel(
  computed(() => hasil.value.baris),
  { nama: teks((r) => r.nama), bruto: bandingAngka((r) => r.bruto), netto: bandingAngka((r) => r.netto) },
)
const bisaUrut = (k) => ['nama', 'bruto', 'netto'].includes(k)
const tampil = (r, k) => {
  const v = r[k.k]
  if (v === null || v === undefined || v === '') return '–'
  if (k.tipe === 'uang') return rupiah(v)
  if (k.tipe === 'desimal' || k.tipe === 'angka') return angka(v)
  return v
}
const kanan = (k) => k.tipe !== 'teks'

async function ekspor(format) {
  galat.value = ''
  info.value = ''
  if (!hasil.value.baris.length && !(await tanya({ judul: 'Berkas akan kosong', isi: 'Tidak ada baris pada filter ini. Tetap unduh?', ya: 'Tetap unduh' }))) return
  mengunduh.value = format
  try {
    await unduhLaporan(format, { periode: honorState.periode, jenis: jenis.value, jurusan: filterJurusan.value, status: filterStatus.value })
    info.value = `Berkas ${format.toUpperCase()} diunduh (${hasil.value.baris.length} dosen).`
    riwayat.value = await getRiwayatEkspor()
  } catch (e) {
    galat.value = e.message
  } finally {
    mengunduh.value = ''
  }
}
</script>

<template>
  <h1 class="page-title">{{ bisaEkspor ? 'Laporan & Ekspor' : 'Laporan' }}</h1>
  <p class="page-sub">Honor kelebihan SKS per dosen · {{ honorState.periode }}</p>

  <div class="tabs" role="tablist" aria-label="Jenis laporan">
    <button v-for="j in JENIS" :key="j.kode" type="button" role="tab" class="tab" :class="{ 'tab--on': jenis === j.kode }" :aria-selected="jenis === j.kode" @click="jenis = j.kode">{{ j.label }}</button>
  </div>

  <p v-if="galat" class="panel" role="alert">{{ galat }}</p>
  <p v-if="info" class="ok" role="status">{{ info }}</p>
  <div class="filters">
    <label>Periode
      <select v-model="honorState.periode"><option v-for="p in honorState.periodeOptions" :key="p">{{ p }}</option></select>
    </label>
    <label>Jurusan
      <select v-model="filterJurusan"><option value="">Semua jurusan</option><option v-for="j in jurusanOpsi" :key="j">{{ j }}</option></select>
    </label>
    <label>Status verifikasi
      <select v-model="filterStatus"><option value="terverifikasi">Terverifikasi</option><option value="menunggu">Menunggu</option><option value="semua">Semua</option></select>
    </label>
  </div>

  <div class="cols" :class="{ solo: !bisaEkspor }">
    <section class="panel">
      <h2>Pratinjau · {{ JENIS.find((j) => j.kode === jenis).label }}</h2>
      <p v-if="jenis === 'gabungan'" class="muted">Gabungan menjumlahkan honor pengajaran dan non-pengajaran per dosen. Dosen dianggap terverifikasi bila setiap bagiannya sudah terverifikasi.</p>
      <div class="scroll-x">
        <table class="tbl tbl--line" :aria-busy="memuat">
          <thead>
            <tr>
              <template v-for="k in kolom" :key="k.k">
                <th v-if="k.k === 'no'">No</th>
                <ThUrut v-else-if="bisaUrut(k.k)" :tabel="tabel" :kunci="k.k" :class="{ r: kanan(k) }">{{ k.judul }}</ThUrut>
                <th v-else :class="{ r: kanan(k) }">{{ k.judul }}</th>
              </template>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(r, i) in tabel.baris.value" :key="r.nama + i">
              <td v-for="k in kolom" :key="k.k" :class="[{ r: kanan(k) }, 'c-' + k.k]" :title="k.k === 'jurusan' ? r.jurusan : null"><span v-if="k.k === 'sks_lebih'" class="sks-badge" :class="{ 'sks-badge--nol': !r.sks_lebih }">{{ tampil(r, k) }}</span><template v-else>{{ k.k === 'no' ? tabel.dari.value + i : tampil(r, k) }}</template></td>
            </tr>
            <tr v-if="!tabel.total.value"><td :colspan="kolom.length" class="kosong">{{ memuat ? 'Memuat…' : 'Tidak ada data yang cocok dengan filter ini.' }}</td></tr>
          </tbody>
          <tfoot v-if="tabel.total.value">
            <tr><td v-for="k in kolom" :key="k.k" :class="{ r: kanan(k) }">{{ k.k === 'no' ? '' : k.k === 'nama' ? hasil.total.nama : k.tipe === 'teks' ? '' : tampil(hasil.total, k) }}</td></tr>
          </tfoot>
        </table>
      </div>
      <Pager :tabel="tabel" />
    </section>

    <aside v-if="bisaEkspor" class="side">
      <section class="kartu">
        <h3>Ekspor laporan</h3>
        <p class="muted">Mengekspor tab, periode, jurusan, dan status yang sedang dipilih ({{ hasil.baris.length }} dosen). Bawaannya hanya yang terverifikasi.</p>
        <div class="row">
          <button class="btn" :disabled="!!mengunduh || memuat" @click="ekspor('xlsx')">{{ mengunduh === 'xlsx' ? 'Menyiapkan…' : 'Ekspor Excel' }}</button>
          <button class="btn btn--ghost" :disabled="!!mengunduh || memuat" @click="ekspor('pdf')">{{ mengunduh === 'pdf' ? 'Menyiapkan…' : 'Ekspor PDF' }}</button>
        </div>
      </section>
      <section class="kartu">
        <h3>Kunci periode</h3>
        <span v-if="menunggu" class="warn">{{ menunggu }} data belum terverifikasi</span>
        <p class="muted">Periode yang dikunci tidak dapat dihitung ulang. Perubahan setelahnya dicatat sebagai revisi.</p>
        <button class="btn btn--ghost" :disabled="terkunci || menunggu > 0" @click="kunciPeriode(honorState.periode)">
          {{ terkunci ? 'Periode terkunci' : `Kunci Periode ${honorState.periode}` }}
        </button>
      </section>
      <section class="kartu">
        <h3>Riwayat ekspor</h3>
        <p v-for="(r, i) in riwayat" :key="i" class="hist"><b>{{ r.waktu }}</b> · {{ r.oleh }}<br>{{ r.objek }}</p>
        <p v-if="!riwayat.length" class="muted">Belum ada ekspor.</p>
      </section>
    </aside>
  </div>
</template>

<style scoped>
.tabs { display: flex; gap: 8px; flex-wrap: wrap; margin: 0 0 16px; }
.tab { font: inherit; font-size: 15px; padding: 7px 18px; border-radius: 999px; border: 1px solid var(--color-primary); background: #fff; color: var(--color-primary); cursor: pointer; }
.tab--on { background: var(--color-primary); color: #fff; }
.ok { background: #d9f7e4; color: #0a5928; border-radius: 6px; padding: 8px 12px; margin: 0 0 12px; font-size: 15px; }
.filters { display: flex; gap: 20px; flex-wrap: wrap; margin: 0 0 20px; }
.filters label { display: grid; gap: 4px; font-size: 15px; }
.filters select { font: inherit; font-size: 15px; padding: 8px 12px; border: 0; border-radius: 4px; background: #e4ecea; min-width: 180px; max-width: 100%; }
.cols { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 16px; align-items: start; }
h2 { font-size: 17px; margin: 0 0 6px; }
.tbl { font-size: 15px; }
.tbl .r { text-align: right; }
.tbl tfoot td { font-weight: 700; border-bottom: 1px solid #c3d0cd; padding: 10px 6px; }
.tbl tbody td { padding: 8px 6px; white-space: nowrap; }
.tbl .c-jurusan { max-width: 150px; overflow: hidden; text-overflow: ellipsis; }
.tbl th { white-space: nowrap; }
.tbl tfoot td { white-space: nowrap; }
.kosong { text-align: center; color: #4a5755; }
.side { display: grid; gap: 16px; }
.kartu { background: #fff; border: 1px solid #a9bcb8; border-radius: 14px; padding: 14px 16px; }
.kartu h3 { margin: 0 0 8px; font-size: 16px; }
.muted { color: #4a5755; font-size: 15px; margin: 6px 0 12px; }
.row { display: flex; gap: 10px; flex-wrap: wrap; }
.warn { display: inline-block; background: #fdecc8; color: #8a4b00; border-radius: 6px; padding: 2px 8px; font-size: 15px; }
.hist { margin: 8px 0 0; font-size: 15px; }
.btn { padding: 8px 14px; font-size: 15px; }
.cols.solo { grid-template-columns: minmax(0, 1fr); }
@media (max-width: 1000px) { .cols { grid-template-columns: 1fr; } }
</style>
