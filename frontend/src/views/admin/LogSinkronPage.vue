<script setup>
import { onMounted, ref, watch } from 'vue'
import { getLogSinkron } from '../../services/admin.js'
import { angka } from '../../lib/format.js'
import { useBase } from '../../lib/useBase.js'

const props = defineProps({ id: String })
const base = useBase()
const log = ref(null)
const selesai = ref(false)
const galat = ref('')
const buka = ref(new Set())

const NAMA = { berhasil: 'Berhasil', gagal: 'Gagal', dilewati: 'Dilewati' }
const NAMA_ATRIBUT = { terambil: 'Terambil', gagal: 'Gagal', dilewati: 'Dilewati' }

async function muat() {
  selesai.value = false
  galat.value = ''
  try {
    log.value = await getLogSinkron(props.id)
    // Endpoint yang gagal terbuka otomatis; bila semua berhasil, buka yang pertama.
    const gagal = log.value?.endpoint.find((e) => e.status === 'gagal')
    buka.value = new Set([gagal ? gagal.no : 1])
  } catch (e) {
    log.value = null
    galat.value = e.message
  }
  selesai.value = true
}
onMounted(muat)
watch(() => props.id, muat)

function ganti(no) {
  const s = new Set(buka.value)
  s.has(no) ? s.delete(no) : s.add(no)
  buka.value = s
}
const detik = (d) => (d == null ? '-' : String(d).replace('.', ',') + ' dtk')
</script>

<template>
  <p v-if="!selesai">Memuat…</p>
  <p v-else-if="!log" role="alert">{{ galat || 'Log sinkronisasi tidak ditemukan.' }} <RouterLink :to="`${base}/dashboard`">Kembali</RouterLink></p>
  <template v-else>
    <RouterLink :to="`${base}/dashboard`" class="kembali">◂ Kembali</RouterLink>
    <h1 class="page-title">Log Aktivitas</h1>
    <p class="page-sub">Detail per sinkronisasi : hasil tiap endpoint SISTER dan atribut yang diambil</p>

    <section class="panel luar">
      <div class="ringkas">
        <span class="pill" :class="log.status === 'berhasil' ? 'pill--berhasil' : 'pill--gagal'">{{ log.status === 'berhasil' ? 'Berhasil' : 'Gagal' }}</span>
        <div><small>Mulai</small>{{ log.mulai }}</div>
        <div><small>Semester</small>{{ log.semester }}</div>
        <div><small>Durasi</small>{{ log.durasi }}</div>
        <div><small>Endpoint</small>{{ log.endpointBerhasil }} dari {{ log.endpointTotal }} Berhasil</div>
        <div><small>Total rekord</small>{{ angka(log.totalRekord) }}</div>
      </div>

      <div v-if="log.status === 'gagal'" class="banner" role="alert">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.6" /><path d="M12 7v6M12 16.5v.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
        <p>{{ log.pesanGagal }}</p>
      </div>

      <p class="legenda">Keterangan: <span><i class="k k--biru"></i>Terambil</span> <span><i class="k k--merah"></i>Gagal</span> <span><i class="k k--abu"></i>Dilewati</span></p>

      <ul class="daftar">
        <li v-for="e in log.endpoint" :key="e.no" class="ep" :class="{ 'ep--buka': buka.has(e.no) }">
          <button class="ep__head" :aria-expanded="buka.has(e.no)" @click="ganti(e.no)">
            <span class="no">{{ e.no }}</span>
            <span class="nama">
              <strong>{{ e.nama }}</strong>
              <small v-if="e.status === 'berhasil'">{{ e.atributStatus.length }} Atribut - {{ detik(e.detik) }}</small>
              <small v-else>{{ e.keterangan }}</small>
            </span>
            <span class="pill" :class="`pill--${e.status}`">{{ NAMA[e.status] }}</span>
            <svg class="panah" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 9l7 7 7-7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" /></svg>
          </button>
          <div v-if="buka.has(e.no)" class="ep__isi">
            <p class="meta">
              <span>Endpoint : <b>{{ e.path }}</b></span>
              <span>Waktu Respons : <b>{{ detik(e.detik) }}</b></span>
              <span>Rekord Diterima : <b>{{ angka(e.rekord) }}</b></span>
              <span>Atribut : <b>{{ e.atributStatus.length }}</b></span>
            </p>
            <p v-if="e.status === 'gagal'" class="kotak-gagal">{{ e.keterangan }}</p>
            <p class="sub">Riwayat Proses</p>
            <ul class="atribut">
              <li v-for="a in e.atributStatus" :key="a.nama">
                <i class="k" :class="a.status === 'terambil' ? 'k--biru' : a.status === 'gagal' ? 'k--merah' : 'k--abu'"></i>
                <span>{{ a.nama }}</span>
                <span class="pill pill--atr" :class="`pill--atr-${a.status}`">{{ NAMA_ATRIBUT[a.status] }}</span>
              </li>
            </ul>
          </div>
        </li>
      </ul>
    </section>
  </template>
</template>

<style scoped>
.kembali { display: inline-block; margin-bottom: 4px; color: #0b5e58; text-decoration: none; }
.luar { padding: 28px clamp(16px, 4vw, 96px) 40px; }
.ringkas { display: flex; flex-wrap: wrap; align-items: center; gap: 14px 40px; background: #fff; border: 1px solid #000; border-radius: 8px; padding: 14px 20px; }
.ringkas small { display: block; color: #666; font-size: 12px; }
.pill { display: inline-block; border-radius: 7px; padding: 3px 14px; font-size: 14px; white-space: nowrap; }
.pill--berhasil { background: #8dfcb3; color: #0a5928; }
.pill--gagal { background: #fdc5c5; color: #b42318; }
.pill--dilewati { background: #d9d9d9; color: #666; }
.banner { display: flex; gap: 18px; align-items: center; margin-top: 16px; padding: 14px 24px; background: #fbcccc; border: 1px solid #e39b9b; border-radius: 8px; color: #8a2121; }
.banner svg { width: 38px; height: 38px; flex: none; }
.banner p { margin: 0; }
.legenda { display: flex; flex-wrap: wrap; gap: 6px 18px; align-items: center; margin: 18px 0 14px; font-size: 15px; }
.legenda span { display: inline-flex; align-items: center; gap: 6px; }
.k { display: inline-block; width: 11px; height: 11px; border-radius: 2px; flex: none; }
.k--biru { background: #2f6df0; }
.k--merah { background: #e11; }
.k--abu { background: #c9c9c9; }
.daftar { list-style: none; margin: 0; padding: 0; display: grid; gap: 16px; }
.ep { background: #fff; border: 1px solid #000; border-radius: 8px; overflow: hidden; }
.ep__head { width: 100%; display: flex; align-items: center; gap: 18px; padding: 14px 30px; background: none; border: 0; cursor: pointer; text-align: left; }
.ep__head:hover { background: #f7faf9; }
.no { width: 50px; height: 50px; flex: none; border-radius: 50%; background: #d9d9d9; display: grid; place-items: center; font-size: 22px; font-weight: 600; }
.nama { flex: 1; display: grid; min-width: 0; }
.nama strong { font-weight: 500; }
.nama small { color: #777; }
.panah { width: 22px; height: 22px; transition: transform .2s; }
.ep--buka .panah { transform: rotate(180deg); }
.ep__isi { border-top: 1px solid #ccc; padding: 18px 40px 22px; }
.meta { display: flex; flex-wrap: wrap; gap: 6px 36px; margin: 0 0 14px; color: #666; font-size: 13px; }
.meta b { color: #000; font-weight: 500; }
.kotak-gagal { background: #fbcccc; border: 1px solid #e39b9b; color: #a22; border-radius: 4px; padding: 10px 14px; margin: 0 0 14px; }
.sub { font-size: 13px; margin: 0 0 12px; }
.atribut { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.atribut li { display: flex; align-items: center; gap: 12px; }
.atribut span:nth-child(2) { flex: 1; }
.pill--atr { min-width: 86px; text-align: center; font-size: 13px; padding: 2px 12px; }
.pill--atr-terambil { background: #a9c1f7; color: #1b3f9a; }
.pill--atr-gagal { background: #fdb3b3; color: #b42318; }
.pill--atr-dilewati { background: #dcdcdc; color: #666; }
@media (max-width: 700px) { .ep__head { padding: 12px 14px; gap: 10px; } .ep__isi { padding: 14px; } }
</style>
