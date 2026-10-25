<script setup>
defineProps({ status: String })
</script>
<template>
  <span class="badge" :class="status === 'terverifikasi' ? 'badge--ok' : 'badge--wait'">
    {{ status === 'terverifikasi' ? 'Terverifikasi' : 'Menunggu' }}
  </span>
</template>
<style scoped>
.badge { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: 14px; }
.badge--ok { background: #d6ece9; color: var(--color-primary-dark); }
.badge--wait { background: #fdecc8; color: #8a5a00; }
</style>
