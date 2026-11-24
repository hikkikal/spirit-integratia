<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { getDashboard } from '../../services/data.js'
import { honorState, siapkanPeriode } from '../../stores/honor.js'
import { angka, rupiah, persen } from '../../lib/format.js'
import { useBase } from '../../lib/useBase.js'
import PeriodeSelect from '../../components/PeriodeSelect.vue'

const base = useBase()

const jurusan = ref([])
const honorTerverifikasi = ref(0)
const np = ref(null)
const total = ref(null)
const perhatian = ref([])
const galat = ref('')
const jum = (f) => jurusan.value.reduce((a, j) => a + j[f], 0)
const totalDosen = computed(() => jum('dosen'))
const totalSks = computed(() => jum('sksLebih'))
const totalHonor = computed(() => jum('honor'))
const verif = computed(() => jum('terverifikasi'))
const belumHonor = computed(() => totalHonor.value - honorTerverifikasi.value)
// Ringkasan pembayaran: bruto, pajak (sementara), neto untuk pengajaran + non-pengajaran.
const bayar = computed(() => {
  const p = total.value ? { bruto: total.value.honor, pajak: total.value.pajak, netto: total.value.netto } : { bruto: totalHonor.value, pajak: 0, netto: totalHonor.value }
  const n = np.value ? { bruto: np.value.bruto, pajak: np.value.pajak, netto: np.value.netto } : { bruto: 0, pajak: 0, netto: 0 }
  return { p, n, t: { bruto: p.bruto + n.bruto, pajak: p.pajak + n.pajak, netto: p.netto + n.netto } }
})
const butuhCek = computed(() => perhatian.value.filter((x) => !x.info))
const infoSaja = computed(() => perhatian.value.filter((x) => x.info))

async function muat() {
  galat.value = ''
  try {
    await siapkanPeriode()
    const d = await getDashboard(honorState.periode)
    jurusan.value = d.jurusan
    honorTerverifikasi.value = d.honorTerverifikasi
    np.value = d.nonPengajaran || null
    total.value = d.total || null
    perhatian.value = d.perhatian || []
  } catch (e) {
    galat.value = e.message
  }
}
onMounted(muat)
watch(() => honorState.periode, muat)
</script>

<template>
  <div class="dash">
    <div class="head">
      <div>
        <h1 class="page-title">Dashboard Laporan</h1>
        <p class="page-sub">Ringkasan honor SKS lebih · {{ honorState.periode }}</p>
      </div>
      <PeriodeSelect />
    </div>

    <p v-if="galat" class="panel" role="alert">{{ galat }}</p>

    <div class="grid">
      <div class="kiri">
        <section class="duo">
          <div class="panel kartu">
            <h2>Honor pengajaran</h2>
            <div class="lbl">Sudah terverifikasi · {{ verif }} dosen</div>
            <div class="val val--ok">{{ rupiah(honorTerverifikasi) }}</div>
            <div class="lbl">Belum terverifikasi · {{ totalDosen - verif }} dosen</div>
            <div class="val2 val--warn">{{ rupiah(belumHonor) }}</div>
            <div class="row"><span>Progres verifikasi</span><span>{{ verif }}/{{ totalDosen }} ({{ persen(verif, totalDosen) }}%)</span></div>
            <div class="bar"><span :style="{ width: persen(verif, totalDosen) + '%' }"></span></div>
          </div>
          <div v-if="np" class="panel kartu">
            <h2>Honor non-pengajaran</h2>
            <div class="lbl">Sudah terverifikasi · {{ np.terverifikasi }} dosen</div>
            <div class="val val--ok">{{ rupiah(np.honorTerverifikasi) }}</div>
            <div class="lbl">Belum terverifikasi · {{ np.dosen - np.terverifikasi }} dosen</div>
            <div class="val2 val--warn">{{ rupiah(np.honorBelum) }}</div>
            <div class="row"><span>Progres verifikasi</span><span>{{ np.terverifikasi }}/{{ np.dosen }} ({{ persen(np.terverifikasi, np.dosen) }}%)</span></div>
            <div class="bar"><span :style="{ width: persen(np.terverifikasi, np.dosen) + '%' }"></span></div>
          </div>
        </section>

        <section class="panel kartu">
          <h2>Ringkasan pembayaran</h2>
          <div class="scroll-x">
            <table class="tbl tbl--line pad">
              <thead><tr><th></th><th class="num">Bruto</th><th class="num">Pajak</th><th class="num">Neto</th></tr></thead>
              <tbody>
                <tr><td>Pengajaran</td><td class="num">{{ rupiah(bayar.p.bruto) }}</td><td class="num">{{ rupiah(bayar.p.pajak) }}</td><td class="num">{{ rupiah(bayar.p.netto) }}</td></tr>
                <tr><td>Non-pengajaran</td><td class="num">{{ rupiah(bayar.n.bruto) }}</td><td class="num">{{ rupiah(bayar.n.pajak) }}</td><td class="num">{{ rupiah(bayar.n.netto) }}</td></tr>
                <tr class="total"><td>Total</td><td class="num">{{ rupiah(bayar.t.bruto) }}</td><td class="num">{{ rupiah(bayar.t.pajak) }}</td><td class="num">{{ rupiah(bayar.t.netto) }}</td></tr>
              </tbody>
            </table>
          </div>
          <p class="cat">Pajak sementara: golongan III 5%, golongan IV 15% (belum dikonfirmasi).</p>
        </section>
      </div>

      <section class="panel kartu perhatian">
        <h2>Perlu perhatian <span v-if="butuhCek.length" class="hitung">{{ butuhCek.length }}</span></h2>
        <p v-if="!butuhCek.length && !infoSaja.length" class="ok">Tidak ada data yang ditandai pada periode ini.</p>
        <ul class="cek">
          <li v-for="x in butuhCek" :key="x.kunci">
            <RouterLink :to="{ path: `${base}/${x.tujuan}`, query: { cek: x.kunci } }"><strong>{{ x.jumlah }}</strong><span>{{ x.label }}</span></RouterLink>
          </li>
          <li v-for="x in infoSaja" :key="x.kunci" class="info">
            <RouterLink :to="{ path: `${base}/${x.tujuan}`, query: { cek: x.kunci } }"><strong>{{ x.jumlah }}</strong><span>{{ x.label }}</span></RouterLink>
          </li>
        </ul>
        <p class="cat">Angka = jumlah dosen. Klik untuk membuka daftar yang ditandai.</p>
      </section>
    </div>

    <section class="panel kartu rekap">
      <h2>Rekap per jurusan</h2>
      <div class="scroll-x rekap-isi">
        <table class="tbl tbl--line pad">
          <thead><tr><th>Jurusan</th><th class="num">Dosen</th><th class="vcol">Progres verifikasi</th><th class="num">SKS lebih</th><th class="num">Pengeluaran honor</th></tr></thead>
          <tbody>
            <tr v-for="j in jurusan" :key="j.nama">
              <td>{{ j.nama }}</td>
              <td class="num">{{ j.dosen }}</td>
              <td class="vcol"><div class="mini"><div class="bar"><span :style="{ width: persen(j.terverifikasi, j.dosen) + '%' }"></span></div><span class="mt">{{ j.terverifikasi }}/{{ j.dosen }} · {{ persen(j.terverifikasi, j.dosen) }}%</span></div></td>
              <td class="num"><span class="sks-badge">{{ j.sksLebih }}</span></td>
              <td class="num">{{ rupiah(j.honor) }}</td>
            </tr>
            <tr class="total"><td>Total</td><td class="num">{{ angka(totalDosen) }}</td><td class="vcol"><span class="mt">{{ verif }}/{{ totalDosen }} · {{ persen(verif, totalDosen) }}%</span></td><td class="num">{{ angka(totalSks) }}</td><td class="num">{{ rupiah(totalHonor) }}</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<style scoped>
.dash { display: grid; gap: 16px; }
.head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
.dash .page-title { font-size: 28px; margin: 0 0 2px; }
.dash .page-sub { margin: 0; }
.grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr); gap: 16px; align-items: stretch; }
.kiri { display: grid; gap: 16px; align-content: start; }
.duo { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.dash .kartu { padding: 16px 20px; }
.dash h2 { font-size: 17px; margin: 0 0 10px; display: flex; align-items: center; gap: 8px; }
.lbl { font-size: 15px; color: var(--color-muted); }
.val { font-size: 26px; font-weight: 600; margin: 0 0 8px; line-height: 1.2; }
.val2 { font-size: 20px; font-weight: 600; margin: 0 0 10px; line-height: 1.2; }
.val--ok { color: var(--color-primary); }
.val--warn { color: #b25e00; }
.row { display: flex; justify-content: space-between; gap: 12px; font-size: 15px; }
.bar { height: 10px; background: #dfe9e7; border-radius: 99px; overflow: hidden; margin-top: 4px; }
.bar span { display: block; height: 100%; background: #0e8277; border-radius: 99px; }
.pad th, .pad td { padding: 7px 8px; }
.total td { font-weight: 700; border-bottom: 0; }
.cat { font-size: 14px; color: var(--color-muted); margin: 10px 0 0; }
.ok { color: var(--color-primary); margin: 0; }
.perhatian { display: flex; flex-direction: column; min-height: 0; }
.hitung { background: #f2b705; color: #3a2a00; border-radius: 99px; font-size: 14px; padding: 0 9px; line-height: 22px; }
.cek { list-style: none; padding: 0; margin: 0; display: grid; gap: 6px; align-content: start; overflow: auto; max-height: 330px; }
.cek a { display: grid; grid-template-columns: 44px 1fr; align-items: center; gap: 10px; padding: 7px 10px; border: 1px solid #d9a441; background: #fff8e6; border-radius: 8px; color: var(--color-ink); text-decoration: none; font-size: 15px; line-height: 1.3; }
.cek a:hover { background: #fff0c7; }
.cek strong { text-align: center; background: #f2b705; color: #3a2a00; border-radius: 6px; padding: 2px 0; }
.cek .info a { border-color: #b9c7c4; background: #f1f5f4; }
.cek .info strong { background: #cfd9d7; color: var(--color-ink); }
.vcol { width: 32%; }
.mini { display: flex; align-items: center; gap: 10px; }
.mini .bar { flex: 1; margin: 0; }
.mt { font-size: 14px; color: var(--color-muted); white-space: nowrap; }
/* Layar lebar dan cukup tinggi: seluruh dashboard pas satu layar, tanpa scroll halaman.
   Bagian yang kepanjangan (daftar perhatian, rekap jurusan) bergulir di dalam kartunya sendiri. */
@media (min-width: 1100px) and (min-height: 900px) {
  .dash { height: calc(100dvh - 80px); grid-template-rows: auto auto minmax(0, 1fr); }
  .head { grid-row: 1; }
  .grid { grid-row: 2; }
  .perhatian { contain: size; min-height: 0; overflow: hidden; }
  .perhatian .cek { flex: 1; max-height: none; min-height: 0; }
  .rekap { grid-row: 3; display: flex; flex-direction: column; min-height: 0; }
  .rekap-isi { flex: 1; min-height: 0; overflow: auto; }
  .rekap-isi thead th { position: sticky; top: 0; background: #fff; z-index: 1; }
}
@media (max-width: 1100px) { .grid, .duo { grid-template-columns: 1fr; } .cek { max-height: none; } }
</style>
