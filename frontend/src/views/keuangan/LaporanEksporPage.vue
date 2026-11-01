<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { getDashboard } from '../../services/data.js'
import { honorState, catatEkspor, kunciPeriode, siapkanPeriode } from '../../stores/honor.js'
import { auth } from '../../stores/auth.js'
import { angka, rupiah } from '../../lib/format.js'

// Pimpinan hanya melihat rekap. Ekspor, kunci periode, dan riwayat ekspor hanya untuk Bidang Keuangan.
// Backend tetap harus menolak ekspor dan kunci dari peran lain.
const bisaEkspor = computed(() => auth.user?.role === 'keuangan')
const filterJurusan = ref('')
const filterStatus = ref('')
const terkunci = computed(() => !!honorState.terkunci[honorState.periode])

const laporanJurusan = ref([])
const galat = ref('')
async function muat() {
  galat.value = ''
  try {
    await siapkanPeriode()
    laporanJurusan.value = (await getDashboard(honorState.periode)).laporan
  } catch (e) {
    galat.value = e.message
  }
}
onMounted(muat)
watch(() => honorState.periode, muat)

const baris = computed(() =>
  laporanJurusan.value.filter(
    (j) =>
      (!filterJurusan.value || j.nama === filterJurusan.value) &&
      (!filterStatus.value || (filterStatus.value === 'terverifikasi' ? j.terverifikasi : j.menunggu) > 0),
  ),
)
const jml = (f) => baris.value.reduce((a, j) => a + j[f], 0)
const totalMenunggu = computed(() => laporanJurusan.value.reduce((a, j) => a + j.menunggu, 0))

// TODO(backend): unduh file Excel/PDF sungguhan dari API. Sekarang hanya mencatat riwayat.
function ekspor(format) {
  catatEkspor(format, auth.user?.name ?? 'Keuangan')
}
</script>

<template>
  <h1 class="page-title">{{ bisaEkspor ? 'Laporan & Ekspor' : 'Laporan' }}</h1>
  <p class="page-sub">Rekap honor per jurusan · {{ honorState.periode }}</p>

  <p v-if="galat" class="panel" role="alert">{{ galat }}</p>
  <div class="filters">
    <label>Periode
      <select v-model="honorState.periode"><option v-for="p in honorState.periodeOptions" :key="p">{{ p }}</option></select>
    </label>
    <label>Jurusan
      <select v-model="filterJurusan"><option value="">Semua jurusan</option><option v-for="j in laporanJurusan" :key="j.nama">{{ j.nama }}</option></select>
    </label>
    <label>Status verifikasi
      <select v-model="filterStatus"><option value="">Semua</option><option value="terverifikasi">Terverifikasi</option><option value="menunggu">Menunggu</option></select>
    </label>
  </div>

  <div class="cols" :class="{ solo: !bisaEkspor }">
    <section class="panel">
      <h2>Pratinjau laporan</h2>
      <div class="scroll-x">
        <table class="tbl tbl--line">
          <thead><tr><th>Jurusan</th><th>Dosen</th><th>SKS lebih</th><th>Honor bruto</th><th>Terverifikasi</th><th>Menunggu</th></tr></thead>
          <tbody>
            <tr v-for="j in baris" :key="j.nama">
              <td>{{ j.nama }}</td><td>{{ j.dosen }}</td><td>{{ j.sksLebih }}</td><td>{{ rupiah(j.honor) }}</td><td>{{ j.terverifikasi }}</td><td>{{ j.menunggu }}</td>
            </tr>
            <tr v-if="!baris.length"><td colspan="6" class="kosong">Tidak ada data yang cocok.</td></tr>
          </tbody>
          <tfoot>
            <tr><td>Total</td><td>{{ jml('dosen') }}</td><td>{{ angka(jml('sksLebih')) }}</td><td>{{ rupiah(jml('honor')) }}</td><td>{{ jml('terverifikasi') }}</td><td>{{ jml('menunggu') }}</td></tr>
          </tfoot>
        </table>
      </div>
    </section>

    <aside v-if="bisaEkspor" class="side">
      <section class="kartu">
        <h3>Ekspor laporan</h3>
        <p class="muted">Hanya data berstatus terverifikasi yang diekspor (opsional: sertakan yang menunggu).</p>
        <div class="row">
          <button class="btn" @click="ekspor('Excel')">Ekspor Excel</button>
          <button class="btn btn--ghost" @click="ekspor('PDF')">Ekspor PDF</button>
        </div>
      </section>
      <section class="kartu">
        <h3>Kunci periode</h3>
        <span v-if="totalMenunggu" class="warn">{{ totalMenunggu }} data belum terverifikasi</span>
        <p class="muted">Periode yang dikunci tidak dapat dihitung ulang. Perubahan setelahnya dicatat sebagai revisi.</p>
        <button class="btn btn--ghost" :disabled="terkunci || totalMenunggu > 0" @click="kunciPeriode(honorState.periode)">
          {{ terkunci ? 'Periode terkunci' : `Kunci Periode ${honorState.periode}` }}
        </button>
      </section>
      <section class="kartu">
        <h3>Riwayat ekspor</h3>
        <p v-for="(r, i) in honorState.riwayat" :key="i" class="hist">{{ r }}</p>
      </section>
    </aside>
  </div>
</template>

<style scoped>
.filters { display: flex; gap: 20px; flex-wrap: wrap; margin: 0 0 20px; }
.filters label { display: grid; gap: 4px; font-size: 14px; }
.filters select { font: inherit; font-size: 14px; padding: 8px 12px; border: 0; border-radius: 4px; background: #d9d9d9; min-width: 180px; }
.cols { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 16px; align-items: start; }
h2 { font-size: 17px; margin: 0 0 6px; }
.tbl { font-size: 14px; }
.tbl tfoot td { font-weight: 700; border-bottom: 1px solid #c9c9c2; padding: 10px 6px; }
.tbl tbody td { padding: 10px 6px; }
.kosong { text-align: center; color: #77776f; }
.side { display: grid; gap: 16px; }
.kartu { background: #fff; border: 1px solid #000; border-radius: 14px; padding: 14px 16px; }
.kartu h3 { margin: 0 0 8px; font-size: 16px; }
.muted { color: #77776f; font-size: 13px; margin: 6px 0 12px; }
.row { display: flex; gap: 10px; flex-wrap: wrap; }
.warn { display: inline-block; background: #fdecc8; color: #8a4b00; border-radius: 6px; padding: 2px 8px; font-size: 13px; }
.hist { margin: 6px 0 0; font-size: 13px; }
.btn { padding: 8px 14px; font-size: 14px; }
.cols.solo { grid-template-columns: minmax(0, 1fr); }
@media (max-width: 1000px) { .cols { grid-template-columns: 1fr; } }
</style>
