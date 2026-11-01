<script setup>
import { ref, computed, onMounted } from 'vue'
import { auth } from '../../stores/auth.js'
import { ROLES } from '../../lib/roles.js'
import { getProfilSaya } from '../../services/data.js'
import GantiAkunModal from '../../components/GantiAkunModal.vue'

const modal = ref(false)
const roles = () => (auth.user?.roles?.length ? auth.user.roles : [auth.user?.role])

const profil = ref(null)
const galat = ref('')
onMounted(async () => {
  try { profil.value = await getProfilSaya() } catch (e) { galat.value = e.message }
})
const identitas = computed(() => [
  ['NIDN', profil.value.nidn],
  ['Nomor Sertifikat', profil.value.sertifikat],
  ['Status', profil.value.status],
  ['Tempat/Tanggal Lahir', profil.value.ttl],
  ['No. Handphone', profil.value.hp],
  ['Email', profil.value.email],
])
</script>

<template>
  <p v-if="!profil" role="alert">{{ galat || 'Memuat…' }}</p>
  <div v-else class="wrap">
    <div class="banner"></div>
    <section class="head">
      <svg class="ava" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="none" stroke="#000" stroke-width="1.6" /><circle cx="12" cy="9" r="3.2" /><path d="M5.5 18.5c1.3-3 3.6-4.4 6.5-4.4s5.2 1.4 6.5 4.4c-1.8 1.9-4 2.8-6.5 2.8s-4.7-.9-6.5-2.8z" /></svg>
      <div class="who">
        <h1>{{ profil.nama }}</h1>
        <p>{{ profil.jabatan }} · {{ profil.prodi }} · {{ profil.kampus }}</p>
      </div>
      <div class="role">
        <span>Role Aktif</span>
        <div class="chips">
          <span v-for="r in roles()" :key="r" class="rc" :class="{ 'rc--on': r === auth.user.role }">{{ ROLES[r]?.label }}</span>
        </div>
        <button class="btn btn--ghost sm" @click="modal = true">⇄ Ganti Role</button>
      </div>
    </section>

    <h2 class="sec">Biodata</h2>
    <section class="panel box">
      <h3>Identitas</h3>
      <dl class="one"><dt>Nama lengkap</dt><dd>{{ profil.gelar }}</dd></dl>
      <div class="two">
        <dl v-for="[k, v] in identitas" :key="k"><dt>{{ k }}</dt><dd>{{ v }}</dd></dl>
      </div>
    </section>

    <section class="panel box">
      <h3>Kepegawaian</h3>
      <dl class="one"><dt>Perguruan Tinggi</dt><dd>{{ profil.kampus }}</dd></dl>
      <dl class="one"><dt>Alamat perguruan tinggi</dt><dd>{{ profil.alamatKampus }}</dd></dl>
      <div class="two">
        <dl><dt>Jurusan / program studi</dt><dd>{{ profil.jurusan }}</dd></dl>
        <dl><dt>Jab. Fungsional(TMT)/Golongan</dt><dd>{{ profil.jabatanFungsional }}</dd></dl>
      </div>
    </section>
  </div>
  <GantiAkunModal v-if="profil && modal" @close="modal = false" />
</template>

<style scoped>
.wrap { max-width: 900px; margin: 0 auto; }
.banner { height: 110px; background: var(--color-primary); border-radius: 14px 14px 0 0; }
.head { display: flex; align-items: center; gap: 20px; background: #f2f5f9; border: 1px solid #000; border-radius: 14px; margin-top: -26px; padding: 22px 28px; flex-wrap: wrap; }
.ava { width: 72px; height: 72px; fill: #000; flex: none; }
.who { flex: 1; min-width: 220px; }
.who h1 { margin: 0; font-size: 34px; font-weight: 500; }
.who p { margin: 4px 0 0; }
.role { display: grid; gap: 6px; justify-items: start; }
.chips { display: flex; gap: 6px; }
.rc { border: 1px solid var(--color-primary); border-radius: 8px; padding: 2px 12px; }
.rc--on { background: #0d8177; color: #fff; }
.sm { padding: 6px 16px; }
.sec { font-size: 20px; margin: 24px 0 10px; }
.box { margin-bottom: 16px; }
.box h3 { margin: 0 0 8px; font-weight: 500; font-size: 18px; }
dl { margin: 0 0 14px; border-bottom: 1px solid #000; padding-bottom: 6px; }
dt { font-size: 13px; color: #55554f; }
dd { margin: 2px 0 0; font-size: 17px; overflow-wrap: anywhere; }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: 0 40px; }
@media (max-width: 700px) { .two { grid-template-columns: 1fr; } }
</style>
