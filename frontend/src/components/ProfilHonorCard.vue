<script setup>
// Kartu nama/profil di bagian atas halaman detail honor: nama dosen (paling menonjol), jabatan dan jurusan,
// angka ringkas, tombol aksi, lalu catatan perlu pengecekan (bila ada) di dalam kartu yang sama.
defineProps({ nama: String, sub: String, catatan: String, catatanJudul: { type: String, default: 'Perlu pengecekan' } })
const inisial = (n) => (n || '').split(/\s+/).filter(Boolean).slice(0, 2).map((x) => x[0]).join('').toUpperCase()
</script>

<template>
  <section class="profil panel">
    <div class="atas">
      <span class="ava" aria-hidden="true">{{ inisial(nama) }}</span>
      <div class="id">
        <h1>{{ nama }}</h1>
        <p v-if="sub">{{ sub }}</p>
      </div>
      <div class="aksi"><slot name="aksi" /></div>
    </div>
    <div class="angka"><slot /></div>
    <p v-if="catatan" class="catatan" role="note"><strong>{{ catatanJudul }}:</strong> {{ catatan }}</p>
  </section>
</template>

<style scoped>
.profil { margin-bottom: 20px; padding: 20px 24px; }
.atas { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.ava { width: 60px; height: 60px; border-radius: 50%; background: #0d8177; color: #fff; display: grid; place-items: center; font-size: 22px; font-weight: 700; flex: none; }
.id { min-width: 0; flex: 1 1 260px; }
.id h1 { font-size: 28px; font-weight: 700; margin: 0; line-height: 1.2; overflow-wrap: anywhere; }
.id p { margin: 4px 0 0; color: #4a5755; font-size: 16px; }
.aksi { margin-left: auto; }
.angka { display: flex; gap: 14px 40px; flex-wrap: wrap; margin-top: 18px; padding-top: 16px; border-top: 1px solid #dbe4e2; }
.angka :deep(.ket) { display: grid; gap: 2px; font-size: 15px; color: #4a5755; }
.angka :deep(.ket strong) { font-size: 22px; font-weight: 700; color: #0b3f3b; line-height: 1.3; }
.catatan { background: #fff8e1; border: 1px solid #e0b400; border-radius: 8px; padding: 10px 14px; font-size: 15px; margin: 16px 0 0; }
</style>
