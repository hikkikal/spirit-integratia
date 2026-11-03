// Lapisan data Admin Input. Mode tiruan memakai src/data/admin.js; mode API memanggil backend.
// Kontrak endpoint: docs/ADMIN_INPUT_API.md (backend: AdminInputController).
import { api, USE_MOCK } from './api.js'
import { koneksiSister, logSinkron, buatLog, tarifList, perdirList, PESAN_TIMEOUT } from '../data/admin.js'

const tunda = (ms) => new Promise((r) => setTimeout(r, ms))
const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
const dua = (n) => String(n).padStart(2, '0')
const stempel = (t = new Date()) => `${dua(t.getDate())} ${BULAN[t.getMonth()]} ${t.getFullYear()}, ${dua(t.getHours())}:${dua(t.getMinutes())}`

// ---------- Sinkronisasi ----------
export async function getKoneksi() {
  if (USE_MOCK) return { ...koneksiSister }
  return (await api('/admin/sinkron/koneksi')).data
}

export async function getRiwayatSinkron() {
  if (USE_MOCK) return logSinkron.map(({ id, mulai, status }) => ({ id, mulai, status }))
  return (await api('/admin/sinkron')).data
}

export async function getLogSinkron(id) {
  if (USE_MOCK) return logSinkron.find((l) => l.id === id) ?? null
  return (await api(`/admin/sinkron/${id}`).catch((e) => { if (e.status === 404) return { data: null }; throw e })).data
}

// Sinkronisasi berjalan di server; frontend memulai lalu memeriksa kemajuannya (polling).
const DURASI_LANGKAH = 1100
const proses = {}

export async function mulaiSinkron({ simulasiGagal = false } = {}) {
  if (!USE_MOCK) return (await api('/admin/sinkron', { method: 'POST', body: { simulasiGagal } })).data // { id }; simulasiGagal hanya dipakai server bila APP_ENV=local
  const id = `run${Date.now()}`
  proses[id] = { mulai: Date.now(), gagal: simulasiGagal, dicatat: false }
  return { id }
}

// Mengembalikan { status: 'berjalan'|'berhasil'|'gagal', persen, langkah: [4 status], logId }.
export async function cekSinkron(id) {
  if (!USE_MOCK) return (await api(`/admin/sinkron/${id}/status`)).data
  const p = proses[id]
  const lewat = Date.now() - p.mulai
  const berhenti = p.gagal ? 2 : 0 // simulasi: gagal di langkah 2 (SISTER tidak membalas)
  const langkah = [0, 1, 2, 3].map((i) => {
    if (berhenti && i + 1 > berhenti) return 'menunggu'
    if (lewat >= (i + 1) * DURASI_LANGKAH) return berhenti && i + 1 === berhenti ? 'gagal' : 'selesai'
    return lewat >= i * DURASI_LANGKAH ? 'berjalan' : 'menunggu'
  })
  const selesai = berhenti ? lewat >= berhenti * DURASI_LANGKAH : lewat >= 4 * DURASI_LANGKAH
  const persen = berhenti ? Math.min(100, Math.round((Math.min(lewat, berhenti * DURASI_LANGKAH) / (4 * DURASI_LANGKAH)) * 100)) : Math.min(100, Math.round((lewat / (4 * DURASI_LANGKAH)) * 100))
  if (!selesai) return { status: 'berjalan', persen, langkah, logId: null }
  if (!p.dicatat) {
    p.dicatat = true
    const t = new Date(p.mulai)
    p.logId = `s${Date.now()}`
    logSinkron.unshift(buatLog({ id: p.logId, mulai: stempel(t), durasi: `${Math.max(1, Math.round(lewat / 1000))} detik`, berhenti: berhenti ? 4 : 0, pesan: PESAN_TIMEOUT }))
    koneksiSister.terakhir = stempel(t)
    koneksiSister.status = berhenti ? 'Gagal' : 'Terhubung'
  }
  return { status: berhenti ? 'gagal' : 'berhasil', persen: berhenti ? persen : 100, langkah, logId: p.logId }
}

// ---------- Master tarif ----------
export async function getTarif() {
  if (USE_MOCK) return tarifList.map((t) => ({ ...t }))
  return (await api('/admin/tarif')).data
}

// t.id kosong = tambah baru, ada = ubah.
export async function simpanTarif(t) {
  if (!USE_MOCK) {
    const r = await api(t.id ? `/admin/tarif/${t.id}` : '/admin/tarif', { method: t.id ? 'PUT' : 'POST', body: t })
    return r.data
  }
  await tunda(250)
  if (t.id) {
    const i = tarifList.findIndex((x) => x.id === t.id)
    if (i >= 0) tarifList[i] = { ...t }
    return { ...t }
  }
  const baru = { ...t, id: `t${Date.now()}` }
  tarifList.unshift(baru)
  return { ...baru }
}

// ---------- Dokumen Perdir ----------
export async function getPerdir() {
  if (USE_MOCK) return perdirList.map((p) => ({ ...p }))
  return (await api('/admin/perdir')).data
}

// berkas = objek File dari input. Mode tiruan hanya menyimpan nama berkasnya.
export async function simpanPerdir({ nomor, judul, tanggal, berkas }) {
  if (!USE_MOCK) {
    const fd = new FormData()
    fd.append('nomor', nomor)
    fd.append('judul', judul)
    fd.append('tanggal', tanggal)
    fd.append('berkas', berkas)
    return (await api('/admin/perdir', { method: 'POST', body: fd })).data
  }
  await tunda(300)
  const baru = { id: `p${Date.now()}`, nomor, judul, tanggal, berkas: berkas.name, berlaku: true }
  perdirList.unshift(baru)
  return { ...baru }
}

export async function ubahStatusPerdir(id, berlaku) {
  if (!USE_MOCK) return (await api(`/admin/perdir/${id}`, { method: 'PATCH', body: { berlaku } })).data
  const p = perdirList.find((x) => x.id === id)
  if (p) p.berlaku = berlaku
  return p ? { ...p } : null
}

export async function hapusPerdir(id) {
  if (!USE_MOCK) return api(`/admin/perdir/${id}`, { method: 'DELETE' })
  const i = perdirList.findIndex((x) => x.id === id)
  if (i >= 0) perdirList.splice(i, 1)
}
