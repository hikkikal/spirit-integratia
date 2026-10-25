import { API_URL, api } from './api.js'

// "sso"  = produksi: login lewat CAS PNJ, divalidasi backend Laravel.
// "mock" = pengembangan: form tiruan, tanpa backend dan tanpa password sungguhan.
export const AUTH_MODE = import.meta.env.VITE_AUTH_MODE === 'sso' ? 'sso' : 'mock'

// Mode SSO: browser dialihkan ke backend, backend ke CAS. Tidak ada password lewat aplikasi ini.
export function startSso() {
  location.assign(`${API_URL}/auth/sso/login`)
}

// Mode SSO: ambil pengguna dari sesi cookie. Mengembalikan { nip, name, role } atau null.
export async function fetchMe() {
  try {
    return (await api('/me')).data
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
  return { nip, name: role === 'dosen' ? 'Dosen B' : `Pengguna Demo (${role})`, role, roles }
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
