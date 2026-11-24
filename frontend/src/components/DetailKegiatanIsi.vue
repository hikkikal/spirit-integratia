<script setup>
import { computed } from 'vue'

// Isi rincian satu kegiatan non-pengajaran (mahasiswa, catatan, informasi, dosen pembimbing).
// Dipakai popup Keuangan/Pimpinan/Admin dan panel samping role Dosen, supaya isinya selalu sama.
const props = defineProps({ kegiatan: Object, periode: String })
const pilih = computed(() => props.kegiatan)
const daftarMhs = computed(() => { const m = pilih.value?.detail?.mahasiswa; return Array.isArray(m) ? m : m ? [m] : [] })
const info = computed(() => {
  const k = pilih.value
  if (!k) return []
  const d = k.detail
  const r = [['Lokasi', k.kampus], ['Program Studi', k.prodi], ['Semester', props.periode]]
  if (d) r.push(['Tanggal Mulai', d.tglMulai], ['Tanggal Selesai', d.tglSelesai], ['SK Penugasan', d.sk], ['Tanggal SK Penugasan', d.tglSk],
    ['Keterangan', d.keterangan], ['Kegiatan Komunal', d.komunal], ['Flagship', d.flagship])
  r.push(['Status BKD', k.statBkd])
  return r.map(([a, b]) => [a, b || '–'])
})
const inisial = (n) => (n || '').split(/\s+/).filter(Boolean).slice(0, 2).map((x) => x[0]).join('').toUpperCase()
</script>

<template>
  <div v-if="pilih" class="isi">
    <p class="isi__judul">{{ pilih.judul }}</p>
    <template v-if="daftarMhs.length">
      <h3>Mahasiswa ({{ daftarMhs.length }})</h3>
      <div class="mhs-list">
        <div v-for="(m, i) in daftarMhs" :key="i" class="mhs">
          <span class="ava">{{ inisial(m.nama) }}</span>
          <div><strong>{{ m.nama }}</strong><br /><span class="abu">{{ m.nim }}</span></div>
          <span v-if="m.peran" class="np-tag">{{ m.peran }}</span>
        </div>
      </div>
    </template>
    <p v-if="pilih.catatan" class="isi__cat">{{ pilih.catatan }}</p>
    <h3>Informasi Kegiatan</h3>
    <dl class="info">
      <template v-for="[a, b] in info" :key="a"><dt>{{ a }}</dt><dd>{{ b }}</dd></template>
    </dl>
    <template v-if="pilih.detail?.pembimbing?.length">
      <h3>Dosen Pembimbing</h3>
      <ul class="pemb">
        <li v-for="(p, i) in pilih.detail.pembimbing" :key="i" :class="{ saya: p.saya }">
          <span class="ava">{{ inisial(p.nama) }}</span>
          <div><strong>{{ p.nama }}</strong><br /><span class="abu">{{ p.peran }}</span></div>
          <span class="np-tag">Urutan {{ p.urutan ?? i + 1 }}</span>
        </li>
      </ul>
    </template>
  </div>
</template>

<style scoped>
.dlg__judul, .isi__judul { }
.np-tag { display: inline-block; font-size: 15px; border: 1px solid #a9bcb8; border-radius: 999px; padding: 3px 14px; background: #fff; white-space: nowrap; flex: none; }
.mhs, .pemb li { display: flex; align-items: center; gap: 14px; border: 1px solid #c3d0cd; border-radius: 10px; padding: 10px 14px; transition: background-color .15s ease; }
.mhs:hover { background: #f3f7f6; }
.mhs > div, .pemb li > div { min-width: 0; flex: 1; line-height: 1.35; }
.mhs .np-tag, .pemb .np-tag { margin-left: auto; color: #4a5755; border-color: #888; }
.mhs-list { display: grid; gap: 8px; max-height: 240px; overflow-y: auto; }
.ava { width: 46px; height: 46px; border-radius: 50%; background: #0d8177; color: #fff; display: grid; place-items: center; flex: none; font-size: 15px; }
.abu { color: #4a5755; font-size: 15px; }
.info { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); margin: 0; border-top: 1px solid #a9bcb8; }
.info dt, .info dd { margin: 0; padding: 11px 6px; border-bottom: 1px solid #dbe4e2; font-size: 15px; overflow-wrap: anywhere; }
.info dd { font-weight: 600; }
.info dt { color: #4a5755; }
.pemb { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
.pemb li.saya { background: #e3f1ee; border-color: var(--color-primary); }
.isi__judul { font-size: 19px; font-weight: 700; margin: 0 0 4px; line-height: 1.4; overflow-wrap: anywhere; }
.isi h3 { font-size: 15px; margin: 20px 0 8px; color: #33423f; text-transform: uppercase; letter-spacing: .04em; }
.isi__cat { background: #fff4d6; border-radius: 6px; padding: 8px 12px; font-size: 15px; }
</style>
