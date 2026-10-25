<script setup>
// SLOT LOGO: ganti berkas src/assets/logo.svg (atau .png) dengan logo asli.
// Logo ditaruh di lencana putih agar terbaca di atas latar hijau maupun terang,
// jadi satu berkas logo cukup untuk semua tempat.
import logoUrl from '../assets/logo.svg'

defineProps({
  size: { type: Number, default: 96 },
})
</script>

<template>
  <span class="brand-logo" :style="{ '--size': `${size}px` }">
    <img :src="logoUrl" alt="Logo Integratia" />
  </span>
</template>

<style scoped>
.brand-logo {
  display: inline-grid;
  place-items: center;
  width: var(--size);
  height: var(--size);
  padding: calc(var(--size) * 0.14);
  border-radius: 26%;
  background: #fff;
  box-shadow: 0 4px 18px rgb(0 0 0 / 0.14);
}

.brand-logo img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
}
</style>
