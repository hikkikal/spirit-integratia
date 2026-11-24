<script setup>
defineProps({ status: String })
</script>
<template>
  <span class="badge" :class="status === 'terverifikasi' ? 'badge--ok' : 'badge--wait'">
    {{ status === 'terverifikasi' ? 'Terverifikasi' : 'Menunggu Verifikasi' }}
  </span>
</template>
<style scoped>
.badge { display: inline-block; padding: 2px 14px; border-radius: 999px; font-size: 15px; white-space: nowrap; }
.badge--ok { background: #c8efd7; color: #0a5928; }
.badge--wait { background: #fdecc8; color: #8a4b00; font-size: 15px; padding: 3px 12px; }
</style>
