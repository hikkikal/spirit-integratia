// Peran pengguna. Nilai harus sama dengan kolom `role` di backend.
export const ROLES = {
  keuangan: { label: 'Bidang Keuangan', chip: 'BIDANG KEUANGAN', base: '/keuangan' },
  pimpinan: { label: 'Pimpinan', chip: 'PIMPINAN', base: '/pimpinan' },
  admin: { label: 'Admin Input Data', chip: 'Admin Input Data', base: '/admin' },
  dosen: { label: 'Dosen', chip: 'DOSEN', base: '/dosen' },
  admin_super: { label: 'Admin Super', chip: 'Admin Super', base: '/admin-super' },
}

const HOME = { admin_super: 'pengguna' }
export const homeFor = (role) => (ROLES[role] ? `${ROLES[role].base}/${HOME[role] ?? 'dashboard'}` : '/login')

// Menu sidebar per peran. TODO: samakan dengan desain Figma Admin Input dan Dosen.
export const MENUS = {
  keuangan: [
    { label: 'Dashboard', to: 'dashboard', match: 'dashboard' },
    { label: 'Data Dosen', to: 'data-honor', match: 'honor' },
    { label: 'Laporan & Ekspor', to: 'laporan', match: 'laporan' },
    { label: 'Tarif & Ketentuan', to: 'tarif', match: 'tarif' },
  ],
  pimpinan: [
    { label: 'Dashboard', to: 'dashboard', match: 'dashboard' },
    { label: 'Data Dosen', to: 'data-honor', match: 'honor' },
    { label: 'Laporan', to: 'laporan', match: 'laporan' },
    { label: 'Tarif & Ketentuan', to: 'tarif', match: 'tarif' },
  ],
  admin: [
    { label: 'Dashboard', to: 'dashboard', match: 'dashboard' },
    { label: 'Master Tarif', to: 'master-tarif', match: 'tarif' },
    { label: 'Dokumen Perdir', to: 'perdir', match: 'perdir' },
  ],
  admin_super: [
    { label: 'Kelola Pengguna', to: 'pengguna', match: 'pengguna' },
    { label: 'Log Aktivitas', to: 'log', match: 'log' },
  ],
  dosen: [
    { label: 'Dashboard Saya', to: 'dashboard', match: 'dashboard' },
    { label: 'Rincian Kelas', to: 'rincian', match: 'rincian' },
    { label: 'Riwayat Honor', to: 'riwayat', match: 'riwayat' },
  ],
}
