<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auth, setUser } from '../stores/auth.js'
import { logout } from '../services/auth.js'
import { ROLES, MENUS } from '../lib/roles.js'
import BrandLogo from '../components/BrandLogo.vue'
import GantiAkunModal from '../components/GantiAkunModal.vue'
import IkonMenu from '../components/IkonMenu.vue'

const route = useRoute()
const router = useRouter()

const role = auth.user?.role
const base = ROLES[role]?.base ?? ''
const menu = (MENUS[role] ?? []).map((m) =>
  m.children
    ? { ...m, children: m.children.map((c) => ({ ...c, to: `${base}/${c.to}` })) }
    : { ...m, to: `${base}/${m.to}` })
const aktif = (m) => String(route.name || '').includes(m.match)
// Grup menu (mis. Data Dosen): terbuka bila salah satu anaknya aktif, atau dibuka manual.
const manual = reactive({})
const terbuka = (m) => manual[m.label] ?? m.children.some(aktif)
const alihkan = (m) => { manual[m.label] = !terbuka(m) }

const inisial = computed(() =>
  (auth.user?.name || '?').split(/\s+/).map((k) => k[0]).join('').slice(0, 2).toUpperCase(),
)

const menuBuka = ref(false)
const navBuka = ref(false)   // menu utama pada layar HP (tombol ☰)
watch(() => route.fullPath, () => { navBuka.value = false })
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
        <BrandLogo :size="40" />
        <span class="side__name">Integratia</span>
      </div>
      <span class="chip">{{ ROLES[role]?.chip }}</span>
      <span class="side__cap">Menu</span>
      <button type="button" class="burger" :aria-expanded="navBuka" aria-label="Menu" @click.stop="navBuka = !navBuka">
        <span></span><span></span><span></span>
      </button>
      <nav class="side__nav" :class="{ 'side__nav--buka': navBuka }">
        <template v-for="m in menu" :key="m.label">
          <div v-if="m.children">
            <button type="button" class="nav nav--grup" :class="{ 'nav--induk': m.children.some(aktif) }" :aria-expanded="terbuka(m)" @click="alihkan(m)">
              <span class="nav__isi"><IkonMenu :nama="m.ikon ?? m.children[0].match" />{{ m.label }}</span>
              <svg class="chev" :class="{ 'chev--buka': terbuka(m) }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
            </button>
            <Transition name="sub"><div v-if="terbuka(m)" class="sub">
              <RouterLink v-for="c in m.children" :key="c.to" :to="c.to" class="nav nav--anak" :class="{ 'nav--on': aktif(c) }"><span class="nav__isi">{{ c.label }}</span></RouterLink>
            </div></Transition>
          </div>
          <RouterLink v-else :to="m.to" class="nav" :class="{ 'nav--on': aktif(m) }"><span class="nav__isi"><IkonMenu :nama="m.match" />{{ m.label }}</span></RouterLink>
        </template>
      </nav>

      <div ref="userRef" class="side__user">
        <Transition name="pop"><div v-if="menuBuka" class="popup" role="menu">
          <div class="popup__head">
            <strong>{{ auth.user?.name }}</strong>
            <span>Role aktif: {{ ROLES[role]?.label }}</span>
          </div>
          <RouterLink :to="`${base}/profil`" class="popup__item" role="menuitem" @click="menuBuka = false">
            Lihat Profil
          </RouterLink>
          <button class="popup__item" role="menuitem" @click="gantiAkun">Ganti Peran</button>
          <button class="popup__item popup__item--out" role="menuitem" @click="keluar">Keluar</button>
        </div></Transition>
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
      <main class="content">
        <RouterView v-slot="{ Component, route }">
          <Transition name="page" mode="out-in">
            <div :key="route.path" class="page"><component :is="Component" /></div>
          </Transition>
        </RouterView>
      </main>
    </div>

    <GantiAkunModal :buka="modalBuka" @close="modalBuka = false" />
  </div>
</template>

<style scoped>
.shell { display: flex; min-height: 100vh; background: var(--color-bg); }
.side { width: 272px; flex: none; background: linear-gradient(180deg, var(--color-primary) 0%, #09504b 100%); color: #fff; padding: 26px 16px 16px; display: flex; flex-direction: column; gap: 14px; position: sticky; top: 0; height: 100vh; }
.side__brand { display: flex; align-items: center; gap: 12px; padding: 0 6px; }
.side__name { font-size: 24px; font-weight: 700; letter-spacing: .01em; }
.chip { align-self: flex-start; margin: 0 6px; font-size: 13px; font-weight: 600; letter-spacing: .1em; text-transform: uppercase; padding: 5px 12px; border-radius: 999px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); }
.side__cap { margin: 14px 8px 0; font-size: 13px; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.62); }
.side__nav { display: flex; flex-direction: column; gap: 4px; margin-top: 0; overflow-y: auto; padding-right: 2px; }
.burger { display: none; }
.nav { position: relative; color: rgba(255,255,255,.9); text-decoration: none; padding: 12px 14px; border-radius: 12px; font-size: 17px; display: flex; align-items: center; justify-content: space-between; gap: 10px; transition: background-color .18s ease, color .18s ease, transform .18s ease; }
.nav__isi { display: flex; align-items: center; gap: 12px; min-width: 0; }
.nav:hover { background: rgba(255,255,255,.1); color: #fff; }
.nav:active { transform: scale(.985); }
.nav--on { background: rgba(255,255,255,.17); color: #fff; font-weight: 600; box-shadow: inset 0 0 0 1px rgba(255,255,255,.12); }
.nav--on::before { content: ''; position: absolute; left: -16px; top: 9px; bottom: 9px; width: 4px; border-radius: 0 4px 4px 0; background: #8fe3d6; animation: tanda .25s ease both; }
@keyframes tanda { from { transform: scaleY(0); } to { transform: scaleY(1); } }
.nav--grup { width: 100%; border: 0; background: none; font: inherit; font-size: 17px; text-align: left; cursor: pointer; }
.nav--grup.nav--induk { color: #fff; font-weight: 600; }
.chev { width: 18px; height: 18px; flex: none; opacity: .8; transition: transform .22s ease; }
.chev--buka { transform: rotate(180deg); }
.sub { display: grid; gap: 2px; margin: 4px 0 6px 22px; padding-left: 14px; border-left: 2px solid rgba(255,255,255,.2); }
.nav--anak { font-size: 16px; padding: 9px 12px; }
.nav--anak.nav--on { background: rgba(255,255,255,.17); }
.nav--anak.nav--on::before { left: -16px; top: 8px; bottom: 8px; }

.side__user { margin-top: auto; position: relative; border-top: 1px solid rgba(255,255,255,.22); padding-top: 12px; }
.usercard { width: 100%; display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.08); border: 0; color: #fff; font: inherit; cursor: pointer; text-align: left; padding: 8px 10px; border-radius: 12px; transition: background-color .18s ease; }
.usercard:hover { background: rgba(255,255,255,.16); }
.avatar { width: 40px; height: 40px; border-radius: 50%; background: #e4ecea; color: #000; display: grid; place-items: center; font-weight: 700; flex: none; }
.who { flex: 1; display: grid; min-width: 0; font-size: 15px; }
.who strong { font-size: 15px; }
.caret { font-size: 20px; transition: transform .15s; transform: rotate(180deg); }
.caret--open { transform: none; }

.popup { position: absolute; bottom: calc(100% - 4px); left: 0; right: 0; background: #fff; color: #000; border-radius: 10px; padding: 10px 12px; box-shadow: 0 6px 20px rgba(0,0,0,.25); display: grid; gap: 2px; }
.popup__head { display: grid; border-bottom: 1px solid #ccc; padding-bottom: 8px; margin-bottom: 6px; font-size: 15px; color: #4a5755; }
.popup__head strong { color: #000; font-size: 15px; }
.popup__item { background: none; border: 0; font: inherit; text-align: left; padding: 8px 4px; border-radius: 6px; color: #000; text-decoration: none; cursor: pointer; display: block; }
.popup__item:hover { background: #eef2f1; }
.popup__item--out { color: #b42318; border-top: 1px solid #ccc; margin-top: 6px; padding-top: 12px; }

.main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.content { padding: 28px 40px 48px; }
@media (max-width: 900px) {
  .shell { flex-direction: column; }
  .side { width: auto; height: auto; position: static; flex-direction: row; flex-wrap: wrap; align-items: center; }
  .side__nav { flex-direction: row; flex-wrap: wrap; margin: 0; }
  .side__user { margin: 0 0 0 auto; border: 0; padding: 0; min-width: 220px; }
  .popup { bottom: auto; top: 100%; }
  .content { padding-left: 16px; padding-right: 16px; }
}
</style>
