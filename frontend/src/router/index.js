import { createRouter, createWebHistory } from 'vue-router'
import { auth, ensureUser } from '../stores/auth.js'
import { homeFor } from '../lib/roles.js'

const Dashboard = () => import('../views/keuangan/DashboardPage.vue')
const DataHonor = () => import('../views/keuangan/DataHonorPage.vue')
const DetailHonor = () => import('../views/keuangan/DetailHonorPage.vue')
const DosenRincian = () => import('../views/keuangan/DosenRincianPage.vue')
const Pertemuan = () => import('../views/keuangan/PertemuanPage.vue')
const NonPengajaran = () => import('../views/keuangan/NonPengajaranPage.vue')
const NonPengajaranDetail = () => import('../views/keuangan/NonPengajaranDetailPage.vue')
const Tarif = () => import('../views/keuangan/TarifKetentuanPage.vue')
const Laporan = () => import('../views/keuangan/LaporanEksporPage.vue')

// Halaman Dashboard, Data Honor, dan Detail dipakai bersama oleh Keuangan, Pimpinan, dan Admin Input.
// TODO: pisahkan per peran bila desain Figma Pimpinan/Admin Input ternyata berbeda.
const bersama = [
  { path: 'dashboard', name: 'dashboard', component: Dashboard },
  { path: 'data-honor', name: 'data-honor', component: DataHonor },
  { path: 'data-honor/:id', name: 'data-honor-rincian', component: DosenRincian, props: true },
  { path: 'data-honor/:id/detail', name: 'data-honor-detail', component: DetailHonor, props: (r) => ({ id: r.params.id, periode: r.query.periode }) },
  { path: 'data-honor/:id/pertemuan', name: 'data-honor-pertemuan', component: Pertemuan, props: (r) => ({ id: r.params.id, periode: r.query.periode, minggu: r.query.minggu }) },
  { path: 'non-pengajaran', name: 'non-pengajaran', component: NonPengajaran },
  { path: 'non-pengajaran/:id', name: 'non-pengajaran-detail', component: NonPengajaranDetail, props: true },
  { path: 'tarif', name: 'tarif', component: Tarif },
]

const dataDosen = bersama.filter((r) => !['dashboard', 'tarif'].includes(r.name)).concat({ path: 'laporan', name: 'laporan', component: Laporan })

// Nama rute harus unik di seluruh aplikasi. vue-router menghapus rute lama bila ada nama kembar,
// jadi tiap area diberi awalan peran, misalnya "keuangan-dashboard".
const profil = { path: 'profil', name: 'profil', component: () => import('../views/dosen/ProfilPage.vue') }
// Judul tab browser per halaman: "<judul> · Integratia".
const JUDUL = {
  dashboard: 'Dashboard', 'data-honor': 'Data Dosen', 'data-honor/:id': 'Rincian Dosen', 'data-honor/:id/detail': 'Detail Honor', 'data-honor/:id/pertemuan': 'Pertemuan', 'rincian/pertemuan': 'Pertemuan',
  tarif: 'Tarif & Ketentuan', laporan: 'Laporan & Ekspor', profil: 'Profil', 'dashboard/log/:id': 'Log Sinkronisasi',
  'master-tarif': 'Master Tarif', perdir: 'Dokumen Perdir', pengguna: 'Pengguna', log: 'Log Aktivitas',
  'non-pengajaran': 'Non-Pengajaran', 'non-pengajaran/:id': 'Detail Non-Pengajaran',
  rincian: 'Rincian Kelas', riwayat: 'Riwayat Honor', detail: 'Detail Honor',
}
const area = (role, children, path = `/${role}`, home = 'dashboard') => ({
  path,
  component: () => import('../layouts/AppLayout.vue'),
  meta: { auth: true, role },
  redirect: `${path}/${home}`,
  children: children.map((c) => ({ ...c, name: `${role}-${c.name}`, meta: { title: c.title ?? JUDUL[c.path], ...c.meta } })),
})

const routes = [
  { path: '/', redirect: '/login' },
  { path: '/login', name: 'login', component: () => import('../views/LoginPage.vue'), meta: { guest: true, title: 'Masuk' } },
  { path: '/auth/callback', name: 'auth-callback', component: () => import('../views/AuthCallbackPage.vue'), meta: { title: 'Masuk' } },
  area('keuangan', [...bersama, { path: 'laporan', name: 'laporan', component: Laporan }, profil]),
  area('pimpinan', [...bersama, { path: 'laporan', name: 'laporan', component: Laporan }, profil]),
  area('admin', [
    { path: 'dashboard', name: 'dashboard', component: () => import('../views/admin/DashboardAdminPage.vue') },
    // Tampilan data seperti Keuangan (tanpa dashboard keuangan) untuk memeriksa data tarikan dari SISTER, termasuk koreksi manual.
    ...dataDosen,
    { path: 'dashboard/log/:id', name: 'dashboard-log', component: () => import('../views/admin/LogSinkronPage.vue'), props: true },
    { path: 'master-tarif', name: 'master-tarif', component: () => import('../views/admin/MasterTarifPage.vue') },
    { path: 'perdir', name: 'perdir', component: () => import('../views/admin/PerdirPage.vue') },
    profil,
  ]),
  area('admin_super', [
    { path: 'pengguna', name: 'pengguna', component: () => import('../views/super/PenggunaPage.vue') },
    { path: 'log', name: 'log', component: () => import('../views/super/LogAktivitasPage.vue') },
    profil,
  ], '/admin-super', 'pengguna'),
  area('dosen', [
    { path: 'dashboard', name: 'dashboard', title: 'Dashboard Saya', component: () => import('../views/dosen/DashboardSayaPage.vue') },
    { path: 'rincian', name: 'rincian', component: () => import('../views/dosen/RincianKelasPage.vue') },
    { path: 'rincian/pertemuan', name: 'rincian-pertemuan', component: () => import('../views/keuangan/PertemuanPage.vue'), props: (r) => ({ periode: r.query.periode, minggu: r.query.minggu }) },
    { path: 'riwayat', name: 'riwayat', component: () => import('../views/dosen/RiwayatHonorPage.vue') },
    { path: 'detail', name: 'rincian-detail', component: () => import('../views/dosen/DetailHonorDosenPage.vue') },
    { path: 'non-pengajaran', name: 'non-pengajaran', component: () => import('../views/dosen/NonPengajaranDosenPage.vue') },
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

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · Integratia` : 'Integratia'
})

export default router
