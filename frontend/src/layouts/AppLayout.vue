<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auth, setUser } from '../stores/auth.js'
import { logout } from '../services/auth.js'
import { ROLES, MENUS } from '../lib/roles.js'
import { honorState } from '../stores/honor.js'
import { periodeOptions } from '../data/mock.js'
import BrandLogo from '../components/BrandLogo.vue'
import GantiAkunModal from '../components/GantiAkunModal.vue'

const route = useRoute()
const router = useRouter()

const role = auth.user?.role
const base = ROLES[role]?.base ?? ''
const menu = (MENUS[role] ?? []).map((m) => ({ ...m, to: `${base}/${m.to}` }))
const aktif = (m) => String(route.name || '').includes(m.match)

// Dosen memilih periode di dalam halamannya sendiri, jadi bilah periode global disembunyikan.
const tampilPeriode = role !== 'dosen'
const inisial = computed(() =>
  (auth.user?.name || '?').split(/\s+/).map((k) => k[0]).join('').slice(0, 2).toUpperCase(),
)

const menuBuka = ref(false)
const modalBuka = ref(false)
const userRef = ref(null)

function tutupDiLuar(e) {
  if (userRef.value && !userRef.value.contains(e.target)) menuBuka.value = false
}
onMounted(() => document.addEventListener('click', tutupDiLuar))
onUnmounted(() => document.removeEventListener('click', tutupDiLuar))

function gantiAkun() {
  menuBuka.value = false
  modalBuka.value = true
}

async function keluar() {
  let logoutUrl = null
  try {
    logoutUrl = await logout()
  } finally {
    setUser(null)
  }
  // Mode SSO: akhiri juga sesi di CAS PNJ.
  if (logoutUrl) location.assign(logoutUrl)
  else router.push('/login')
}
</script>

<template>
  <div class="shell">
    <aside class="side">
      <div class="side__brand">
        <BrandLogo :size="44" />
        <span class="side__name">Integratia</span>
      </div>
      <span class="chip">{{ ROLES[role]?.chip }}</span>
      <nav class="side__nav">
        <RouterLink v-for="m in menu" :key="m.to" :to="m.to" class="nav" :class="{ 'nav--on': aktif(m) }">
          {{ m.label }}
        </RouterLink>
      </nav>

      <div ref="userRef" class="side__user">
        <div v-if="menuBuka" class="popup" role="menu">
          <div class="popup__head">
            <strong>{{ auth.user?.name }}</strong>
            <span>Role aktif: {{ ROLES[role]?.label }}</span>
          </div>
          <RouterLink v-if="role === 'dosen'" :to="`${base}/profil`" class="popup__item" role="menuitem" @click="menuBuka = false">
            Lihat Profil
          </RouterLink>
          <button class="popup__item" role="menuitem" @click="gantiAkun">Ganti Akun</button>
          <button class="popup__item popup__item--out" role="menuitem" @click="keluar">Log Out</button>
        </div>
        <button class="usercard" :aria-expanded="menuBuka" @click.stop="menuBuka = !menuBuka">
          <span class="avatar">{{ inisial }}</span>
          <span class="who">
            <strong>{{ auth.user?.name }}</strong>
            <span>{{ ROLES[role]?.label }}</span>
          </span>
          <span class="caret" :class="{ 'caret--open': menuBuka }">⌃</span>
        </button>
      </div>
    </aside>

    <div class="main">
      <header v-if="tampilPeriode" class="top">
        <label class="periode">
          <span class="sr-only">Periode</span>
          <select v-model="honorState.periode">
            <option v-for="p in periodeOptions" :key="p">{{ p }}</option>
          </select>
        </label>
      </header>
      <main class="content"><RouterView /></main>
    </div>

    <GantiAkunModal v-if="modalBuka" @close="modalBuka = false" />
  </div>
</template>

<style scoped>
.shell { display: flex; min-height: 100vh; background: var(--color-bg); }
.side { width: 260px; flex: none; background: var(--color-primary); color: #fff; padding: 28px 20px 16px; display: flex; flex-direction: column; gap: 20px; position: sticky; top: 0; height: 100vh; }
.side__brand { display: flex; align-items: center; gap: 12px; }
.side__name { font-size: 24px; font-weight: 600; }
.chip { align-self: flex-start; font-size: 12px; letter-spacing: .08em; padding: 4px 10px; border-radius: 4px; background: var(--color-primary-dark); }
.side__nav { display: flex; flex-direction: column; gap: 6px; margin-top: 8px; }
.nav { color: #fff; text-decoration: none; padding: 12px 16px; border-radius: 10px; font-size: 18px; }
.nav:hover { background: rgba(255,255,255,.1); }
.nav--on { background: var(--color-primary-dark); font-weight: 600; }

.side__user { margin-top: auto; position: relative; border-top: 1px solid rgba(255,255,255,.4); padding-top: 12px; }
.usercard { width: 100%; display: flex; align-items: center; gap: 10px; background: none; border: 0; color: #fff; font: inherit; cursor: pointer; text-align: left; padding: 6px 4px; border-radius: 10px; }
.usercard:hover { background: rgba(255,255,255,.1); }
.avatar { width: 40px; height: 40px; border-radius: 50%; background: #d9d9d9; color: #000; display: grid; place-items: center; font-weight: 700; flex: none; }
.who { flex: 1; display: grid; min-width: 0; font-size: 13px; }
.who strong { font-size: 15px; }
.caret { font-size: 20px; transition: transform .15s; transform: rotate(180deg); }
.caret--open { transform: none; }

.popup { position: absolute; bottom: calc(100% - 4px); left: 0; right: 0; background: #fff; color: #000; border-radius: 10px; padding: 10px 12px; box-shadow: 0 6px 20px rgba(0,0,0,.25); display: grid; gap: 2px; }
.popup__head { display: grid; border-bottom: 1px solid #ccc; padding-bottom: 8px; margin-bottom: 6px; font-size: 13px; color: #55554f; }
.popup__head strong { color: #000; font-size: 15px; }
.popup__item { background: none; border: 0; font: inherit; text-align: left; padding: 8px 4px; border-radius: 6px; color: #000; text-decoration: none; cursor: pointer; display: block; }
.popup__item:hover { background: #eef2f1; }
.popup__item--out { color: #b42318; border-top: 1px solid #ccc; margin-top: 6px; padding-top: 12px; }

.main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.top { display: flex; justify-content: flex-end; padding: 20px 40px 0; }
.periode select { font: inherit; font-size: 16px; padding: 10px 14px; border: 1px solid #c9c9c2; border-radius: 10px; background: #fff; }
.content { padding: 16px 40px 48px; }
@media (max-width: 900px) {
  .shell { flex-direction: column; }
  .side { width: auto; height: auto; position: static; flex-direction: row; flex-wrap: wrap; align-items: center; }
  .side__nav { flex-direction: row; flex-wrap: wrap; margin: 0; }
  .side__user { margin: 0 0 0 auto; border: 0; padding: 0; min-width: 220px; }
  .popup { bottom: auto; top: 100%; }
  .top, .content { padding-left: 16px; padding-right: 16px; }
}
</style>
