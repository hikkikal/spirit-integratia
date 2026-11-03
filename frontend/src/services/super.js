// Lapisan data Admin Super (pengguna, role, log aktivitas). Mode tiruan memakai src/data/pengguna.js.
// Kontrak endpoint: docs/ADMIN_SUPER_API.md (backend: AdminSuperController).
import { api, USE_MOCK } from './api.js'
import { penggunaList, logList, catatLog } from '../data/pengguna.js'
import { auth } from '../stores/auth.js'

export async function getPengguna() {
  if (USE_MOCK) return penggunaList.map((p) => ({ ...p, roles: [...p.roles] }))
  return (await api('/admin-super/pengguna')).data
}

// Mengganti seluruh daftar role pengguna. Mengembalikan baris pengguna terbaru.
export async function simpanRole(nip, roles) {
  if (!USE_MOCK) return (await api(`/admin-super/pengguna/${encodeURIComponent(nip)}/role`, { method: 'PUT', body: { roles } })).data
  const p = penggunaList.find((x) => x.nip === nip)
  if (roles.length < 1) throw new Error('Pengguna harus memiliki minimal satu role.')
  roles.filter((r) => !p.roles.includes(r)).forEach(() => catatLog(auth.user, 'Tambah role'))
  p.roles.filter((r) => !roles.includes(r)).forEach(() => catatLog(auth.user, 'Hapus role'))
  p.roles = [...roles]
  return { ...p, roles: [...p.roles] }
}

export async function ubahStatusPengguna(nip, aktif) {
  if (!USE_MOCK) return (await api(`/admin-super/pengguna/${encodeURIComponent(nip)}`, { method: 'PATCH', body: { aktif } })).data
  const p = penggunaList.find((x) => x.nip === nip)
  p.aktif = aktif
  catatLog(auth.user, aktif ? 'Aktifkan pengguna' : 'Nonaktifkan pengguna')
  return { ...p, roles: [...p.roles] }
}

// hari: 7 | 30 | 0 (semua). status: '' | 'Berhasil' | 'Gagal'. Pencarian nama dilakukan server (mode API).
export async function getLogAktivitas({ hari = 7, status = '', q = '' } = {}) {
  if (!USE_MOCK) return (await api('/admin-super/log', { query: { hari, status, q } })).data
  const cari = q.trim().toLowerCase()
  return logList.filter((l) => (!cari || l.nama.toLowerCase().includes(cari)) && (!status || l.status === status)).map((l) => ({ ...l }))
}
