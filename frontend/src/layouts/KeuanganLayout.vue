<script setup>
import { useRoute, useRouter } from 'vue-router'
import { auth, setUser } from '../stores/auth.js'
import { honorState } from '../stores/honor.js'
import { periodeOptions } from '../data/mock.js'
import BrandLogo from '../components/BrandLogo.vue'

const route = useRoute()
const router = useRouter()

const menu = [
  { label: 'Dashboard', to: '/keuangan/dashboard', match: 'dashboard' },
  { label: 'Data Dosen', to: '/keuangan/data-honor', match: 'honor' },
  { label: 'Laporan & Ekspor', to: '/keuangan/laporan', match: 'laporan' },
]
const aktif = (m) => String(route.name || '').includes(m.match)

function keluar() {
  setUser(null)
  router.push('/login')
}
</script>

<template>
  <div class="shell">
    <aside class="side">
      <div class="side__brand">
        <BrandLogo :size="44" />
        <span class="side__name">Integratia</span>
      </div>
      <span class="chip">BIDANG KEUANGAN</span>
      <nav class="side__nav">
        <RouterLink v-for="m in menu" :key="m.to" :to="m.to" class="nav" :class="{ 'nav--on': aktif(m) }">
          {{ m.label }}
        </RouterLink>
      </nav>
      <div class="side__user">
        <span class="side__who">{{ auth.user?.name }}</span>
        <button class="side__out" @click="keluar">Keluar</button>
      </div>
    </aside>

    <div class="main">
      <header class="top">
        <label class="periode">
          <span class="sr-only">Periode</span>
          <select v-model="honorState.periode">
            <option v-for="p in periodeOptions" :key="p">{{ p }}</option>
          </select>
        </label>
      </header>
      <main class="content"><RouterView /></main>
    </div>
  </div>
</template>

<style scoped>
.shell { display: flex; min-height: 100vh; background: var(--color-bg); }
.side { width: 260px; flex: none; background: var(--color-primary); color: #fff; padding: 28px 20px; display: flex; flex-direction: column; gap: 20px; position: sticky; top: 0; height: 100vh; }
.side__brand { display: flex; align-items: center; gap: 12px; }
.side__name { font-size: 24px; font-weight: 600; }
.chip { align-self: flex-start; font-size: 12px; letter-spacing: .08em; padding: 4px 10px; border-radius: 999px; background: rgba(255,255,255,.16); }
.side__nav { display: flex; flex-direction: column; gap: 6px; margin-top: 8px; }
.nav { color: #fff; text-decoration: none; padding: 12px 16px; border-radius: 10px; font-size: 18px; }
.nav:hover { background: rgba(255,255,255,.1); }
.nav--on { background: var(--color-primary-dark); font-weight: 600; }
.side__user { margin-top: auto; display: flex; flex-direction: column; gap: 8px; font-size: 14px; }
.side__out { align-self: flex-start; background: none; border: 1px solid rgba(255,255,255,.5); color: #fff; border-radius: 8px; padding: 6px 14px; cursor: pointer; font: inherit; }
.main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.top { display: flex; justify-content: flex-end; padding: 20px 40px 0; }
.periode select { font: inherit; font-size: 16px; padding: 10px 14px; border: 1px solid #c9c9c2; border-radius: 10px; background: #fff; }
.content { padding: 16px 40px 48px; }
@media (max-width: 900px) {
  .shell { flex-direction: column; }
  .side { width: auto; height: auto; position: static; flex-direction: row; flex-wrap: wrap; align-items: center; }
  .side__nav { flex-direction: row; flex-wrap: wrap; margin: 0; }
  .side__user { margin: 0 0 0 auto; }
  .top, .content { padding-left: 16px; padding-right: 16px; }
}
</style>
