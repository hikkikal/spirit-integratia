import { createRouter, createWebHistory } from 'vue-router'
import { auth, ensureUser } from '../stores/auth.js'
import { homeFor } from '../lib/roles.js'

const Dashboard = () => import('../views/keuangan/DashboardPage.vue')
const DataHonor = () => import('../views/keuangan/DataHonorPage.vue')
const DetailHonor = () => import('../views/keuangan/DetailHonorPage.vue')
const Laporan = () => import('../views/keuangan/LaporanEksporPage.vue')

// Halaman Dashboard, Data Honor, dan Detail dipakai bersama oleh Keuangan, Pimpinan, dan Admin Input.
// TODO: pisahkan per peran bila desain Figma Pimpinan/Admin Input ternyata berbeda.
const bersama = [
  { path: 'dashboard', name: 'dashboard', component: Dashboard },
  { path: 'data-honor', name: 'data-honor', component: DataHonor },
  { path: 'data-honor/:id', name: 'detail-honor', component: DetailHonor, props: true },
]

const area = (role, children) => ({
  path: `/${role}`,
  component: () => import('../layouts/AppLayout.vue'),
  meta: { auth: true, role },
  redirect: `/${role}/dashboard`,
  children,
})

const routes = [
  { path: '/', redirect: '/login' },
  { path: '/login', name: 'login', component: () => import('../views/LoginPage.vue'), meta: { guest: true } },
  { path: '/auth/callback', name: 'auth-callback', component: () => import('../views/AuthCallbackPage.vue') },
  area('keuangan', [...bersama, { path: 'laporan', name: 'laporan', component: Laporan }]),
  area('pimpinan', bersama),
  area('admin', bersama),
  area('dosen', [
    { path: 'dashboard', name: 'dosen-dashboard', component: () => import('../views/dosen/DashboardSayaPage.vue') },
    { path: 'rincian', name: 'dosen-rincian', component: () => import('../views/dosen/RincianKelasPage.vue') },
    { path: 'riwayat', name: 'dosen-riwayat', component: () => import('../views/dosen/RiwayatHonorPage.vue') },
    { path: 'detail', name: 'dosen-detail', component: () => import('../views/dosen/DetailHonorDosenPage.vue') },
    { path: 'profil', name: 'dosen-profil', component: () => import('../views/dosen/ProfilPage.vue') },
  ]),
  { path: '/:pathMatch(.*)*', redirect: '/login' },
]

const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach(async (to) => {
  if (to.name === 'auth-callback') return true
  const user = await ensureUser()
  const role = to.matched.find((r) => r.meta.role)?.meta.role
  if (to.meta.auth && !user) return { name: 'login' }
  if (to.meta.guest && user) return homeFor(user.role)
  // Peran tidak boleh membuka area peran lain. Pemeriksaan sebenarnya tetap di backend.
  if (role && user && role !== user.role) return homeFor(user.role)
})

export default router
