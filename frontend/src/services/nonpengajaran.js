// Honor kegiatan non-pengajaran (bimbingan, penguji, magang). Mode mock memakai data/nonpengajaran.js.
import { api, USE_MOCK } from './api.js'
import { npDosen } from '../data/nonpengajaran.js'

const q = (o) => o
const jumlah = (d) => d.kegiatan.length
const total = (d) => d.kegiatan.reduce((a, x) => a + x.honor, 0)

// Daftar dosen yang punya kegiatan. Filter jenis dikerjakan di server pada mode API; pencarian nama di halaman.
export async function getNpDaftar({ periode, jenis = '' } = {}) {
  if (USE_MOCK) {
    return npDosen
      .filter((d) => !jenis || d.kegiatan.some((x) => x.jenis === jenis))
      .map((d) => ({ id: d.id, nama: d.nama, jabatan: d.jabatan, jurusan: d.jurusan, jumlahKegiatan: jumlah(d),
        jenis: [...new Set(d.kegiatan.map((x) => x.jenis))], honor: total(d), status: d.status }))
  }
  return (await api('/non-pengajaran', { query: q({ periode, jenis }) })).data.dosen
}

// Detail satu dosen. id kosong = milik dosen yang sedang login.
export async function getNpDetail(id, periode) {
  if (USE_MOCK) {
    const d = npDosen.find((x) => x.id === (id || 'B'))
    if (!d) return null
    return { nama: d.nama, jabatan: d.jabatan, jurusan: d.jurusan, status: d.status, totalKegiatan: jumlah(d), totalHonor: total(d),
      kegiatan: d.kegiatan.map((x, i) => ({ no: i + 1, ...x })) }
  }
  try {
    return (await api(id ? `/non-pengajaran/${id}` : '/dosen/non-pengajaran', { query: q({ periode }) })).data
  } catch (e) {
    if (e.status === 404) return null
    throw e
  }
}

export async function verifikasiNp(id, periode) {
  if (USE_MOCK) {
    const d = npDosen.find((x) => x.id === id)
    if (d) d.status = 'terverifikasi'
    return
  }
  await api(`/non-pengajaran/${id}/verifikasi`, { method: 'POST', query: q({ periode }) })
}
