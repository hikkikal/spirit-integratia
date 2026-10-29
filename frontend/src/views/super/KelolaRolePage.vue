<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { penggunaList, ROLE_INFO } from '../../data/pengguna.js'

const props = defineProps({ nip: { type: String, required: true } })
const router = useRouter()
const pengguna = penggunaList.find((p) => p.nip === props.nip)

const asli = pengguna ? [...pengguna.roles] : []
const roles = ref([...asli])
const baru = computed(() => roles.value.filter((r) => !asli.includes(r)))
const tersedia = computed(() => Object.keys(ROLE_INFO).filter((r) => !roles.value.includes(r)))
const pilih = ref(false)
const ubah = computed(() => baru.value.length)

function tambah(r) {
  roles.value.push(r)
  pilih.value = false
}
function batal() {
  roles.value = [...asli]
}
function simpan() {
  // Demo: backend nanti memvalidasi dan mencatat ke Log Aktivitas.
  if (pengguna) pengguna.roles = [...roles.value]
  router.push('/admin-super/pengguna')
}
const inisial = (n) => n.split(/\s+/).map((k) => k[0]).join('').slice(0, 2).toUpperCase()
</script>

<template>
  <p v-if="!pengguna">Pengguna tidak ditemukan. <RouterLink to="/admin-super/pengguna">Kembali</RouterLink></p>
  <template v-else>
    <div class="top">
      <div>
        <p class="crumb">Manajemen Pengguna › Kelola Role</p>
        <h1 class="page-title">Kelola Role Pengguna</h1>
        <p class="page-sub">Tambahkan role untuk pengguna aktif, lalu simpan perubahan</p>
      </div>
      <RouterLink to="/admin-super/pengguna" class="btn btn--ghost">← Kembali ke daftar pengguna</RouterLink>
    </div>

    <div class="cols">
      <div class="main">
        <section class="panel">
          <div class="who">
            <span class="ava" :class="{ on: ubah }">{{ inisial(pengguna.nama) }}</span>
            <div>
              <h2>{{ pengguna.nama }} <span class="badge">{{ pengguna.aktif ? 'Aktif' : 'Nonaktif' }}</span></h2>
              <p>{{ roles.map((r) => ROLE_INFO[r].label).join(' · ') }} · Politeknik Negeri Jakarta</p>
            </div>
          </div>
          <dl><dt>NIP</dt><dd>{{ pengguna.nip }}</dd><dt>Email</dt><dd>{{ pengguna.email }}</dd><dt>Login terakhir</dt><dd>{{ pengguna.loginTerakhir }}</dd></dl>
        </section>

        <section class="panel">
          <div class="rhead">
            <h3><span class="bar"></span>Role Pengguna <span class="count">{{ roles.length }} Role</span></h3>
            <button class="btn" @click="pilih = !pilih">+ Tambah Role</button>
          </div>
          <div v-if="pilih" class="pick">
            <p v-if="!tersedia.length">Semua role sudah dimiliki.</p>
            <button v-for="r in tersedia" :key="r" class="btn btn--ghost" @click="tambah(r)">{{ ROLE_INFO[r].label }}</button>
          </div>
          <div v-for="r in roles" :key="r" class="role" :class="{ 'role--new': baru.includes(r) }">
            <span class="ava sm" :class="{ on: ubah }">{{ ROLE_INFO[r].kode }}</span>
            <div><strong>{{ ROLE_INFO[r].label }}</strong><small>{{ ROLE_INFO[r].ket }}</small></div>
          </div>
        </section>

        <section class="panel save" :class="{ 'save--dirty': ubah }">
          <span class="dot"></span>
          <strong>{{ ubah ? `${ubah} Perubahan belum disimpan` : 'Tidak ada Perubahan' }}</strong>
          <button class="btn btn--ghost" :disabled="!ubah" @click="batal">Batalkan Perubahan</button>
          <button class="btn" :disabled="!ubah" @click="simpan">Simpan Perubahan</button>
        </section>
      </div>

      <aside class="side">
        <section class="panel">
          <h3>Ringkasan Perubahan</h3>
          <p v-if="!baru.length" class="muted">Belum ada perubahan. Klik “Tambah role” untuk menambahkan role baru bagi pengguna ini.</p>
          <ul v-else><li v-for="r in baru" :key="r">Tambah role {{ ROLE_INFO[r].label }}</li></ul>
        </section>
        <section class="panel">
          <h3>Catatan</h3>
          <ul class="muted">
            <li>Perubahan baru berlaku setelah Anda menekan Simpan perubahan.</li>
            <li>Pengguna perlu login ulang agar menu role baru muncul.</li>
            <li>Setiap perubahan dicatat pada Log Aktivitas.</li>
            <li>Hindari menggabungkan role input dan role verifikasi pada satu pengguna.</li>
          </ul>
        </section>
      </aside>
    </div>
  </template>
</template>

<style scoped>
.top { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
.top .btn { text-decoration: none; }
.crumb { font-size: 13px; color: #55554f; margin: 0 0 4px; }
.cols { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 20px; margin-top: 12px; }
.main, .side { display: grid; gap: 20px; align-content: start; }
.who { display: flex; gap: 16px; align-items: center; margin-bottom: 16px; }
.who h2 { margin: 0; font-size: 30px; font-weight: 500; }
.who p { margin: 4px 0 0; }
.badge { font-size: 14px; background: var(--color-primary); color: #fff; border-radius: 99px; padding: 2px 16px; vertical-align: middle; }
.ava { width: 66px; height: 66px; border-radius: 50%; background: #d9d9d9; display: grid; place-items: center; font-weight: 700; font-size: 26px; flex: none; transition: background .3s, color .3s; }
.ava.sm { width: 50px; height: 50px; font-size: 18px; }
.ava.on { background: var(--color-primary); color: #fff; }
dl { display: grid; grid-template-columns: 140px 1fr; gap: 10px 0; margin: 0; }
dt { color: #55554f; } dd { margin: 0; }
.rhead { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
h3 { margin: 0; font-size: 17px; display: flex; align-items: center; gap: 8px; }
.bar { width: 5px; height: 20px; background: var(--color-primary); border-radius: 3px; }
.count { background: #d6d8dc; border-radius: 99px; padding: 2px 12px; font-weight: 400; font-size: 15px; }
.role { display: flex; gap: 16px; align-items: center; background: #fff; border: 1px solid #000; border-radius: 8px; padding: 10px 28px; margin-top: 12px; animation: rise .3s ease both; }
.role small { display: block; color: #55554f; }
.role--new { background: #fdf0c8; border-color: #f2c94c; }
.pick { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
.save { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.save strong { flex: 1; font-weight: 500; font-size: 18px; }
.dot { width: 24px; height: 24px; border-radius: 50%; background: #bfc3c9; }
.save--dirty { background: #fdf0c8; border-color: #f2c94c; }
.save--dirty .dot { background: #fbc02d; }
.muted { color: #55554f; }
ul { padding-left: 20px; margin: 8px 0 0; display: grid; gap: 6px; }
@media (max-width: 1000px) { .cols { grid-template-columns: 1fr; } }
</style>
