// Aturan hitung honor (Kepdir 638/2026): SKS lebih dibayar 100% untuk 6 SKS pertama,
// 50% untuk 4 SKS berikutnya, sisanya tidak dibayar.
export const BATAS_100 = 6
export const BATAS_50 = 4

// TODO: aturan pajak belum dikonfirmasi ke pihak berwenang. 5% hanya mengikuti contoh di Figma.
export const TARIF_PAJAK = 0.05

// Menghitung honor satu baris kelas dari jumlah SKS per kategori pembayaran.
export function hitungBaris(kelas, tarifReguler) {
  const subtotal = kelas.lebih100 * tarifReguler + kelas.lebih50 * tarifReguler * 0.5
  const pajak = Math.round(subtotal * TARIF_PAJAK)
  return { subtotal, pajak, honor: subtotal - pajak }
}

// Honor SKS lebih satu periode: 6 SKS pertama 100%, 4 SKS berikutnya 50%, sisanya tidak dibayar.
// Hanya untuk tampilan tiruan. Di produksi angka ini dihitung dan dikirim oleh backend.
export function hitungHonor(sksLebih, tarif) {
  const sks100 = Math.min(sksLebih, BATAS_100)
  const sks50 = Math.min(Math.max(sksLebih - BATAS_100, 0), BATAS_50)
  const sks0 = Math.max(sksLebih - BATAS_100 - BATAS_50, 0)
  const bruto = sks100 * tarif + sks50 * tarif * 0.5
  const pajak = Math.round(bruto * TARIF_PAJAK)
  return { sks100, sks50, sks0, bruto, pajak, netto: bruto - pajak }
}
