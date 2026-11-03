<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { getKoneksi, getRiwayatSinkron, mulaiSinkron, cekSinkron } from '../../services/admin.js'
import { useBase } from '../../lib/useBase.js'

const base = useBase()

// Empat langkah sesuai desain.
const LANGKAH = [
  { judul: 'Mengirim request data ke SISTER melalui API', ket: 'Sistem mengirim permintaan data beban kerja dosen', tag: 'Integratia', warna: 'hijau' },
  { judul: 'SISTER menerima dan memproses request', ket: 'Menerima dan menyiapkan data', tag: 'SISTER', warna: 'biru' },
  { judul: 'Menerima data SISTER', ket: 'Data dosen, kelas, penugasan mengajar diterima', tag: 'Integratia', warna: 'hijau' },
  { judul: 'Menyimpan hasil sinkronisasi', ket: 'Data ditambahkan ke database Integratia', tag: 'Database', warna: 'abu' },
]
const NAMA_STATUS = { menunggu: 'Menunggu', berjalan: 'Berjalan…', selesai: 'Selesai', gagal: 'Gagal' }

const koneksi = ref(null)
const riwayat = ref([])
const galat = ref('')
const status = ref('siap') // siap | berjalan | berhasil | gagal
const persen = ref(0)
const langkah = ref(['menunggu', 'menunggu', 'menunggu', 'menunggu'])
const logId = ref(null)
let timer = null

const labelStatus = computed(() => ({ siap: 'Siap', berjalan: 'Berjalan', berhasil: 'Berhasil', gagal: 'Gagal' })[status.value])

async function muat() {
  try {
    ;[koneksi.value, riwayat.value] = await Promise.all([getKoneksi(), getRiwayatSinkron()])
  } catch (e) {
    galat.value = e.message
  }
}
onMounted(muat)
onUnmounted(() => clearTimeout(timer))

async function jalankan(e) {
  if (status.value === 'berjalan') return
  galat.value = ''
  persen.value = 0
  logId.value = null
  langkah.value = ['berjalan', 'menunggu', 'menunggu', 'menunggu']
  status.value = 'berjalan'
  try {
    // Shift + klik: simulasi gagal (hanya mode tiruan) untuk melihat tampilan kegagalan.
    const { id } = await mulaiSinkron({ simulasiGagal: !!e?.shiftKey })
    const putaran = async () => {
      const s = await cekSinkron(id)
      persen.value = s.persen
      langkah.value = s.langkah
      if (s.status === 'berjalan') {
        timer = setTimeout(putaran, 400)
        return
      }
      status.value = s.status
      logId.value = s.logId
      await muat()
    }
    await putaran()
  } catch (err) {
    status.value = 'gagal'
    galat.value = err.message || 'Sinkronisasi gagal dimulai.'
  }
}
</script>

<template>
  <div class="head">
    <div>
      <h1 class="page-title">Sinkronisasi Data SISTER</h1>
      <p class="page-sub">Ambil data beban kerja dosen dari SISTER melalui API</p>
    </div>
    <button class="btn sinkron" :disabled="status === 'berjalan'" @click="jalankan">
      <svg viewBox="0 0 24 24" aria-hidden="true" :class="{ putar: status === 'berjalan' }"><path d="M20 12a8 8 0 1 1-2.6-5.9M20 4v5h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
      {{ status === 'berjalan' ? 'Menyinkronkan…' : 'Sinkronisasi Data' }}
    </button>
  </div>
  <p v-if="galat" class="galat" role="alert">{{ galat }}</p>

  <div class="kolom">
    <section class="panel proses">
      <div class="proses__head">
        <h2><span class="bar-aksen"></span>Proses Sinkronisasi</h2>
        <span class="pill" :class="`pill--${status}`">{{ labelStatus }}</span>
      </div>
      <div class="persen" role="progressbar" :aria-valuenow="persen" aria-valuemin="0" aria-valuemax="100">
        <span class="persen__isi" :class="{ 'persen__isi--gagal': status === 'gagal' }" :style="{ width: persen + '%' }"></span>
      </div>
      <div class="persen__angka">{{ persen }}%</div>

      <ol class="langkah">
        <li v-for="(l, i) in LANGKAH" :key="i" class="item" :class="`item--${langkah[i]}`">
          <span class="no">{{ i + 1 }}</span>
          <div class="txt"><strong>{{ l.judul }}</strong><span>{{ l.ket }}</span></div>
          <span class="tag" :class="`tag--${l.warna}`">{{ l.tag }}</span>
          <span class="st">{{ NAMA_STATUS[langkah[i]] }}</span>
        </li>
      </ol>

      <p v-if="status === 'berhasil'" class="hasil hasil--ok">Sinkronisasi selesai. <RouterLink v-if="logId" :to="`${base}/dashboard/log/${logId}`">Lihat log aktivitas</RouterLink></p>
      <p v-if="status === 'gagal' && logId" class="hasil hasil--gagal">Sinkronisasi berhenti. <RouterLink :to="`${base}/dashboard/log/${logId}`">Lihat log aktivitas</RouterLink></p>
    </section>

    <aside class="samping">
      <section class="panel">
        <div class="kop"><h2>Koneksi Sister</h2><span v-if="koneksi" class="pill" :class="koneksi.status === 'Terhubung' ? 'pill--berhasil' : 'pill--gagal'">{{ koneksi.status }}</span></div>
        <dl v-if="koneksi" class="info">
          <dt>Layanan</dt><dd>{{ koneksi.layanan }}</dd>
          <dt>Metode</dt><dd>{{ koneksi.metode }}</dd>
          <dt>Terakhir</dt><dd>{{ koneksi.terakhir }}</dd>
          <dt>Dijalankan</dt><dd><strong>{{ koneksi.dijalankan }}</strong></dd>
        </dl>
      </section>
      <section class="panel">
        <h2>Riwayat Sinkronasi</h2>
        <p v-if="!riwayat.length" class="kosong">Belum ada sinkronisasi.</p>
        <ul class="riwayat">
          <li v-for="r in riwayat.slice(0, 8)" :key="r.id">
            <RouterLink :to="`${base}/dashboard/log/${r.id}`" class="baris">
              <span>{{ r.mulai }}</span>
              <span class="pill" :class="r.status === 'berhasil' ? 'pill--berhasil' : 'pill--gagal'">{{ r.status === 'berhasil' ? 'Berhasil' : 'Gagal' }}</span>
            </RouterLink>
          </li>
        </ul>
      </section>
    </aside>
  </div>
</template>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 8px; }
.head .page-sub { margin-bottom: 16px; }
.sinkron { display: inline-flex; align-items: center; gap: 10px; background: #0d8177; padding: 14px 22px; font-weight: 600; }
.sinkron svg { width: 22px; height: 22px; }
.putar { animation: putar 1s linear infinite; }
@keyframes putar { to { transform: rotate(360deg); } }
.galat { color: var(--color-error); }
.kolom { display: grid; grid-template-columns: minmax(0, 1.9fr) minmax(280px, 1fr); gap: 20px; align-items: start; }
.proses { padding: 28px 40px 32px; }
.proses__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.proses h2 { display: flex; align-items: center; gap: 10px; font-size: 18px; margin: 0; }
.bar-aksen { width: 4px; height: 20px; background: #0d8177; border-radius: 2px; }
.pill { display: inline-block; border-radius: 7px; padding: 3px 14px; font-size: 14px; background: #c9ccd1; color: #333; white-space: nowrap; }
.pill--berjalan { background: #cfe0ff; color: #12408c; }
.pill--berhasil { background: #8dfcb3; color: #0a5928; }
.pill--gagal { background: #fdc5c5; color: #b42318; }
.persen { height: 12px; border-radius: 6px; background: #d9d9d9; overflow: hidden; }
.persen__isi { display: block; height: 100%; background: #0d8177; transition: width .35s ease; }
.persen__isi--gagal { background: #d33; }
.persen__angka { text-align: right; font-size: 13px; color: #666; margin: 2px 0 12px; }
.langkah { list-style: none; margin: 0; padding: 0; display: grid; gap: 16px; }
.item { display: flex; align-items: center; gap: 16px; background: #fff; border: 1px solid #000; border-radius: 8px; padding: 14px 16px; transition: background .25s, opacity .25s; }
.item--menunggu { opacity: .85; }
.item--berjalan { background: #eaf6f4; border-color: #0d8177; }
.item--selesai .no { background: #0d8177; color: #fff; }
.item--gagal { background: #fdeaea; border-color: #b42318; }
.item--gagal .no { background: #b42318; color: #fff; }
.no { width: 50px; height: 50px; flex: none; border-radius: 50%; background: #d9d9d9; display: grid; place-items: center; font-size: 22px; font-weight: 600; }
.txt { flex: 1; display: grid; gap: 2px; min-width: 0; }
.txt strong { font-weight: 500; font-size: 16px; }
.txt span { color: #666; font-size: 13px; }
.tag { border-radius: 7px; padding: 3px 16px; font-size: 14px; min-width: 100px; text-align: center; }
.tag--hijau { background: #8dfcb3; color: #0a5928; }
.tag--biru { background: #7cc4ff; color: #0b3d6e; }
.tag--abu { background: #c9ccd1; color: #333; }
.st { width: 82px; text-align: right; font-size: 14px; color: #666; }
.hasil { margin: 18px 0 0; }
.hasil--ok { color: #0a5928; }
.hasil--gagal { color: var(--color-error); }
.hasil a { color: inherit; font-weight: 600; }
.samping { display: grid; gap: 16px; }
.samping .panel { padding: 18px 22px; }
.kop { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.kop h2, .samping h2 { margin: 0; font-size: 18px; }
.info { display: grid; grid-template-columns: auto 1fr; gap: 8px 40px; margin: 8px 0 0; color: #555; }
.info dd { margin: 0; }
.info strong { color: #000; }
.riwayat { list-style: none; margin: 8px 0 0; padding: 0; }
.baris { display: flex; justify-content: space-between; align-items: center; padding: 11px 0 9px; border-bottom: 1px solid #000; color: #555; text-decoration: none; }
.baris:hover { background: rgba(13, 129, 119, .08); }
.kosong { color: #777; }
@media (max-width: 1100px) { .kolom { grid-template-columns: 1fr; } .proses { padding: 22px; } }
@media (max-width: 640px) { .item { flex-wrap: wrap; } .tag, .st { min-width: 0; width: auto; } }
</style>
