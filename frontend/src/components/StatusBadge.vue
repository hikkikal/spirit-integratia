<script setup>
defineProps({ status: String })
</script>
<template>
  <span class="badge" :class="status === 'terverifikasi' ? 'badge--ok' : 'badge--wait'">
    {{ status === 'terverifikasi' ? 'Terverifikasi' : 'Menunggu Verifikasi' }}
  </span>
</template>
<style scoped>
.badge { display: inline-block; padding: 2px 14px; border-radius: 999px; font-size: 14px; white-space: nowrap; }
.badge--ok { background: #8dfcb3; color: #0a5928; }
.badge--wait { background: #fdecc8; color: #8a4b00; font-size: 12px; padding: 3px 12px; }
</style>
