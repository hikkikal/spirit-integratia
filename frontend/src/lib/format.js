const nf = new Intl.NumberFormat('id-ID')

export const angka = (n) => nf.format(n)
export const rupiah = (n) => `Rp${nf.format(n)}`
export const persen = (bagian, total) => (total ? Math.round((bagian / total) * 100) : 0)

const BLN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
// "2026-12-31" -> "31 Desember 2026" (tanpa zona waktu, supaya tanggal tidak bergeser sehari).
export function tanggalPanjang(iso) {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '')
  return m ? `${Number(m[3])} ${BLN[Number(m[2]) - 1]} ${m[1]}` : '-'
}
export const ukuranFile = (b) => (b >= 1048576 ? `${(b / 1048576).toFixed(1).replace('.', ',')} MB` : `${Math.max(1, Math.round(b / 1024))} KB`)
export const hariIni = () => {
  const t = new Date()
  return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`
}
