// Klien HTTP tunggal. Semua pemanggilan ke backend lewat sini.
// Sesi memakai cookie (Laravel Sanctum mode SPA), jadi tidak ada token di JavaScript.
export const API_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8000').replace(/\/$/, '')
export const USE_MOCK = import.meta.env.VITE_USE_MOCK !== 'false'

function csrfToken() {
  const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)
  return m ? decodeURIComponent(m[1]) : ''
}

export class ApiError extends Error {
  constructor(status, message) {
    super(message)
    this.status = status
  }
}

// Sanctum SPA: sebelum permintaan tulis pertama, minta cookie XSRF-TOKEN sekali.
let csrfSiap = false
async function pastikanCsrf() {
  if (csrfSiap) return
  await fetch(`${API_URL}/sanctum/csrf-cookie`, { credentials: 'include' })
  csrfSiap = true
}

// export async function api(path, { method = 'GET', body, query } = {}) {
//   if (method !== 'GET') await pastikanCsrf()
//   const qs = query ? '?' + new URLSearchParams(Object.entries(query).filter(([, v]) => v !== '' && v != null)) : ''
//   const res = await fetch(`${API_URL}/api${path}${qs}`, {
//     method,
//     credentials: 'include',
//     headers: {
//       Accept: 'application/json',
//       ...(body && !(body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
//       ...(method !== 'GET' ? { 'X-XSRF-TOKEN': csrfToken() } : {}),
//     },
//     body: body instanceof FormData ? body : body ? JSON.stringify(body) : undefined,
//   })
//   if (res.status === 401) {
//     // Sesi habis: bersihkan dan kembali ke login.
//     const { setUser } = await import('../stores/auth.js')
//     setUser(null)
//     if (location.pathname !== '/login') location.assign('/login')
//   }
//   if (!res.ok) {
//     let msg = `Permintaan gagal (${res.status}).`
//     try {
//       msg = (await res.json()).message || msg
//     } catch {
//       /* respons bukan JSON */
//     }
//     throw new ApiError(res.status, msg)
//   }
//   return res.status === 204 ? null : res.json()
// }

// tanpaAlihan: untuk pemanggilan yang 401-nya memang diharapkan (cek sesi, validasi tiket SSO);
// penanggilnya yang menangani, bukan pengalihan paksa ke /login.
export async function api(path, { method = 'GET', body, query, tanpaAlihan = false } = {}) {
  if (method !== 'GET') await pastikanCsrf()
  const qs = query ? '?' + new URLSearchParams(Object.entries(query).filter(([, v]) => v !== '' && v != null)) : ''
  const res = await fetch(`${API_URL}/api${path}${qs}`, {
    method,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(body && !(body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
      ...(method !== 'GET' ? { 'X-XSRF-TOKEN': csrfToken() } : {}),
    },
    body: body instanceof FormData ? body : body ? JSON.stringify(body) : undefined,
  })
  if (res.status === 401 && !tanpaAlihan) {
    // Sesi habis: bersihkan dan kembali ke login.
    const { setUser } = await import('../stores/auth.js')
    setUser(null)
    if (location.pathname !== '/login') location.assign('/login')
  }
  if (!res.ok) {
    let msg = `Permintaan gagal (${res.status}).`
    try {
      msg = (await res.json()).message || msg
    } catch {
      /* respons bukan JSON */
    }
    throw new ApiError(res.status, msg)
  }
  return res.status === 204 ? null : res.json()
}