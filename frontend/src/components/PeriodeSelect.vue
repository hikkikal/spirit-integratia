<script setup>
import { honorState } from '../stores/honor.js'
</script>
<template>
  <label class="psel">
    <span>Periode</span>
    <select v-model="honorState.periode">
      <option v-for="p in honorState.periodeOptions" :key="p">{{ p }}</option>
    </select>
  </label>
</template>
<style scoped>
.psel { display: grid; gap: 4px; font-size: 16px; }
.psel select { font: inherit; padding: 8px 14px; border: 0; border-radius: 4px; background: #d9d9d9; min-width: 190px; cursor: pointer; }
</style>
