// Rincian per minggu + pencocokan SISTER. Data akademik ditarik oleh sinkronisasi di server; di sini hanya dibaca.
import { api, USE_MOCK } from './api.js'

const dasar = (dosenId) => (dosenId ? `/data-honor/${dosenId}/academia` : '/dosen/academia')

// Hasil tersimpan (null bila belum pernah disinkronkan).
export async function getAcademia(dosenId, periode) {
  if (USE_MOCK) return null
  return (await api(dasar(dosenId), { query: { periode } })).data
}
