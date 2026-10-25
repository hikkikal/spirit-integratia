const nf = new Intl.NumberFormat('id-ID')

export const angka = (n) => nf.format(n)
export const rupiah = (n) => `Rp${nf.format(n)}`
export const persen = (bagian, total) => (total ? Math.round((bagian / total) * 100) : 0)
