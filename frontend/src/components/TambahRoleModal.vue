<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { ROLE_INFO } from '../data/pengguna.js'

// Pilih role yang akan ditambahkan. Role yang sudah dimiliki tidak bisa dipilih lagi.
const props = defineProps({ dimiliki: { type: Array, required: true } })
const emit = defineEmits(['close', 'lanjut'])
const pilihan = ref([])

function toggle(r) {
  if (props.dimiliki.includes(r)) return
  pilihan.value = pilihan.value.includes(r) ? pilihan.value.filter((x) => x !== r) : [...pilihan.value, r]
}
const onKey = (e) => e.key === 'Escape' && emit('close')
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="overlay" @click.self="emit('close')">
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="tr-title">
      <header>
        <div><h2 id="tr-title">Tambah Role</h2><p>Pilih role yang akan ditambahkan</p></div>
        <button class="x" aria-label="Tutup" @click="emit('close')">⊗</button>
      </header>
      <ul>
        <li v-for="(info, r) in ROLE_INFO" :key="r">
          <button type="button" class="opt" :class="{ on: pilihan.includes(r), own: dimiliki.includes(r) }" :disabled="dimiliki.includes(r)" :aria-pressed="pilihan.includes(r)" @click="toggle(r)">
            <span class="ava">{{ info.kode }}</span>
            <span class="txt"><strong>{{ info.label }}</strong><small>{{ info.ket }}</small></span>
            <span v-if="dimiliki.includes(r)" class="tag">Sudah Dimiliki</span>
            <span v-else class="radio" :class="{ chk: pilihan.includes(r) }">{{ pilihan.includes(r) ? '✓' : '' }}</span>
          </button>
        </li>
      </ul>
      <footer>
        <button class="btn btn--ghost" @click="emit('close')">Batal</button>
        <button class="btn" :disabled="!pilihan.length" @click="emit('lanjut', pilihan)">Lanjutkan</button>
      </footer>
    </section>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: grid; place-items: center; z-index: 60; padding: 16px; }
.modal { background: #fff; border-radius: 12px; width: min(574px, 100%); padding: 20px; max-height: 94vh; overflow: auto; }
header { display: flex; justify-content: space-between; align-items: flex-start; }
h2 { margin: 0; font-size: 26px; }
header p { margin: 2px 0 14px; color: #55554f; }
.x { background: none; border: 0; font-size: 28px; cursor: pointer; line-height: 1; }
ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
.opt { width: 100%; display: flex; align-items: center; gap: 14px; text-align: left; font: inherit; background: #fff; border: 1px solid #000; border-radius: 6px; padding: 12px 14px; cursor: pointer; transition: background-color .15s; }
.opt:hover:not(:disabled) { background: #f3f8f7; }
.opt.on { background: #cfe5e2; }
.opt.own { background: #c9c9c9; border-color: #aaa; color: #777; cursor: not-allowed; }
.ava { width: 54px; height: 54px; border-radius: 50%; background: #0d8177; color: #fff; display: grid; place-items: center; flex: none; }
.own .ava { background: #7bb0aa; }
.txt { flex: 1; display: grid; }
.txt strong { font-weight: 500; font-size: 18px; }
.opt.on .txt strong { font-weight: 700; }
.txt small { color: #55554f; font-size: 13px; }
.radio { width: 26px; height: 26px; border-radius: 50%; border: 1px solid #777; display: grid; place-items: center; color: #fff; flex: none; }
.radio.chk { background: #0d8177; border-color: #0d8177; }
.tag { background: #ddd; border-radius: 6px; padding: 3px 10px; font-size: 13px; color: #333; }
footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 26px; }
</style>
