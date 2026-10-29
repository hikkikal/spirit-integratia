<script setup>
// Fade antar area besar (login ↔ layout role). Perpindahan halaman di dalam layout ditangani AppLayout.
</script>

<template>
  <RouterView v-slot="{ Component, route }">
    <Transition name="fade" mode="out-in">
      <component :is="Component" :key="route.matched[0]?.path ?? route.path" />
    </Transition>
  </RouterView>
</template>
