<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { ROLE_INFO } from '../data/pengguna.js'
import { simpanRole } from '../services/super.js'
import TambahRoleModal from './TambahRoleModal.vue'

// Pop-up Kelola Role: tambah dan hapus role, lalu simpan. Perubahan baru berlaku setelah disimpan.
const props = defineProps({ pengguna: { type: Object, required: true } })
const emit = defineEmits(['close', 'tersimpan'])

const asli = [...props.pengguna.roles]
const roles = ref([...asli])
const baru = computed(() => roles.value.filter((r) => !asli.includes(r)))
const dihapus = computed(() => asli.filter((r) => !roles.value.includes(r)))
const jumlahUbah = computed(() => baru.value.length + dihapus.value.length)
// Tampilan: role aktif + role yang akan dihapus (tercoret) agar bisa dibatalkan.
const tampil = computed(() => [...roles.value, ...dihapus.value])
const pilihTambah = ref(false)
const error = ref('')

function tambah(daftar) {
  roles.value.push(...daftar.filter((r) => !roles.value.includes(r)))
  pilihTambah.value = false
}
function hapus(r) {
  // Setiap pengguna harus punya minimal satu role.
  if (roles.value.length <= 1) {
    error.value = 'Pengguna harus memiliki minimal satu role.'
    return
  }
  error.value = ''
  roles.value = roles.value.filter((x) => x !== r)
}
function pulihkan(r) {
  error.value = ''
  roles.value.push(r)
}
function batal() {
  roles.value = [...asli]
  error.value = ''
}
const menyimpan = ref(false)
async function simpan() {
  if (menyimpan.value) return
  menyimpan.value = true
  error.value = ''
  try {
    // Server memvalidasi ulang (mis. Admin Super terakhir tidak boleh dicabut) dan mencatat ke Log Aktivitas.
    emit('tersimpan', await simpanRole(props.pengguna.nip, roles.value))
    emit('close')
  } catch (e) {
    error.value = e.message
  }
  menyimpan.value = false
}
const inisial = (n) => n.split(/\s+/).map((k) => k[0]).join('').slice(0, 2).toUpperCase()
const onKey = (e) => e.key === 'Escape' && !pilihTambah.value && emit('close')
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="overlay" @click.self="emit('close')">
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="kr-title">
      <header>
        <div>
          <p class="crumb">Manajemen Pengguna › Kelola Role</p>
          <h2 id="kr-title">Kelola Role Pengguna</h2>
          <p class="sub">Tambahkan atau hapus role untuk pengguna aktif, lalu simpan perubahan</p>
        </div>
        <button class="x" aria-label="Tutup" @click="emit('close')">×</button>
      </header>

      <div class="cols">
        <div class="main">
          <section class="panel">
            <div class="who">
              <span class="ava" :class="{ on: jumlahUbah }">{{ inisial(pengguna.nama) }}</span>
              <div>
                <h3>{{ pengguna.nama }} <span class="badge">{{ pengguna.aktif ? 'Aktif' : 'Nonaktif' }}</span></h3>
                <p>{{ roles.map((r) => ROLE_INFO[r].label).join(' · ') }} · Politeknik Negeri Jakarta</p>
              </div>
            </div>
            <dl><dt>NIP</dt><dd>{{ pengguna.nip }}</dd><dt>Email</dt><dd>{{ pengguna.email }}</dd><dt>Login terakhir</dt><dd>{{ pengguna.loginTerakhir }}</dd></dl>
          </section>

          <section class="panel">
            <div class="rhead">
              <h4><span class="bar"></span>Role Pengguna <span class="count">{{ roles.length }} Role</span></h4>
              <button class="btn" @click="pilihTambah = true">+ Tambah Role</button>
            </div>
            <div v-for="r in tampil" :key="r" class="role" :class="{ 'role--new': baru.includes(r), 'role--del': dihapus.includes(r) }">
              <span class="ava sm" :class="{ on: jumlahUbah }">{{ ROLE_INFO[r].kode }}</span>
              <div class="rt"><strong>{{ ROLE_INFO[r].label }}</strong><small>{{ dihapus.includes(r) ? 'Akan dihapus setelah disimpan' : ROLE_INFO[r].ket }}</small></div>
              <button v-if="dihapus.includes(r)" class="btn btn--ghost btn--sm" @click="pulihkan(r)">Batalkan hapus</button>
              <button v-else class="btn btn--ghost btn--sm hapus" :aria-label="`Hapus role ${ROLE_INFO[r].label}`" @click="hapus(r)">Hapus</button>
            </div>
            <p v-if="error" class="err" role="alert">{{ error }}</p>
          </section>

          <section class="panel save" :class="{ 'save--dirty': jumlahUbah }">
            <span class="dot"></span>
            <strong>{{ jumlahUbah ? `${jumlahUbah} Perubahan belum disimpan` : 'Tidak ada Perubahan' }}</strong>
            <button class="btn btn--ghost" :disabled="!jumlahUbah" @click="batal">Batalkan Perubahan</button>
            <button class="btn" :disabled="!jumlahUbah || menyimpan" @click="simpan">{{ menyimpan ? 'Menyimpan…' : 'Simpan Perubahan' }}</button>
          </section>
        </div>

        <aside class="side">
          <section class="panel">
            <h4>Ringkasan Perubahan</h4>
            <p v-if="!jumlahUbah" class="muted">Belum ada perubahan. Klik “Tambah role” untuk menambahkan role baru, atau “Hapus” untuk mencabut role.</p>
            <ul v-else>
              <li v-for="r in baru" :key="'b' + r">Tambah role {{ ROLE_INFO[r].label }}</li>
              <li v-for="r in dihapus" :key="'d' + r">Hapus role {{ ROLE_INFO[r].label }}</li>
            </ul>
          </section>
          <section class="panel">
            <h4>Catatan</h4>
            <ul class="muted">
              <li>Perubahan baru berlaku setelah Anda menekan Simpan perubahan.</li>
              <li>Pengguna perlu login ulang agar menu role baru muncul.</li>
              <li>Setiap perubahan dicatat pada Log Aktivitas.</li>
              <li>Hindari menggabungkan role input dan role verifikasi pada satu pengguna.</li>
            </ul>
          </section>
        </aside>
      </div>
    </section>
    <TambahRoleModal v-if="pilihTambah" :dimiliki="roles" @close="pilihTambah = false" @lanjut="tambah" />
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: grid; place-items: center; z-index: 50; padding: 16px; }
.modal { background: #f6f5f1; border-radius: 12px; width: min(1100px, 100%); max-height: 94vh; overflow: auto; padding: 22px 26px; }
header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 14px; }
.crumb { font-size: 13px; color: #55554f; margin: 0 0 4px; }
h2 { margin: 0; font-size: 28px; font-weight: 500; }
.sub { margin: 2px 0 0; color: #55554f; font-size: 14px; }
.x { background: none; border: 0; font-size: 32px; line-height: 1; cursor: pointer; }
.cols { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; }
.main, .side { display: grid; gap: 16px; align-content: start; }
.who { display: flex; gap: 16px; align-items: center; margin-bottom: 14px; }
.who h3 { margin: 0; font-size: 26px; font-weight: 500; }
.who p { margin: 4px 0 0; }
.badge { font-size: 14px; background: #0d8177; color: #fff; border-radius: 99px; padding: 2px 16px; vertical-align: middle; }
.ava { width: 62px; height: 62px; border-radius: 50%; background: #d9d9d9; display: grid; place-items: center; font-weight: 700; font-size: 24px; flex: none; transition: background .3s, color .3s; }
.ava.sm { width: 48px; height: 48px; font-size: 17px; }
.ava.on { background: #0d8177; color: #fff; }
dl { display: grid; grid-template-columns: 130px 1fr; gap: 8px 0; margin: 0; }
dt { color: #55554f; } dd { margin: 0; }
.rhead { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
h4 { margin: 0 0 8px; font-size: 17px; display: flex; align-items: center; gap: 8px; }
.rhead h4 { margin: 0; }
.bar { width: 5px; height: 20px; background: #0d8177; border-radius: 3px; }
.count { background: #d6d8dc; border-radius: 99px; padding: 2px 12px; font-weight: 400; font-size: 15px; }
.role { display: flex; gap: 14px; align-items: center; background: #fff; border: 1px solid #000; border-radius: 8px; padding: 10px 18px; margin-top: 10px; animation: rise .3s ease both; }
.rt { flex: 1; display: grid; }
.rt small { color: #55554f; }
.role--new { background: #fdf0c8; border-color: #f2c94c; }
.role--del { background: #fbe4e1; border-color: #e5a29a; }
.role--del .rt strong { text-decoration: line-through; }
.hapus { color: #b42318; border-color: #b42318; }
.hapus:hover { background: #fdeceb; }
.err { color: #b42318; margin: 10px 0 0; font-size: 14px; }
.save { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.save strong { flex: 1; font-weight: 500; font-size: 18px; }
.dot { width: 22px; height: 22px; border-radius: 50%; background: #bfc3c9; }
.save--dirty { background: #fdf0c8; border-color: #f2c94c; }
.save--dirty .dot { background: #fbc02d; }
.muted { color: #55554f; }
ul { padding-left: 20px; margin: 6px 0 0; display: grid; gap: 6px; }
@media (max-width: 1000px) { .cols { grid-template-columns: 1fr; } }
</style>
