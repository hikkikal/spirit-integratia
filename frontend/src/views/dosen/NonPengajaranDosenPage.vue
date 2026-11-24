<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { honorState, siapkanPeriode } from '../../stores/honor.js'
import { getNpDetail } from '../../services/nonpengajaran.js'
import { rupiah } from '../../lib/format.js'
import PeriodeSelect from '../../components/PeriodeSelect.vue'
import DetailKegiatanIsi from '../../components/DetailKegiatanIsi.vue'

// Aktivitas non-pengajaran milik dosen yang sedang login (hanya-baca), mengikuti Figma "Aktivitas Non-Pengajaran":
// kartu ringkasan, tabel kegiatan, dan panel "Detail kegiatan" untuk baris yang dipilih.
// Rincian satu kegiatan memakai popup yang sama dengan halaman Keuangan.
const detail = ref(null)
const selesai = ref(false)
const galat = ref('')
const pilih = ref(null)

async function muat() {
  galat.value = ''
  try {
    await siapkanPeriode()
    detail.value = await getNpDetail(null, honorState.periode)
  } catch (e) {
    detail.value = null
    galat.value = e.message
  }
  pilih.value = detail.value?.kegiatan?.[0] ?? null
  selesai.value = true
}
onMounted(muat)
watch(() => honorState.periode, muat)

const kegiatan = computed(() => detail.value?.kegiatan ?? [])
const sudah = computed(() => detail.value?.status === 'terverifikasi')
</script>

<template>
  <div class="head">
    <div>
      <h1 class="page-title">Aktivitas Non-Pengajaran</h1>
      <p class="page-sub">Pembimbingan dan kegiatan di luar kelas pada periode terpilih · Sumber: SISTER</p>
    </div>
    <PeriodeSelect />
  </div>

  <p v-if="!selesai" class="panel">Memuat…</p>
  <p v-else-if="!detail" class="panel" role="alert">{{ galat || 'Belum ada kegiatan non-pengajaran untuk Anda pada periode ini.' }}</p>
  <template v-else>
    <section class="ringkas">
      <div class="kartu"><span>Total Kegiatan</span><strong>{{ detail.totalKegiatan }}</strong></div>
      <div class="kartu"><span>Total Honor (Bruto)</span><strong>{{ rupiah(detail.totalHonor) }}</strong></div>
      <div class="kartu"><span>Status verifikasi</span><strong :class="sudah ? 'ok' : 'tunggu'">{{ sudah ? 'Terverifikasi' : 'Menunggu Verifikasi' }}</strong></div>
    </section>

    <div class="dua">
      <section class="panel tabel">
        <div class="scroll-x">
          <table class="tbl tbl--line">
            <thead>
              <tr><th>No</th><th>Judul</th><th>Jenis Kegiatan</th><th>Kategori kegiatan</th><th>Program studi</th><th>Status BKD</th><th class="r">Honor bruto</th></tr>
            </thead>
            <tbody>
              <tr v-for="k in kegiatan" :key="k.no" class="klik" :class="{ pilih: pilih && pilih.no === k.no }" tabindex="0" :aria-label="`Lihat rincian: ${k.judul}`" @click="pilih = k" @keydown.enter="pilih = k">
                <td>{{ k.no }}</td><td>{{ k.judul }}</td>
                <td><span class="np-tag">{{ k.jenis }}</span></td>
                <td>{{ k.kategori }}</td><td>{{ k.prodi || '-' }}</td>
                <td><span v-if="k.statBkd" class="stat" :class="{ 'stat--lebih': k.statBkd === 'Beban lebih' }">{{ k.statBkd }}</span><span v-else class="kosong" title="Tidak ditemukan di BKD SISTER">–</span></td>
                <td class="r">{{ rupiah(k.honor) }}</td>
              </tr>
              <tr v-if="!kegiatan.length"><td colspan="7" class="kosong">Belum ada kegiatan pada periode ini.</td></tr>
            </tbody>
          </table>
        </div>
        <p class="total">Total : {{ detail.totalKegiatan }} Kegiatan</p>
      </section>

      <aside v-if="pilih" class="panel samping" aria-live="polite">
        <h2>Detail Kegiatan</h2>
        <p class="samping__sub">{{ detail.nama }} • {{ honorState.periode }}</p>
        <DetailKegiatanIsi :kegiatan="pilih" :periode="honorState.periode" />
        <p class="cat2">Data pembimbingan diambil dari SISTER dan hanya dapat dikoreksi oleh Admin Input. Laporan Anda dicatat pada audit log.</p>
      </aside>
    </div>
  </template>
</template>

<style scoped>
.head { display: flex; justify-content: space-between; gap: 16px 24px; flex-wrap: wrap; align-items: flex-start; }
.ringkas { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
.kartu { background: #ffffff; border: 1px solid #a9bcb8; border-radius: 10px; padding: 12px 16px; display: grid; gap: 6px; min-width: 250px; font-size: 15px; color: #4a5755; }
.kartu strong { font-size: 20px; font-weight: 500; color: #000; }
.kartu strong.ok { color: #0d8177; font-weight: 700; }
.kartu strong.tunggu, dd.tunggu { color: #8a4b00; }
.dua { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 20px; align-items: start; }
.tabel { padding: 10px 14px; border-radius: 14px; }
.tbl :is(th, td) { font-size: 15px; padding: 14px 10px; }
.np-tag { display: inline-block; font-size: 15px; border: 1px solid #a9bcb8; border-radius: 999px; padding: 3px 14px; background: #fff; }
.r { text-align: right; }
.klik { cursor: pointer; }
.klik:hover, .klik:focus-visible, .klik.pilih { background: #e8f1ef; outline: none; }
.total { font-weight: 600; font-size: 15px; margin: 12px 4px 4px; }
.kosong { text-align: center; color: #4a5755; }
.stat { display: inline-block; font-size: 15px; border: 1px solid #6b7a77; border-radius: 999px; padding: 2px 10px; background: #fff; color: #3f4c4a; white-space: nowrap; }
.stat--lebih { border-color: var(--color-primary); background: var(--color-primary); color: #fff; }
.samping { padding: 18px 20px; position: sticky; top: 12px; max-height: calc(100vh - 24px); overflow-y: auto; }
.samping h2 { font-size: 22px; margin: 0; }
.samping__sub { color: #4a5755; font-size: 15px; margin: 2px 0 12px; }
.cat2 { color: #5a6866; font-size: 15px; margin: 16px 0 0; }
@media (max-width: 900px) { .dua { grid-template-columns: 1fr; } .samping { position: static; max-height: none; } .kartu { min-width: 0; flex: 1 1 100%; } }
</style>
