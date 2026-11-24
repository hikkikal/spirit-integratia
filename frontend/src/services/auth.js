import { api } from './api.js'

// "sso"  = produksi: login lewat SSO PNJ (OpenID Connect), diselesaikan backend Laravel.
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

// Mode SSO (OpenID Connect, Authorization Code + PKCE): backend menyiapkan state/nonce/PKCE dan memberi alamat login SSO PNJ.
// Password diketik di halaman SSO, tidak di aplikasi ini. Setelah login, SSO mengembalikan browser ke
// {alamat frontend}/auth/callback?code=...&state=... (alamat ini harus terdaftar di SSO).
export async function startSso() {
  const res = await api('/auth/sso/mulai', { method: 'POST', tanpaAlihan: true })
  if (!res?.url) throw new Error('SSO tidak memberi alamat login.')
  location.assign(res.url)
}

// Mode SSO: backend menukar code dengan token, mencocokkan akun, lalu membuat sesi (cookie). Mengembalikan { nip, name, role, roles }.
export async function ssoCallback({ code, state }) {
  return (await api('/auth/sso/callback', { method: 'POST', body: { code, state }, tanpaAlihan: true })).data
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

// Backend membalas { logout_url } (alamat logout SSO) supaya sesi SSO ikut berakhir.
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
