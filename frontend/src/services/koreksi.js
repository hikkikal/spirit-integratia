// Koreksi manual jabatan fungsional, golongan, dan tugas tambahan satu dosen. Mode mock: disimpan di memori saja.
import { api, USE_MOCK } from './api.js'
import { dosen as dosenMock } from '../data/mock.js'

const OPSI_JABATAN = ['Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Guru Besar'].map((n) => ({ id: n, nama: n }))
const mock = {}
function profilMock(id) {
  if (!mock[id]) {
    const d = dosenMock.find((x) => x.id === id) ?? dosenMock[0]
    const jab = OPSI_JABATAN.find((j) => d.jabatan?.startsWith(j.nama))
    mock[id] = { id, nama: d.nama, jabatan: { id: jab?.id ?? null, nama: jab?.nama ?? null, manual: false },
      golongan: { nilai: d.golongan?.replace('Gol ', '') ?? null, manual: false }, koreksiTerakhir: null,
      tugas: [{ id: `s-${id}`, jenis: 'Kepala Laboratorium', unit: 'Akuntansi', mulai: '2025-08-05', selesai: '2029-08-04', sumber: 'sister', alasan: null, kelompok: null, kelompokManual: false, kelompokPeta: null, diabaikan: false }] }
  }
  return { ...mock[id], opsiJabatan: OPSI_JABATAN, opsiGolongan: ['III', 'IV'], opsiKelompok: [1, 2, 3] }
}
const hasilMock = (id) => ({ data: profilMock(id), hitung: { periode: 'contoh', dihitungUlang: false, verifikasiDireset: false, catatan: ['Mode contoh: tidak ada perhitungan.'] } })

const dasar = (id) => `/dosen-koreksi/${encodeURIComponent(id)}`
const q = (periode) => ({ periode })

export async function getKoreksi(id, periode) {
  if (USE_MOCK) return profilMock(id)
  return (await api(dasar(id), { query: q(periode) })).data
}
export async function ubahJabatan(id, idJabfung, alasan, periode) {
  if (USE_MOCK) {
    const o = OPSI_JABATAN.find((j) => j.id === idJabfung)
    profilMock(id); mock[id].jabatan = { id: o?.id ?? null, nama: o?.nama ?? null, manual: !!o }
    return hasilMock(id)
  }
  return api(`${dasar(id)}/jabatan`, { method: 'PUT', body: { id_jabfung: idJabfung || null, alasan }, query: q(periode) })
}
export async function ubahGolongan(id, golongan, alasan, periode) {
  if (USE_MOCK) { profilMock(id); mock[id].golongan = { nilai: golongan || null, manual: !!golongan }; return hasilMock(id) }
  return api(`${dasar(id)}/golongan`, { method: 'PUT', body: { golongan: golongan || null, alasan }, query: q(periode) })
}
export async function tambahTugas(id, t, alasan, periode) {
  if (USE_MOCK) {
    profilMock(id)
    mock[id].tugas.push({ id: `m${Date.now()}`, jenis: t.nama, unit: t.unit || null, mulai: t.mulai || null, selesai: t.selesai || null, sumber: 'manual', alasan, kelompok: Number(t.kelompok), kelompokManual: true, kelompokPeta: null, diabaikan: false })
    return hasilMock(id)
  }
  return api(`${dasar(id)}/tugas`, { method: 'POST', body: { jenis_tugas: t.nama, kelompok: Number(t.kelompok), unit_kerja: t.unit || null, tanggal_mulai: t.mulai || null, tanggal_selesai: t.selesai || null, alasan }, query: q(periode) })
}
export async function hapusTugas(id, tugas, alasan, periode) {
  if (USE_MOCK) {
    profilMock(id)
    const t = mock[id].tugas.find((x) => x.id === tugas)
    if (t?.sumber === 'manual') mock[id].tugas = mock[id].tugas.filter((x) => x !== t)
    else if (t) t.diabaikan = true
    return hasilMock(id)
  }
  return api(`${dasar(id)}/tugas/${encodeURIComponent(tugas)}/hapus`, { method: 'POST', body: { alasan }, query: q(periode) })
}
export async function pulihkanTugas(id, tugas, alasan, periode) {
  if (USE_MOCK) { profilMock(id); const t = mock[id].tugas.find((x) => x.id === tugas); if (t) t.diabaikan = false; return hasilMock(id) }
  return api(`${dasar(id)}/tugas/${encodeURIComponent(tugas)}/pulihkan`, { method: 'POST', body: { alasan }, query: q(periode) })
}
export async function kelompokTugas(id, tugas, kelompok, alasan, periode) {
  if (USE_MOCK) {
    profilMock(id)
    const t = mock[id].tugas.find((x) => x.id === tugas)
    if (t) { t.kelompok = kelompok ? Number(kelompok) : t.kelompokPeta; t.kelompokManual = !!kelompok }
    return hasilMock(id)
  }
  return api(`${dasar(id)}/tugas/${encodeURIComponent(tugas)}/kelompok`, { method: 'POST', body: { kelompok: kelompok ? Number(kelompok) : null, alasan }, query: q(periode) })
}
