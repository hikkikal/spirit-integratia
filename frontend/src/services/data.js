// Lapisan data tunggal untuk halaman. Mode tiruan (VITE_USE_MOCK=true) memakai src/data/*;
// mode API memanggil backend Laravel. Bentuk hasilnya SAMA di kedua mode, jadi halaman tidak peduli.
import { api, USE_MOCK } from './api.js'
import { periodeOptions, jurusan as jurusanMock, honorTerverifikasi as verifMock, dosen as dosenMock, detailKelas, laporanJurusan, TARIF_KERJA_SAMA } from '../data/mock.js'
import { profil as profilMock, riwayat as riwayatMock, kelasPeriode, mingguPeriode, TARIF_REGULER } from '../data/dosen.js'
import { hitungBaris, hitungHonor } from '../lib/honor.js'

const q = (periode) => ({ periode })

export async function getPeriode() {
  if (USE_MOCK) return [...periodeOptions]
  return (await api('/periode')).data
}

export async function getDaftarDosen(periode) {
  if (USE_MOCK) return dosenMock.map((d) => ({ ...d }))
  return (await api('/data-honor', { query: q(periode) })).data.dosen
}

export async function getDashboard(periode) {
  if (USE_MOCK) {
    return { jurusan: jurusanMock, honorTerverifikasi: verifMock, laporan: laporanJurusan }
  }
  const d = (await api('/dashboard', { query: q(periode) })).data
  return { jurusan: d.jurusan, honorTerverifikasi: d.honorTerverifikasi, laporan: d.jurusan }
}

// Detail honor per kelas. id kosong = milik dosen yang sedang login.
export async function getDetailHonor(id, periode) {
  if (USE_MOCK) {
    const det = detailKelas[id || 'B']
    if (!det) return null
    const d = dosenMock.find((x) => x.id === (id || 'B'))
    return {
      id: d.id, nama: d.nama, jabatan: d.jabatan, golongan: d.golongan, status: d.status,
      sksWajib: d.sksWajib, sksLebih: d.sksLebih,
      tarifReguler: det.tarifReguler, tarifKerjaSama: TARIF_KERJA_SAMA, pajakKet: 'pajak 5% (sementara, belum dikonfirmasi)',
      kelas: det.kelas.map((k) => ({ ...k, ...hitungBaris(k, det.tarifReguler) })),
    }
  }
  const [h, p] = await Promise.all([
    api(id ? `/data-honor/${id}/detail` : '/dosen/honor', { query: q(periode) }).then((r) => r.data).catch((e) => { if (e.status === 404) return null; throw e }),
    api(id ? `/data-honor/${id}` : '/dosen/profil', { query: q(periode) }).then((r) => r.data),
  ])
  if (!h) return null
  const profil = id ? p.profil : p
  return {
    id, nama: h.nama, jabatan: (profil?.jabatan ?? '-').replace(/\s*\(Gol[^)]*\)\s*$/, ''), golongan: h.golongan ?? '-', status: h.status,
    sksWajib: h.sksWajib, sksLebih: h.sksLebih, tarifReguler: h.tarifReguler, tarifKerjaSama: h.tarifKerjaSama,
    pajakKet: 'pajak sesuai golongan (sementara, belum dikonfirmasi)',
    kelas: h.kelas.map((k) => ({ mk: k.mk, jenis: k.jenis, wajib: k.wajib, lebih100: k.lebih100, lebih50: k.lebih50, lebih0: k.lebih0, subtotal: k.bruto, pajak: k.pajak, honor: k.netto })),
  }
}

// Rincian satu dosen (halaman Data Honor → Detail).
export async function getRincianDosen(id, periode) {
  if (USE_MOCK) {
    const d = dosenMock.find((x) => x.id === id)
    if (!d) return null
    const ada = id === 'B'
    return {
      profil: { nama: d.nama, jabatan: `${d.jabatan} (${d.golongan})`, prodi: 'D4 Teknik Informatika', email: `${d.nama.toLowerCase().replace(' ', '')}@pnj.ac.id` },
      kelas: ada ? kelasPeriode[periode] ?? [] : [], minggu: ada ? mingguPeriode[periode] ?? [] : [],
      ringkasan: { totalKelas: ada ? (kelasPeriode[periode] ?? []).length : '-', sksTerlaksana: d.sksWajib + d.sksLebih, sksWajib: d.sksWajib, sksLebih: d.sksLebih, status: d.status },
    }
  }
  const d = (await api(`/data-honor/${id}`, { query: q(periode) }).catch((e) => { if (e.status === 404) return null; throw e }))?.data
  if (!d) return null
  return {
    profil: { nama: d.profil.nama, jabatan: d.profil.jabatan ?? '-', prodi: d.profil.jurusan ?? '-', email: d.profil.email ?? '-' },
    kelas: d.kelas, minggu: d.mingguan, ringkasan: d.ringkasan,
  }
}

export async function verifikasi(id, periode) {
  if (USE_MOCK) return
  await api(`/data-honor/${id}/verifikasi?periode=${encodeURIComponent(periode)}`, { method: 'POST' })
}

// ---- Dosen (milik sendiri) ----
export async function getRiwayat() {
  if (USE_MOCK) return riwayatMock
  return (await api('/dosen/riwayat')).data
}

export async function getKelasSaya(periode) {
  if (USE_MOCK) {
    const kelas = kelasPeriode[periode] ?? []
    const r = riwayatMock.find((x) => x.periode === periode)
    return { kelas, minggu: mingguPeriode[periode] ?? [], ringkasan: { totalKelas: kelas.length, sksTerlaksana: r?.terlaksana, sksWajib: r?.sksWajib, sksLebih: r?.lebih } }
  }
  const d = (await api('/dosen/kelas', { query: q(periode) })).data
  return { kelas: d.kelas, minggu: d.mingguan, ringkasan: d.ringkasan }
}

export async function getProfilSaya() {
  if (USE_MOCK) return profilMock
  const p = (await api('/dosen/profil')).data
  const s = (v) => v || '-'
  return {
    nama: p.nama, gelar: p.nama, jabatan: s(p.jabatan), prodi: s(p.jurusan), kampus: 'Politeknik Negeri Jakarta', email: s(p.email),
    nidn: s(p.nidn), sertifikat: s(p.nuptk), status: s(p.status), ttl: '-', hp: '-', alamatKampus: '-',
    jurusan: s(p.jurusan), jabatanFungsional: s(p.jabatan),
  }
}

export async function getTarif() {
  if (USE_MOCK) return null
  return (await api('/tarif')).data
}

export { TARIF_REGULER, hitungHonor }

// Honor satu periode untuk halaman Dosen: pakai angka backend bila ada, jika tidak hitung tiruan.
export function honorDariRiwayat(r) {
  if (r.bruto != null && r.sks100 != null) return { sks100: r.sks100, sks50: r.sks50, sks0: r.sks0, bruto: r.bruto, pajak: r.pajak, netto: r.netto }
  return hitungHonor(r.lebih, TARIF_REGULER)
}
