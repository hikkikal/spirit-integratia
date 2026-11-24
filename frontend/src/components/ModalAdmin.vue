<script setup>
import { onMounted, onUnmounted } from 'vue'
// buka = false memicu animasi keluar; induk tetap memasang komponen ini (jangan pakai v-if untuk menutup).
const props = defineProps({ judul: String, sub: String, buka: { type: Boolean, default: true } })
const emit = defineEmits(['close'])
const tutup = () => emit('close')
const onKey = (e) => e.key === 'Escape' && props.buka && tutup()
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <Transition name="modal" appear>
    <div v-if="buka" class="overlay" @mousedown.self="tutup">
      <section class="modal" role="dialog" aria-modal="true" aria-labelledby="m-judul">
        <header>
          <div>
            <h2 id="m-judul">{{ judul }}</h2>
            <p v-if="sub">{{ sub }}</p>
          </div>
          <button class="x" type="button" aria-label="Tutup" @click="tutup">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10.5" fill="none" stroke="currentColor" stroke-width="2" /><path d="M8 8l8 8M16 8l-8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
          </button>
        </header>
        <slot />
      </section>
    </div>
  </Transition>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, .45); display: grid; place-items: center; z-index: 60; padding: 16px; overflow-y: auto; }
.modal { background: #fff; border-radius: 12px; width: min(680px, 100%); padding: 28px 32px 30px; max-height: calc(100vh - 32px); overflow-y: auto; }
header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 18px; }
h2 { margin: 0; font-size: 24px; }
header p { margin: 4px 0 0; color: #4a5755; font-size: 15px; }
.x { background: none; border: 0; padding: 0; cursor: pointer; width: 34px; height: 34px; color: #000; flex: none; }
.x svg { width: 100%; height: 100%; }
@media (max-width: 560px) { .modal { padding: 20px 18px; } }
</style>
