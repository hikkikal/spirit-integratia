<script setup>
import { ref, watch, onMounted } from 'vue'
import { getAcademia } from '../services/academia.js'
import { tanggalPanjang, rupiah } from '../lib/format.js'

// Rincian honor per minggu (kartu 1) dan total honor semester (kartu 2, terpisah). Data hanya dibaca dari penyimpanan:
// ditarik oleh Sinkronisasi Data (Admin Input), honornya dihitung otomatis di akhir sinkronisasi.
// Slot "total-aksi": tombol verifikasi (diisi halaman Keuangan) yang tampil di kartu total honor.
const props = defineProps({
  dosenId: String,
  periode: String,
  detailTo: { type: Function, required: true },
  pertemuanTo: { type: Function, required: true },
})
const data = ref(null)
const memuat = ref(true)
const galat = ref('')
const tampilContoh = ref(false)

async function muat() {
  memuat.value = true
  galat.value = ''
  try { data.value = await getAcademia(props.dosenId, props.periode) } catch (e) { data.value = null; galat.value = e.message }
  memuat.value = false
}
onMounted(muat)
watch(() => [props.dosenId, props.periode], muat)

const rentang = (m) => (m.dari ? (m.dari === m.sampai ? tanggalPanjang(m.dari) : `${tanggalPanjang(m.dari)} – ${tanggalPanjang(m.sampai)}`) : '')
const waktu = (iso) => new Date(iso).toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' })
</script>

<template>
  <section class="panel acad">
    <div class="kepala">
      <div>
        <h2>Rincian per minggu</h2>
        <p v-if="data" class="ket">Diperbarui {{ waktu(data.diambil) }}</p>
        <p v-else-if="!memuat" class="ket">Data per minggu belum tersedia untuk periode ini. Data diisi saat Sinkronisasi Data dijalankan oleh Admin Input.</p>
      </div>
    </div>

    <p v-if="galat" class="galat" role="alert">{{ galat }}</p>
    <p v-if="memuat" class="kosong">Memuat…</p>

    <template v-else>
      <template v-if="data">
        <ul v-if="data.catatan?.length" class="catatan-list" role="note">
          <li v-for="c in data.catatan" :key="c">{{ c }}</li>
        </ul>

        <p v-if="!data.minggu.length" class="kosong">Belum ada pertemuan terjadwal yang dapat dikelompokkan per minggu.</p>
        <div v-else class="scroll-x">
          <table class="tbl">
            <thead>
              <tr><th class="c">No</th><th>Minggu</th><th class="c">Total SKS</th><th class="c">Pertemuan rencana</th><th class="c">Pertemuan terealisasi</th><th class="c">Aksi</th></tr>
            </thead>
            <tbody>
              <tr v-for="(m, i) in data.minggu" :key="m.no" :class="{ redup: !m.kelas.length }">
                <td class="c">{{ i + 1 }}</td>
                <td>
                  <strong>{{ m.label }}</strong>
                  <div class="ket">{{ rentang(m) }}<template v-if="m.kategori"> · {{ m.kategori }}</template></div>
                </td>
                <template v-if="m.kelas.length">
                  <td class="c">
                    <div class="sks">{{ m.sks }}</div>
                    <RouterLink :to="pertemuanTo(m)" class="btn btn--ghost btn--sm">Lihat pertemuan</RouterLink>
                  </td>
                  <td class="c">{{ m.rencana }}</td>
                  <td class="c" :class="{ kurang: m.realisasi !== null && m.realisasi < m.rencana }">{{ m.realisasi ?? '–' }}</td>
                  <td class="c"><RouterLink :to="detailTo(m)" class="btn btn--ghost btn--sm">Lihat perhitungan</RouterLink></td>
                </template>
                <template v-else>
                  <td class="c ket" colspan="4">Tidak ada pertemuan</td>
                </template>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <template v-if="data?.contoh">
        <button class="btn btn--ghost btn--sm" type="button" @click="tampilContoh = !tampilContoh">{{ tampilContoh ? 'Sembunyikan' : 'Lihat' }} contoh respons mentah (mode debug)</button>
        <pre v-if="tampilContoh" class="contoh">{{ JSON.stringify(data.contoh, null, 2) }}</pre>
      </template>
    </template>
  </section>

  <section v-if="!memuat" class="panel acad total">
    <div class="total__kepala">
      <div>
        <h3>Total honor semester</h3>
        <p class="ket">Honor tiap minggu dijumlahkan untuk semua minggu yang ada pertemuannya.</p>
      </div>
      <div class="total__aksi"><slot name="total-aksi" /></div>
    </div>
    <div v-if="data?.total" class="kartu-total">
      <div v-if="data.total.real" class="kt utama">
        <div class="kt__lbl">Berdasarkan pertemuan terealisasi · {{ data.total.minggu_real }} minggu</div>
        <div class="kt__val">{{ rupiah(data.total.real.netto) }}</div>
        <div class="kt__sub">Bruto {{ rupiah(data.total.real.bruto) }} · Pajak {{ rupiah(data.total.real.pajak) }} · Neto {{ rupiah(data.total.real.netto) }}</div>
      </div>
      <div class="kt">
        <div class="kt__lbl">Jika semua pertemuan terjadwal terlaksana · {{ data.total.minggu_rencana }} minggu</div>
        <div class="kt__val">{{ rupiah(data.total.rencana.netto) }}</div>
        <div class="kt__sub">Bruto {{ rupiah(data.total.rencana.bruto) }} · Pajak {{ rupiah(data.total.rencana.pajak) }} · Neto {{ rupiah(data.total.rencana.netto) }}</div>
      </div>
    </div>
    <p v-else class="kosong">Total belum dapat dihitung karena data per minggu belum tersedia.</p>
    <p v-if="data?.tanpa_honor?.length" class="peringatan" role="note">Kelas berikut tidak ditemukan di perhitungan honor, jadi tidak ikut dijumlahkan: {{ data.tanpa_honor.join(', ') }}.</p>
    <p v-if="data?.total && !data.total.real" class="peringatan" role="note">Pertemuan terealisasi tidak tersedia, sehingga total hanya berdasarkan jadwal.</p>
  </section>
</template>

<style scoped>
.acad { margin-top: 16px; }
.kepala { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 12px; }
.kepala h2 { margin: 0; }
.ket { color: #555; font-size: 15px; }
.c { text-align: center; }
.sks { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
.btn--sm { padding: 4px 18px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; font-size: 15px; white-space: nowrap; }
.galat { background: #fdecea; border: 1px solid #c0392b; border-radius: 8px; padding: 10px 14px; margin: 8px 0; }
.catatan-list { background: #fff8e1; border: 1px solid #e0a800; border-radius: 8px; padding: 10px 14px 10px 32px; margin: 8px 0 16px; }
.kurang { color: #b03a2e; font-weight: 700; }
.redup { color: #777; background: #f7f7f7; }
.total { margin-top: 16px; }
.total__kepala { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 12px; }
.total h3 { font-size: 20px; margin: 0 0 4px; }
.total__aksi { display: flex; align-items: center; gap: 12px; }
.kartu-total { display: flex; gap: 12px; flex-wrap: wrap; }
.kt { flex: 1 1 320px; border: 1px solid #c9d6d3; border-radius: 10px; padding: 14px 16px; }
.kt.utama { border-color: var(--color-primary); background: #eef6f4; }
.kt__lbl { font-size: 15px; color: #444; }
.kt__val { font-size: 28px; font-weight: 700; margin: 4px 0; }
.kt__sub { font-size: 15px; color: #444; }
.peringatan { background: #fff8e1; border: 1px solid #e0a800; border-radius: 8px; padding: 8px 12px; margin-top: 10px; }
.contoh { max-height: 320px; overflow: auto; background: #f5f5f5; padding: 12px; font-size: 13px; margin-top: 8px; }
</style>
