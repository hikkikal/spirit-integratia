import { reactive } from 'vue'
import { AUTH_MODE, fetchMe } from '../services/auth.js'

const KEY = 'integratia.user'

// Mode mock menyimpan pengguna di sessionStorage supaya refresh tidak logout.
// Mode SSO tidak menyimpan apa pun di browser: sumber kebenarannya sesi cookie di backend.
function baca() {
  if (AUTH_MODE !== 'mock') return null
  try {
    return JSON.parse(sessionStorage.getItem(KEY))
  } catch {
    return null
  }
}

export const auth = reactive({ user: baca(), loaded: AUTH_MODE === 'mock' })

export function setUser(user) {
  auth.user = user
  if (AUTH_MODE !== 'mock') return
  try {
    if (user) sessionStorage.setItem(KEY, JSON.stringify(user))
    else sessionStorage.removeItem(KEY)
  } catch {
    /* penyimpanan browser bisa saja tidak tersedia */
  }
}

// Dipanggil guard router sebelum halaman pertama. Mode SSO menanyakan backend siapa penggunanya.
export async function ensureUser() {
  if (auth.loaded) return auth.user
  auth.user = await fetchMe()
  auth.loaded = true
  return auth.user
}
