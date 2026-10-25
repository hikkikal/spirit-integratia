import { useRoute } from 'vue-router'

// Awalan rute peran saat ini, misalnya "/keuangan". Halaman yang dipakai bersama
// (Dashboard, Data Honor, Detail) memakai ini supaya tautannya tetap di area peran masing-masing.
export function useBase() {
  const route = useRoute()
  return '/' + route.path.split('/')[1]
}
