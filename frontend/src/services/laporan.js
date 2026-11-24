// Laporan & Ekspor per dosen (pengajaran, non-pengajaran, gabungan). Mode mock menyusun data dari data contoh.
import { api, API_URL, ApiError, USE_MOCK } from './api.js'
import { dosen as dosenMock } from '../data/mock.js'
import { npDosen } from '../data/nonpengajaran.js'

export const JENIS = [
  { kode: 'gabungan', label: 'Gabungan' },
  { kode: 'pengajaran', label: 'Pengajaran' },
  { kode: 'nonpengajaran', label: 'Non-Pengajaran' },
]

const uang = (k, judul) => ({ k, judul, tipe: 'uang' })
const DASAR = [{ k: 'no', judul: 'No', tipe: 'angka' }, { k: 'nama', judul: 'Nama dosen', tipe: 'teks' }, { k: 'jabatan', judul: 'Jabatan', tipe: 'teks' },
  { k: 'golongan', judul: 'Gol.', tipe: 'teks' }, { k: 'jurusan', judul: 'Jurusan', tipe: 'teks' }]
const STATUS = { k: 'status', judul: 'Status', tipe: 'teks' }
const pph = (gol) => (/IV/.test(gol || '') ? 0.15 : 0.05)

function mock(jenis, status, jurusan) {
  const np = (nama) => {
    const d = npDosen.find((x) => x.nama === nama)
    return d ? { bruto: d.kegiatan.reduce((a, k) => a + k.honor, 0), ok: d.status === 'terverifikasi' } : null
  }
  let rows = dosenMock.map((d) => {
    const n = np(d.nama)
    const pj = Math.round(d.honor * pph(d.golongan)), pn = n ? Math.round(n.bruto * pph(d.golongan)) : 0
    return { nama: d.nama, jabatan: d.jabatan, golongan: d.golongan.replace('Gol ', ''), jurusan: d.jurusan, sks_wajib: d.sksWajib, sks_terlaksana: d.sksWajib + d.sksLebih, sks_lebih: d.sksLebih,
      p_bruto: d.honor, bruto_p: d.honor, pajak_p: pj, netto_p: d.honor - pj, n_bruto: n?.bruto ?? 0, pajak_n: pn, netto_n: (n?.bruto ?? 0) - pn, jumlah_kegiatan: n ? 1 : 0,
      ok_p: d.status === 'terverifikasi', ok_n: n ? n.ok : true, ada_n: !!n }
  })
  if (jenis === 'nonpengajaran') rows = rows.filter((r) => r.ada_n)
  rows = rows.map((r) => {
    const ok = jenis === 'pengajaran' ? r.ok_p : jenis === 'nonpengajaran' ? r.ok_n : r.ok_p && r.ok_n
    const x = jenis === 'pengajaran' ? { bruto: r.bruto_p, pajak: r.pajak_p, netto: r.netto_p }
      : jenis === 'nonpengajaran' ? { bruto: r.n_bruto, pajak: r.pajak_n, netto: r.netto_n }
      : { bruto: r.bruto_p + r.n_bruto, pajak: r.pajak_p + r.pajak_n, netto: r.netto_p + r.netto_n }
    return { ...r, ...x, ok, status: ok ? 'Terverifikasi' : 'Menunggu' }
  })
  rows = rows.filter((r) => (!jurusan || r.jurusan === jurusan) && (status === 'semua' || (status === 'terverifikasi' ? r.ok : !r.ok)))
  const kolom = jenis === 'pengajaran'
    ? [...DASAR, { k: 'sks_wajib', judul: 'SKS wajib', tipe: 'desimal' }, { k: 'sks_terlaksana', judul: 'SKS terlaksana', tipe: 'desimal' }, { k: 'sks_lebih', judul: 'SKS lebih', tipe: 'desimal' }, uang('bruto', 'Honor bruto'), uang('pajak', 'Pajak'), uang('netto', 'Honor neto'), STATUS]
    : jenis === 'nonpengajaran'
      ? [...DASAR, { k: 'jumlah_kegiatan', judul: 'Kegiatan', tipe: 'angka' }, uang('bruto', 'Honor bruto'), uang('pajak', 'Pajak'), uang('netto', 'Honor neto'), STATUS]
      : [...DASAR, uang('p_bruto', 'Bruto pengajaran'), uang('n_bruto', 'Bruto non-pengajaran'), uang('bruto', 'Total bruto'), uang('pajak', 'Pajak'), uang('netto', 'Total neto'), STATUS]
  const baris = rows.map((r, i) => ({ ...r, no: i + 1 }))
  const total = { nama: `Total (${baris.length} dosen)` }
  for (const k of kolom.filter((c) => c.tipe !== 'teks' && c.k !== 'no')) total[k.k] = baris.reduce((a, r) => a + (r[k.k] || 0), 0)
  return { kolom, baris, total }
}

export async function getLaporan({ periode, jenis, jurusan = '', status = 'terverifikasi' }) {
  if (USE_MOCK) return mock(jenis, status, jurusan)
  return (await api('/laporan/data', { query: { periode, jenis, jurusan, status } })).data
}

export async function getRiwayatEkspor() {
  if (USE_MOCK) return mockRiwayat
  return (await api('/laporan/riwayat')).data
}
const mockRiwayat = []

// Unduh berkas: respons berupa berkas biner, jadi tidak lewat api() yang membaca JSON.
export async function unduhLaporan(format, { periode, jenis, jurusan = '', status = 'terverifikasi' }) {
  if (USE_MOCK) {
    const h = mock(jenis, status, jurusan)
    const csv = [h.kolom.map((k) => k.judul).join(';'), ...h.baris.map((r) => h.kolom.map((k) => r[k.k] ?? '').join(';'))].join('\n')
    simpan(new Blob(['﻿' + csv], { type: 'text/csv' }), `laporan-honor-${jenis}.csv`)
    mockRiwayat.unshift({ waktu: new Date().toLocaleString('id-ID'), objek: `${format.toUpperCase()} (contoh, CSV) | ${jenis} | ${periode}`, oleh: 'Keuangan' })
    return
  }
  const qs = new URLSearchParams(Object.entries({ format, periode, jenis, jurusan, status }).filter(([, v]) => v !== ''))
  const res = await fetch(`${API_URL}/api/laporan/ekspor?${qs}`, { credentials: 'include', headers: { Accept: 'application/json' } })
  if (!res.ok) {
    let msg = `Ekspor gagal (${res.status}).`
    try { msg = (await res.json()).message || msg } catch { /* bukan JSON */ }
    throw new ApiError(res.status, msg)
  }
  const nama = /filename="([^"]+)"/.exec(res.headers.get('Content-Disposition') || '')?.[1] || `laporan.${format}`
  simpan(await res.blob(), nama)
}

function simpan(blob, nama) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = nama
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 2000)
}
