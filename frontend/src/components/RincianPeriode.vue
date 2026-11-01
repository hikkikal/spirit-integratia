<script setup>
import { rupiah } from '../lib/format.js'

// Tampilan rincian dosen per periode (profil, kartu ringkasan, tabel per minggu).
// Dipakai role Dosen (milik sendiri) dan Keuangan/Pimpinan (saat membuka Detail dosen).
defineProps({
  nama: String,
  jabatan: String,
  prodi: String,
  email: String,
  totalKelas: { type: [Number, String], default: '-' },
  terlaksana: Number,
  wajib: Number,
  lebih: Number,
  minggu: { type: Array, default: () => [] },
  detailTo: { type: Function, required: true },
})
</script>

<template>
  <section class="panel prof">
    <svg class="ava" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="none" stroke="#000" stroke-width="1.6" /><circle cx="12" cy="9" r="3.2" /><path d="M5.5 18.5c1.3-3 3.6-4.4 6.5-4.4s5.2 1.4 6.5 4.4c-1.8 1.9-4 2.8-6.5 2.8s-4.7-.9-6.5-2.8z" /></svg>
    <div>
      <h2>{{ nama }}</h2>
      <p>{{ jabatan }} · {{ prodi }}</p>
      <p>{{ email }}</p>
    </div>
  </section>

  <section class="grid cards">
    <div class="stat"><div class="stat__lbl">Total Kelas</div><div class="stat__val">{{ totalKelas }}</div><div class="stat__sub">{{ totalKelas }} mata kuliah</div></div>
    <div class="stat"><div class="stat__lbl">SKS terlaksana</div><div class="stat__val">{{ terlaksana }}</div></div>
    <div class="stat"><div class="stat__lbl">SKS Wajib</div><div class="stat__val">{{ wajib }}</div><div class="stat__sub">{{ jabatan }}</div></div>
    <div class="stat"><div class="stat__lbl">SKS Lebih</div><div class="stat__val">{{ lebih }}</div><div class="stat__sub">{{ terlaksana }}-{{ wajib }}</div></div>
  </section>

  <section class="panel">
    <template v-if="!minggu.length">
      <p class="kosong">Rincian per minggu periode ini belum tersedia (menunggu kalender akademik dari SISTER).</p>
      <RouterLink :to="detailTo()" class="btn btn--ghost sm">Lihat detail honor per kelas</RouterLink>
    </template>
    <div v-else class="scroll-x">
      <table class="tbl tbl--line">
        <thead>
          <tr><th>Minggu</th><th>Total SKS</th><th>WM</th><th>Tarif Perdir</th><th>Rincian Bruto Perdir</th><th>Pajak</th><th>Netto</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <tr v-for="m in minggu" :key="m.label">
            <td>{{ m.label }}<br />Perkuliahan</td>
            <td>{{ m.totalSks }}</td><td>{{ m.wm }}</td>
            <td><div v-for="t in m.tarif" :key="t">{{ t }}</div></td>
            <td>
              <div v-for="r in m.rincian" :key="r">{{ r }}</div>
              <div>Total: {{ rupiah(m.total) }}</div>
            </td>
            <td>{{ m.pajak }}</td><td>{{ m.netto }}</td>
            <td><RouterLink :to="detailTo(m)" class="btn btn--ghost sm">Lihat Detail</RouterLink></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.prof { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; padding: 20px 20px; }
.ava { width: 76px; height: 76px; flex: none; }
.prof h2 { font-size: 32px; font-weight: 500; margin: 0; }
.prof p { margin: 2px 0; }
.cards { grid-template-columns: repeat(4, 1fr); margin-bottom: 20px; }
.sm { padding: 6px 14px; white-space: nowrap; text-decoration: none; display: inline-block; }
.tbl td { vertical-align: middle; font-size: 14px; }
.kosong { color: #77776f; }
@media (max-width: 900px) { .cards { grid-template-columns: repeat(2, 1fr); } }
</style>
