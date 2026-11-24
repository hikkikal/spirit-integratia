<script setup>
import { computed } from 'vue'
// Navigasi halaman untuk useTabel().  <Pager :tabel="t" />
const props = defineProps({ tabel: { type: Object, required: true } })
const hal = computed(() => {
  const n = props.tabel.jumlahHalaman.value, h = props.tabel.halaman.value
  const s = new Set([1, n, h - 1, h, h + 1])
  const urut = [...s].filter((x) => x >= 1 && x <= n).sort((a, b) => a - b)
  const out = []
  urut.forEach((x, i) => { if (i && x - urut[i - 1] > 1) out.push('…'); out.push(x) })
  return out
})
</script>

<template>
  <div v-if="tabel.total.value" class="pager">
    <span class="info" role="status">Menampilkan {{ tabel.dari.value }}–{{ tabel.sampai.value }} dari {{ tabel.total.value }}</span>
    <label class="per">Per halaman
      <select v-model.number="tabel.ukuran.value">
        <option v-for="u in [10, 25, 50, 100]" :key="u" :value="u">{{ u }}</option>
      </select>
    </label>
    <nav class="nomor" aria-label="Halaman">
      <button type="button" class="hal" :disabled="tabel.halaman.value <= 1" aria-label="Halaman sebelumnya" @click="tabel.ke(tabel.halaman.value - 1)">‹</button>
      <template v-for="(x, i) in hal" :key="i">
        <span v-if="x === '…'" class="titik">…</span>
        <button v-else type="button" class="hal" :class="{ 'hal--on': x === tabel.halaman.value }" :aria-current="x === tabel.halaman.value ? 'page' : null" @click="tabel.ke(x)">{{ x }}</button>
      </template>
      <button type="button" class="hal" :disabled="tabel.halaman.value >= tabel.jumlahHalaman.value" aria-label="Halaman berikutnya" @click="tabel.ke(tabel.halaman.value + 1)">›</button>
    </nav>
  </div>
</template>

<style scoped>
.pager { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-top: 16px; font-size: 15px; }
.info { margin-right: auto; color: #444; }
.per { display: flex; align-items: center; gap: 8px; }
.per select { font: inherit; padding: 4px 8px; border: 1px solid var(--color-primary); border-radius: 6px; background: #fff; }
.nomor { display: flex; align-items: center; gap: 6px; }
.hal { font: inherit; min-width: 34px; height: 34px; padding: 0 8px; border: 1px solid var(--color-primary); border-radius: 6px; background: #fff; color: var(--color-primary); cursor: pointer; }
.hal--on { background: var(--color-primary); color: #fff; }
.hal:disabled { opacity: .4; cursor: default; }
.titik { padding: 0 2px; color: #4a5755; }
@media (max-width: 560px) { .info { flex: 1 1 100%; } }
</style>
