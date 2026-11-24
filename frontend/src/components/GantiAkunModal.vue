<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth, setUser } from '../stores/auth.js'
import { switchRole } from '../services/auth.js'
import { ROLES, homeFor } from '../lib/roles.js'
import { deskripsiRole } from '../data/dosen.js'
import ModalAdmin from './ModalAdmin.vue'

// buka = false memicu animasi tutup; induk tetap memasang komponen ini.
defineProps({ buka: { type: Boolean, default: true } })
const emit = defineEmits(['close'])
const router = useRouter()
const error = ref('')
const busy = ref(false)

const daftar = () => (auth.user?.roles?.length ? auth.user.roles : [auth.user?.role]).filter(Boolean)

async function pilih(role) {
  if (role === auth.user.role || busy.value) return
  busy.value = true
  error.value = ''
  try {
    const user = await switchRole(auth.user, role)
    setUser(user)
    emit('close')
    router.push(homeFor(user.role))
  } catch (e) {
    error.value = e.message || 'Gagal berganti peran.'
  } finally {
    busy.value = false
  }
}

</script>

<template>
  <ModalAdmin :buka="buka" judul="Ganti Peran" :sub="`Anda masuk sebagai ${ROLES[auth.user?.role]?.label.toLowerCase()}, pilih peran yang tersedia untuk melanjutkan`" @close="emit('close')">
    <ul>
      <li v-for="r in daftar()" :key="r" :class="{ on: r === auth.user?.role }">
        <span class="avatar">{{ ROLES[r]?.label[0] }}</span>
        <div class="txt">
          <strong>{{ ROLES[r]?.label }}</strong>
          <span>{{ deskripsiRole[r] }}</span>
        </div>
        <button class="pill" :class="{ 'pill--on': r === auth.user?.role }" :disabled="r === auth.user?.role || busy" @click="pilih(r)">
          {{ r === auth.user?.role ? 'Aktif' : 'Pilih' }}
        </button>
      </li>
    </ul>
    <p v-if="error" class="err" role="alert">{{ error }}</p>
  </ModalAdmin>
</template>

<style scoped>
ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
li { display: flex; align-items: center; gap: 14px; border: 1px solid #a9bcb8; border-radius: 10px; padding: 12px 14px; transition: background-color .15s ease; }
li.on { background: #e3f1ee; border-color: var(--color-primary); }
.avatar { width: 52px; height: 52px; border-radius: 50%; background: #0d8177; color: #fff; display: grid; place-items: center; font-size: 20px; flex: none; }
.txt { flex: 1; display: grid; font-size: 15px; color: #4a5755; min-width: 0; }
.txt strong { color: #000; font-size: 17px; }
.pill { border: 1px solid #0d8177; color: #0d8177; background: #fff; border-radius: 8px; padding: 6px 18px; font: inherit; cursor: pointer; transition: background-color .15s ease, color .15s ease; }
.pill:not(:disabled):hover { background: #0d8177; color: #fff; }
.pill--on { background: #0d8177; color: #fff; cursor: default; }
.err { color: var(--color-error); margin: 12px 0 0; }
</style>