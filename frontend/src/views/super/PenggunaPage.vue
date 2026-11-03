<script setup>
import { computed, onMounted, ref } from 'vue'
import { ROLE_INFO } from '../../data/pengguna.js'
import { USE_MOCK } from '../../services/api.js'
import { getPengguna, ubahStatusPengguna } from '../../services/super.js'
import KelolaRoleModal from '../../components/KelolaRoleModal.vue'

const daftar = ref([])
const muat = ref(true)
const galat = ref('')
const sibuk = ref('') // NIP yang sedang diubah statusnya
async function ambil() {
  muat.value = true
  galat.value = ''
  try {
    daftar.value = await getPengguna()
  } catch (e) {
    galat.value = e.message
  }
  muat.value = false
}
onMounted(ambil)
function perbarui(baru) {
  const i = daftar.value.findIndex((p) => p.nip === baru.nip)
  if (i >= 0) daftar.value[i] = baru
}
const diubah = ref(null) // NIP pengguna yang rolenya sedang dikelola (pop-up)
const pengguna = computed(() => daftar.value.find((p) => p.nip === diubah.value))
const cari = ref('')
const role = ref('')
const status = ref('')

const tampil = computed(() => {
  const q = cari.value.trim().toLowerCase()
  return daftar.value.filter((p) =>
    (!q || p.nama.toLowerCase().includes(q) || p.nip.includes(q)) &&
    (!role.value || p.roles.includes(role.value)) &&
    (!status.value || (status.value === 'aktif') === p.aktif))
})

const hitung = (r) => daftar.value.filter((p) => p.aktif && p.roles.includes(r)).length
// Mode tiruan: angka ringkasan mengikuti desain (198 pengguna); daftar hanya contoh. Mode API: dihitung dari data.
const ringkas = computed(() =>
  USE_MOCK
    ? [['Pengguna aktif', 198], ['Dosen', 180], ['Admin Input', Math.max(3, hitung('admin'))], ['Keuangan', 4], ['Pimpinan / Super Admin', `8 / ${Math.max(3, hitung('admin_super'))}`]]
    : [['Pengguna aktif', daftar.value.filter((p) => p.aktif).length], ['Dosen', hitung('dosen')], ['Admin Input', hitung('admin')], ['Keuangan', hitung('keuangan')], ['Pimpinan / Super Admin', `${hitung('pimpinan')} / ${hitung('admin_super')}`]],
)
async function ubahStatus(p) {
  sibuk.value = p.nip
  galat.value = ''
  try {
    perbarui(await ubahStatusPengguna(p.nip, !p.aktif))
  } catch (e) {
    galat.value = e.message
  }
  sibuk.value = ''
}
</script>

<template>
  <h1 class="page-title">Manajemen Pengguna</h1>
  <p class="page-sub">Kelola akun, role, dan status pengguna</p>

  <div class="filters">
    <label>Cari<input v-model="cari" type="search" placeholder="Nama / NIP" /></label>
    <label>Role
      <select v-model="role">
        <option value="">Semua role</option>
        <option v-for="(r, k) in ROLE_INFO" :key="k" :value="k">{{ r.label }}</option>
      </select>
    </label>
    <label>Status
      <select v-model="status">
        <option value="">Semua</option>
        <option value="aktif">Aktif</option>
        <option value="nonaktif">Nonaktif</option>
      </select>
    </label>
  </div>

  <p v-if="galat" class="galat" role="alert">{{ galat }}</p>
  <section class="stats">
    <div v-for="[k, v] in ringkas" :key="k" class="panel stat"><div class="lbl">{{ k }}</div><div class="val">{{ v }}</div></div>
  </section>

  <section class="panel">
    <h2>Daftar pengguna</h2>
    <div class="scroll-x">
      <table class="tbl tbl--line">
        <thead><tr><th>NIP</th><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          <tr v-for="p in tampil" :key="p.nip">
            <td>{{ p.nip }}</td><td>{{ p.nama }}</td><td>{{ p.email }}</td>
            <td><span v-for="r in p.roles" :key="r" class="tag">{{ ROLE_INFO[r].label }}</span></td>
            <td><span :class="p.aktif ? 'badge-ok' : 'tag tag--off'">{{ p.aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
            <td><div class="aksi">
              <button class="btn btn--ghost btn--sm" @click="diubah = p.nip">Ubah role</button>
              <button v-if="!p.roles.includes('admin_super')" class="btn btn--ghost btn--sm" @click="ubahStatus(p)">{{ p.aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
            </div></td>
          </tr>
          <tr v-if="muat"><td colspan="6">Memuat…</td></tr>
          <tr v-else-if="!tampil.length"><td colspan="6">Tidak ada pengguna yang cocok.</td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <KelolaRoleModal v-if="pengguna" :key="pengguna.nip" :pengguna="pengguna" @tersimpan="perbarui" @close="diubah = null" />
</template>

<style scoped>
.stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 20px; }
.stat { padding: 14px 16px; }
.lbl { font-size: 14px; color: #55554f; }
.val { font-size: 30px; font-weight: 600; margin-top: 8px; }
h2 { font-size: 18px; margin: 0 0 8px; }
.galat { color: #b3261e; margin: 0 0 12px; }
.aksi { display: flex; gap: 8px; flex-wrap: wrap; text-decoration: none; }
.aksi a { text-decoration: none; }
@media (max-width: 1100px) { .stats { grid-template-columns: repeat(2, 1fr); } }
</style>
