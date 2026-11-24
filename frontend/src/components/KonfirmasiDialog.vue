<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { dialog, jawab } from '../lib/konfirmasi.js'

const tombol = ref(null)
watch(() => dialog.buka, async (b) => { if (b) { await nextTick(); tombol.value?.focus() } })
const onKey = (e) => { if (dialog.buka && e.key === 'Escape') jawab(false) }
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="dialog.buka" class="tirai-konf" @mousedown.self="jawab(false)">
        <div class="konf" role="alertdialog" aria-modal="true" aria-labelledby="konf-judul" aria-describedby="konf-isi">
          <h2 id="konf-judul">{{ dialog.judul }}</h2>
          <p id="konf-isi">{{ dialog.isi }}</p>
          <div class="aksi">
            <button class="btn btn--ghost" type="button" @click="jawab(false)">{{ dialog.tidak }}</button>
            <button ref="tombol" class="btn" type="button" @click="jawab(true)">{{ dialog.ya }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.tirai-konf { position: fixed; inset: 0; background: rgba(0, 0, 0, .45); display: grid; place-items: center; padding: 16px; z-index: 200; }
</style>
