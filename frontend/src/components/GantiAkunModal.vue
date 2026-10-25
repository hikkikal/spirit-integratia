<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth, setUser } from '../stores/auth.js'
import { switchRole } from '../services/auth.js'
import { ROLES, homeFor } from '../lib/roles.js'
import { deskripsiRole } from '../data/dosen.js'

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

const onKey = (e) => e.key === 'Escape' && emit('close')
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="overlay" @click.self="emit('close')">
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="ga-title">
      <header>
        <h2 id="ga-title">Ganti Akun</h2>
        <button class="x" aria-label="Tutup" @click="emit('close')">×</button>
      </header>
      <p class="sub">Anda masuk sebagai {{ ROLES[auth.user.role]?.label.toLowerCase() }}, pilih role yang tersedia untuk melanjutkan</p>

      <ul>
        <li v-for="r in daftar()" :key="r" :class="{ on: r === auth.user.role }">
          <span class="avatar">{{ ROLES[r]?.label[0] }}</span>
          <div class="txt">
            <strong>{{ ROLES[r]?.label }}</strong>
            <span>{{ deskripsiRole[r] }}</span>
          </div>
          <button class="pill" :class="{ 'pill--on': r === auth.user.role }" :disabled="r === auth.user.role || busy" @click="pilih(r)">
            {{ r === auth.user.role ? 'Aktif' : 'Pilih' }}
          </button>
        </li>
      </ul>
      <p v-if="error" class="err" role="alert">{{ error }}</p>
    </section>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: grid; place-items: center; z-index: 50; padding: 16px; }
.modal { background: #fff; border-radius: 10px; width: min(560px, 100%); padding: 24px; }
header { display: flex; justify-content: space-between; align-items: center; }
h2 { margin: 0; font-size: 26px; }
.x { background: none; border: 0; font-size: 30px; line-height: 1; cursor: pointer; }
.sub { color: #55554f; margin: 8px 0 16px; }
ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
li { display: flex; align-items: center; gap: 14px; border: 1px solid #999; border-radius: 8px; padding: 12px 14px; }
li.on { background: #cfe5e2; border-color: #cfe5e2; }
.avatar { width: 52px; height: 52px; border-radius: 50%; background: #0d8177; color: #fff; display: grid; place-items: center; font-size: 20px; flex: none; }
.txt { flex: 1; display: grid; font-size: 14px; color: #55554f; }
.txt strong { color: #000; font-size: 17px; }
.pill { border: 1px solid #0d8177; color: #0d8177; background: #fff; border-radius: 6px; padding: 6px 18px; font: inherit; cursor: pointer; }
.pill--on { background: #0d8177; color: #fff; cursor: default; }
.err { color: var(--color-error); margin: 12px 0 0; }
</style>
