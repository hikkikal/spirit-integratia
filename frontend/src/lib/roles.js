// Peran pengguna. Nilai harus sama dengan kolom `role` di backend.
export const ROLES = {
  keuangan: { label: 'Bidang Keuangan', chip: 'BIDANG KEUANGAN', base: '/keuangan' },
  pimpinan: { label: 'Pimpinan', chip: 'PIMPINAN', base: '/pimpinan' },
  admin: { label: 'Admin Input', chip: 'ADMIN INPUT', base: '/admin' },
  dosen: { label: 'Dosen', chip: 'DOSEN', base: '/dosen' },
}

export const homeFor = (role) => (ROLES[role] ? `${ROLES[role].base}/dashboard` : '/login')

// Menu sidebar per peran. TODO: samakan dengan desain Figma Admin Input dan Dosen.
export const MENUS = {
  keuangan: [
    { label: 'Dashboard', to: 'dashboard', match: 'dashboard' },
    { label: 'Data Dosen', to: 'data-honor', match: 'honor' },
    { label: 'Laporan & Ekspor', to: 'laporan', match: 'laporan' },
  ],
  pimpinan: [
    { label: 'Dashboard', to: 'dashboard', match: 'dashboard' },
    { label: 'Data Dosen', to: 'data-honor', match: 'honor' },
  ],
  admin: [
    { label: 'Dashboard', to: 'dashboard', match: 'dashboard' },
    { label: 'Data Dosen', to: 'data-honor', match: 'honor' },
  ],
  dosen: [
    { label: 'Dashboard Saya', to: 'dashboard', match: 'dashboard' },
    { label: 'Rincian Kelas', to: 'rincian', match: 'rincian' },
    { label: 'Riwayat Honor', to: 'riwayat', match: 'riwayat' },
  ],
}
