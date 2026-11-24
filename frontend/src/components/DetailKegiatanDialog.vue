<script setup>
import { ref, watch, nextTick } from 'vue'
import DetailKegiatanIsi from './DetailKegiatanIsi.vue'

// Popup "Detail Kegiatan" non-pengajaran (Keuangan/Pimpinan/Admin). Data kegiatan ditahan selama animasi tutup berjalan.
const props = defineProps({ buka: Boolean, kegiatan: Object, nama: String, periode: String })
const emit = defineEmits(['close'])
const dlg = ref(null)
const tahan = ref(null)
watch(() => props.kegiatan, (k) => { if (k) tahan.value = k }, { immediate: true })
watch(() => props.buka, async (b) => { if (b) { await nextTick(); dlg.value?.focus() } })
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="buka && tahan" class="tirai" @click.self="emit('close')">
        <div ref="dlg" class="dlg" role="dialog" aria-modal="true" aria-labelledby="dlg-judul" tabindex="-1" @keydown.esc="emit('close')">
          <h2 id="dlg-judul">Detail Kegiatan</h2>
          <p class="dlg__sub">{{ nama }} • {{ periode }}</p>
          <hr />
          <DetailKegiatanIsi :kegiatan="tahan" :periode="periode" />
          <div class="dlg__aksi"><button class="btn btn--ghost" type="button" @click="emit('close')">Tutup</button></div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.tirai { position: fixed; inset: 0; background: rgba(0, 0, 0, .45); display: grid; place-items: center; padding: 20px 16px; z-index: 100; }
.dlg { background: #fff; border-radius: 16px; width: min(660px, 100%); max-height: calc(100vh - 40px); overflow-y: auto; padding: 24px 28px 22px; outline: none; }
.dlg h2 { font-size: 24px; margin: 0; }
.dlg__sub { color: #4a5755; margin: 2px 0 10px; font-size: 15px; }
.dlg hr { border: 0; border-top: 1px solid #a9bcb8; margin: 0 0 16px; }
.dlg__aksi { display: flex; justify-content: flex-end; margin-top: 18px; }
</style>
