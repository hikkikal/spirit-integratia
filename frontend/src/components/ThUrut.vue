<script setup>
// Judul kolom yang dapat diurutkan: SELURUH sel judul bisa diklik (atau Enter/Spasi saat fokus).
// Pakai di dalam <tr> pada <thead>: <ThUrut :tabel="t" kunci="nama">Nama</ThUrut>
const props = defineProps({ tabel: { type: Object, required: true }, kunci: { type: String, required: true } })
const aktif = () => props.tabel.kunci.value === props.kunci
const ariaSort = () => (aktif() ? (props.tabel.arah.value === 'asc' ? 'ascending' : 'descending') : 'none')
const panah = () => (!aktif() ? '⇅' : props.tabel.arah.value === 'asc' ? '▲' : '▼')
const ganti = () => props.tabel.ganti(props.kunci)
</script>

<template>
  <th class="urut-th" :class="{ 'urut-th--on': aktif() }" :aria-sort="ariaSort()" tabindex="0" title="Klik untuk mengurutkan" @click="ganti" @keydown.enter.prevent="ganti" @keydown.space.prevent="ganti">
    <span class="urut-isi"><slot /><span class="panah" aria-hidden="true">{{ panah() }}</span></span>
  </th>
</template>

<style scoped>
th.urut-th { cursor: pointer; user-select: none; white-space: nowrap; transition: background-color .15s; }
th.urut-th:hover { background: #cfe3df; }
th.urut-th:focus-visible { outline: 3px solid #f2b705; outline-offset: -3px; }
th.urut-th--on { background: #d9ebe7; }
.urut-isi { display: inline-flex; align-items: center; gap: 8px; }
th.r .urut-isi { justify-content: flex-end; width: 100%; }
th.c .urut-isi { justify-content: center; width: 100%; }
.panah { font-size: 14px; color: #6b7a77; }
.urut-th--on .panah { color: var(--color-primary); }
th.urut-th:hover .panah { color: var(--color-primary); }
</style>
