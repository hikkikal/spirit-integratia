<script setup>
// Ikon garis untuk menu sidebar (24x24, mengikuti warna teks).
defineProps({ nama: String })
const PATH = {
  dashboard: 'M4 13h6V4H4v9zm0 7h6v-4H4v4zm10 0h6v-9h-6v9zm0-16v4h6V4h-6z',
  dosen: 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM4 20c0-3.3 3.6-5.5 8-5.5s8 2.2 8 5.5',
  'data-honor': 'M3 8l9-4 9 4-9 4-9-4zM7 10.5V15c0 1.4 2.2 3 5 3s5-1.6 5-3v-4.5',
  'non-pengajaran': 'M9 4h6l1 2h3v14H5V6h3l1-2zM9 12l2 2 4-4',
  laporan: 'M7 3h8l4 4v14H7V3zM14 3v5h5M10 13h6M10 17h6',
  tarif: 'M12 3v18M16 7.5c0-1.4-1.8-2.5-4-2.5s-4 1.1-4 2.5S10 10 12 10.5s4 1.1 4 2.5-1.8 2.5-4 2.5-4-1.1-4-2.5',
  perdir: 'M6 3h9l3 3v15H6V3zM9 10h6M9 14h6M9 18h4',
  pengguna: 'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM2.5 20c0-3 2.8-5 6.5-5s6.5 2 6.5 5M16 4.5a3.2 3.2 0 0 1 0 6M18 15c2 .6 3.5 2 3.5 4.5',
  log: 'M12 7v5l3 2M21 12a9 9 0 1 1-3-6.7M21 4v4h-4',
  rincian: 'M4 6h16M4 12h16M4 18h10',
  riwayat: 'M12 7v5l3 2M21 12a9 9 0 1 1-3-6.7M21 4v4h-4',
}
</script>

<template>
  <svg class="ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path :d="PATH[nama] || PATH.dashboard" />
  </svg>
</template>

<style scoped>
.ikon { width: 22px; height: 22px; flex: none; }
</style>
