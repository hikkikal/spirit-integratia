import { createRouter, createWebHistory } from 'vue-router'
import { auth, ensureUser } from '../stores/auth.js'
import { homeFor } from '../lib/roles.js'

const Dashboard = () => import('../views/keuangan/DashboardPage.vue')
const DataHonor = () => import('../views/keuangan/DataHonorPage.vue')
const DetailHonor = () => import('../views/keuangan/DetailHonorPage.vue')
const DosenRincian = () => import('../views/keuangan/DosenRincianPage.vue')
const Tarif = () => import('../views/keuangan/TarifKetentuanPage.vue')
const Laporan = () => import('../views/keuangan/LaporanEksporPage.vue')

// Halaman Dashboard, Data Honor, dan Detail dipakai bersama oleh Keuangan, Pimpinan, dan Admin Input.
// TODO: pisahkan per peran bila desain Figma Pimpinan/Admin Input ternyata berbeda.
const bersama = [
  { path: 'dashboard', name: 'dashboard', component: Dashboard },
  { path: 'data-honor', name: 'data-honor', component: DataHonor },
  { path: 'data-honor/:id', name: 'data-honor-rincian', component: DosenRincian, props: true },
  { path: 'data-honor/:id/detail', name: 'data-honor-detail', component: DetailHonor, props: (r) => ({ id: r.params.id, periode: r.query.periode }) },
  { path: 'tarif', name: 'tarif', component: Tarif },
]

// Nama rute harus unik di seluruh aplikasi. vue-router menghapus rute lama bila ada nama kembar,
// jadi tiap area diberi awalan peran, misalnya "keuangan-dashboard".
const profil = { path: 'profil', name: 'profil', component: () => import('../views/dosen/ProfilPage.vue') }
const area = (role, children, path = `/${role}`, home = 'dashboard') => ({
  path,
  component: () => import('../layouts/AppLayout.vue'),
  meta: { auth: true, role },
  redirect: `${path}/${home}`,
  children: children.map((c) => ({ ...c, name: `${role}-${c.name}` })),
})

const routes = [
  { path: '/', redirect: '/login' },
  { path: '/login', name: 'login', component: () => import('../views/LoginPage.vue'), meta: { guest: true } },
  { path: '/auth/callback', name: 'auth-callback', component: () => import('../views/AuthCallbackPage.vue') },
  area('keuangan', [...bersama, { path: 'laporan', name: 'laporan', component: Laporan }, profil]),
  area('pimpinan', [...bersama, { path: 'laporan', name: 'laporan', component: Laporan }, profil]),
  area('admin', [...bersama, profil]),
  area('admin_super', [
    { path: 'pengguna', name: 'pengguna', component: () => import('../views/super/PenggunaPage.vue') },
    { path: 'log', name: 'log', component: () => import('../views/super/LogAktivitasPage.vue') },
    profil,
  ], '/admin-super', 'pengguna'),
  area('dosen', [
    { path: 'dashboard', name: 'dashboard', component: () => import('../views/dosen/DashboardSayaPage.vue') },
    { path: 'rincian', name: 'rincian', component: () => import('../views/dosen/RincianKelasPage.vue') },
    { path: 'riwayat', name: 'riwayat', component: () => import('../views/dosen/RiwayatHonorPage.vue') },
    { path: 'detail', name: 'rincian-detail', component: () => import('../views/dosen/DetailHonorDosenPage.vue') },
    { path: 'profil', name: 'profil', component: () => import('../views/dosen/ProfilPage.vue') },
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
