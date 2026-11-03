import { api } from './api.js'

// "sso"  = produksi: login lewat CAS PNJ, divalidasi backend Laravel.
// "dev"  = pengembangan dengan backend lokal: pilih peran, backend membuat sesi (hanya APP_ENV=local).
// "mock" = pengembangan: form tiruan, tanpa backend dan tanpa password sungguhan.
const MODE = import.meta.env.VITE_AUTH_MODE
export const AUTH_MODE = MODE === 'sso' ? 'sso' : MODE === 'dev' ? 'dev' : 'mock'

// Mode dev saja. Backend menolak (404) bila bukan APP_ENV=local.
export async function devLogin(role) {
  return (await api('/dev-login', { method: 'POST', body: { role } })).data
}

// Login lokal (username/email + password) untuk akun demo/darurat. Password dikirim sekali ke backend (HTTPS di produksi),
// tidak disimpan di frontend. Backend membalas 401 bila salah, 429 bila terlalu banyak percobaan, 404 bila dimatikan.
export async function loginLokal({ username, password }) {
  try {
    return (await api('/auth/login', { method: 'POST', body: { username, password }, tanpaAlihan: true })).data
  } catch (e) {
    if (e.status === 404) throw new Error('Login dengan password dinonaktifkan. Gunakan SSO PNJ.')
    throw e
  }
}

// Alamat CAS PNJ. Layanan SSO hanya terdaftar untuk http://localhost:8081, jadi CAS mengembalikan
// browser ke FRONTEND (/auth/callback?ticket=...), lalu frontend meneruskan tiket ke backend.
export const CAS_URL = (import.meta.env.VITE_CAS_URL || 'https://dev-id.pnj.ac.id/cas').replace(/\/$/, '')
// `service` harus sama persis dengan CAS_SERVICE_URL di .env backend.
export const casService = () => `${location.origin}/auth/callback`

// Mode SSO: browser dialihkan ke halaman login CAS. Password diketik di CAS, tidak di aplikasi ini.
export function startSso() {
  location.assign(`${CAS_URL}/login?service=${encodeURIComponent(casService())}`)
}

// Mode SSO: backend memvalidasi tiket ke CAS lalu membuat sesi (cookie). Mengembalikan { nip, name, role, roles }.
export async function ssoValidate(ticket) {
  return (await api('/auth/sso/validate', { method: 'POST', body: { ticket }, tanpaAlihan: true })).data
}

// Mode SSO: ambil pengguna dari sesi cookie. Mengembalikan { nip, name, role } atau null.
export async function fetchMe() {
  try {
    return (await api('/me', { tanpaAlihan: true })).data
  } catch (e) {
    if (e.status === 401) return null
    throw e
  }
}

// Mode mock saja. Dipakai untuk mencoba tiap peran tanpa CAS.
export async function mockLogin({ nip, role }) {
  await new Promise((r) => setTimeout(r, 400))
  if (!nip) throw new Error('NIP wajib diisi.')
  // Contoh pengguna ber-peran ganda: dosen yang juga pimpinan (untuk mencoba Ganti Akun).
  const roles = role === 'dosen' ? ['dosen', 'pimpinan'] : [role]
  const NAMA = { dosen: 'Dosen B', pimpinan: 'User Pimpinan', keuangan: 'User Keuangan', admin: 'Admin Input', admin_super: 'Super Admin' }
  return { nip, name: NAMA[role] ?? 'Pengguna Demo', role, roles }
}

// Backend membalas { logout_url } (alamat logout CAS) supaya sesi SSO ikut berakhir.
export async function logout() {
  if (AUTH_MODE === 'mock') return null
  const res = await api('/logout', { method: 'POST' })
  return res?.logout_url ?? null
}

// Pindah peran aktif. Di mode SSO backend yang memutuskan (dan memeriksa bahwa pengguna memang
// memiliki peran itu); frontend tidak boleh menganggap pilihannya sah.
export async function switchRole(user, role) {
  if (AUTH_MODE === 'mock') {
    if (!user.roles?.includes(role)) throw new Error('Peran tidak tersedia.')
    return { ...user, role }
  }
  return (await api('/role', { method: 'POST', body: { role } })).data
}
