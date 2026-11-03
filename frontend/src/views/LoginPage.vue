<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { setUser } from '../stores/auth.js'
import BrandLogo from '../components/BrandLogo.vue'
import { AUTH_MODE, mockLogin, devLogin, loginLokal, startSso } from '../services/auth.js'
import { ROLES, homeFor } from '../lib/roles.js'

const router = useRouter()
const isSso = AUTH_MODE === 'sso'
const isDev = AUTH_MODE === 'dev'
const isMock = AUTH_MODE === 'mock'

const nip = ref('')
const password = ref('')
const ingat = ref(true) // hanya tampilan; lama sesi diatur backend
const role = ref('keuangan') // khusus mode pengembangan
const loading = ref(false)
const error = ref(new URLSearchParams(location.search).get('error') === 'unregistered'
  ? 'Akun SSO Anda belum terdaftar di Integratia. Hubungi admin.'
  : '')

const canSubmit = computed(() => !loading.value)

// Mode SSO: username/email + password dicek backend (akun lokal saja; akun SSO memakai tombol SSO).
// Mode dev/mock: memakai pilihan peran, password tidak dikirim ke mana pun.
async function onSubmit() {
  error.value = ''
  if ((isSso || isMock) && nip.value.trim() === '') {
    error.value = 'NIP/Email wajib diisi.'
    return
  }
  if (isSso && password.value === '') {
    error.value = 'Password wajib diisi.'
    return
  }
  loading.value = true
  try {
    const user = isSso
      ? await loginLokal({ username: nip.value.trim(), password: password.value })
      : isDev ? await devLogin(role.value) : await mockLogin({ nip: nip.value.trim(), role: role.value })
    password.value = ''
    setUser(user)
    const gagal = await router.push(homeFor(user.role))
    if (gagal) throw new Error('Gagal membuka halaman setelah login.')
  } catch (e) {
    console.error(e)
    password.value = ''
    error.value = e.message || 'Gagal masuk. Coba lagi.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="login">
    <section class="hero" aria-label="Tentang Integratia">
      <span class="lingkar lingkar--isi" aria-hidden="true"></span>
      <span class="lingkar lingkar--garis-atas" aria-hidden="true"></span>
      <span class="lingkar lingkar--garis-bawah" aria-hidden="true"></span>
      <div class="hero__isi">
        <div class="hero__merek">
          <BrandLogo :size="94" />
          <span class="hero__nama">Integratia</span>
        </div>
        <h2 class="hero__judul">Honorarium kelebihan SKS, <em>terhitung transparan.</em></h2>
        <p class="hero__ket">Satu tempat untuk menyatukan data LKD dan SISTER, lalu menghitung honorarium dosen sesuai ketentuan yang berlaku.</p>
        <ul class="hero__daftar">
          <li><span class="cek" aria-hidden="true">✓</span>Sinkronisasi otomatis dari SISTER</li>
          <li><span class="cek" aria-hidden="true">✓</span>Perhitungan kelebihan SKS sesuai Perdir</li>
          <li><span class="cek" aria-hidden="true">✓</span>Jejak audit untuk setiap perubahan</li>
        </ul>
      </div>
    </section>

    <section class="login__panel">
      <div class="form">
        <BrandLogo class="form__logo" :size="64" />
        <p class="form__salam">Selamat datang</p>
        <h1>Masuk ke Integratia</h1>
        <p class="form__sub">Gunakan akun kampus Anda untuk melihat dan mengelola perhitungan honorarium.</p>

        <form novalidate @submit.prevent="onSubmit">
          <div class="field">
            <label for="nip">NIP/Email</label>
            <div class="field__box">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3.5 6.5 8.5 7 8.5-7" /></svg>
              <input id="nip" v-model="nip" type="text" autocomplete="username" placeholder="nama@pnj.ac.id" />
            </div>
          </div>
          <div class="field">
            <label for="password">Password</label>
            <div class="field__box">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10.5" width="14" height="10" rx="2" /><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" /></svg>
              <input id="password" v-model="password" type="password" autocomplete="current-password" placeholder="masukkan password anda" />
            </div>
          </div>

          <label class="ingat"><input v-model="ingat" type="checkbox" /><span class="kotak" aria-hidden="true"></span>Ingat saya</label>

          <div v-if="isMock || isDev" class="dev">
            <label for="role">Peran (khusus mode pengembangan)</label>
            <select id="role" v-model="role">
              <option v-for="(r, k) in ROLES" :key="k" :value="k">{{ r.label }}</option>
            </select>
          </div>

          <p v-if="error" class="form__error" role="alert">{{ error }}</p>

          <button class="btn-masuk" type="submit" :disabled="!canSubmit">{{ loading ? 'Memproses…' : 'Login' }}</button>
        </form>

        <div class="atau"><span>atau</span></div>
        <button class="btn-sso" type="button" @click="startSso">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="15" r="4" /><path d="m11 12 8-8m-3 3 2.5 2.5M14 9l2 2" /></svg>
          Masuk dengan SSO PNJ
        </button>

        <p class="form__bantu">Butuh bantuan akses? Hubungi Super Admin.<br />© 2026 Integratia · Politeknik Negeri Jakarta</p>
      </div>
    </section>
  </main>
</template>

<style scoped>
.login { display: grid; grid-template-columns: 49.3% 1fr; min-height: 100vh; background: var(--color-bg); }

/* ---------- Panel hijau kiri ---------- */
.hero { position: relative; display: flex; align-items: center; padding: 48px 65px; background: #0b5e58; color: #fff; overflow: hidden; }
.lingkar { position: absolute; border-radius: 50%; pointer-events: none; }
.lingkar--isi { width: 340px; height: 340px; right: 0; top: -112px; background: #1f7566; }
.lingkar--garis-atas { width: 340px; height: 340px; right: 0; top: -172px; border: 1px solid #fff; }
.lingkar--garis-bawah { width: 420px; height: 420px; left: -146px; bottom: -64px; border: 1px solid #fff; }
.hero__isi { position: relative; max-width: 580px; }
.hero__merek { display: flex; align-items: center; gap: 12px; margin-bottom: 26px; }
.hero__nama { font-size: 2.6rem; font-weight: 600; }
.hero__judul { margin: 0; max-width: 470px; font-size: 2.35rem; line-height: 1.5; font-weight: 700; }
.hero__judul em { font-style: normal; color: #7ef0a6; }
.hero__ket { margin: 4px 0 28px; font-size: 1.02rem; line-height: 1.4; opacity: 0.92; }
.hero__daftar { list-style: none; margin: 0; padding: 0; display: grid; gap: 20px; font-size: 1.05rem; }
.hero__daftar li { display: flex; align-items: center; gap: 22px; }
.cek { display: grid; place-items: center; flex: none; width: 30px; height: 30px; border-radius: 50%; background: #1d8a72; font-size: 1rem; }

/* ---------- Form kanan ---------- */
.login__panel { display: grid; place-items: center; padding: 40px 32px; }
.form { width: min(455px, 100%); }
.form .form__logo { display: none; margin: 0 auto 24px; }
.form__salam { margin: 0; font-size: 0.95rem; letter-spacing: 0.02em; text-transform: uppercase; color: #12994a; }
h1 { margin: 2px 0 6px; font-size: 2.3rem; line-height: 1.15; }
.form__sub { margin: 0 0 30px; color: #55554f; line-height: 1.4; }

.field + .field { margin-top: 14px; }
.field label { display: block; font-size: 1.35rem; line-height: 1.6; }
.field__box { display: flex; align-items: center; gap: 12px; height: 62px; padding: 0 22px; border: 2px solid #000; border-radius: 10px; background: #fff; }
.field__box:focus-within { outline: 3px solid var(--color-primary); outline-offset: 2px; }
.field__box svg { width: 30px; height: 30px; flex: none; fill: none; stroke: #000; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.field__box input { flex: 1; min-width: 0; height: 100%; border: 0; outline: 0; background: transparent; font: inherit; font-size: 1.05rem; color: #000; }
.field__box input::placeholder { color: #6b6b64; }

.ingat { position: relative; display: inline-flex; align-items: center; gap: 12px; margin-top: 20px; font-size: 1rem; color: #3b3b36; cursor: pointer; }
.ingat input { position: absolute; opacity: 0; width: 26px; height: 26px; margin: 0; cursor: pointer; }
.kotak { display: grid; place-items: center; width: 26px; height: 26px; border-radius: 6px; background: #0d8177; }
.ingat input:not(:checked) + .kotak { background: #fff; border: 2px solid #0d8177; }
.ingat input:checked + .kotak::after { content: '✓'; color: #fff; font-size: 0.95rem; }
.ingat input:focus-visible + .kotak { outline: 3px solid var(--color-primary); outline-offset: 2px; }

.dev { margin-top: 16px; padding: 12px 14px; border: 1px dashed #8a8a82; border-radius: 10px; }
.dev label { display: block; font-size: 0.9rem; color: #55554f; margin-bottom: 6px; }
.dev select { width: 100%; height: 44px; border: 1px solid #000; border-radius: 8px; padding: 0 12px; font: inherit; background: #fff; }

.form__error { margin: 14px 0 0; color: var(--color-error); }
.btn-masuk { display: block; width: 100%; height: 62px; margin-top: 18px; border: 1px solid #000; border-radius: 10px; background: #0b5e58; color: #fff; font: inherit; font-size: 1.5rem; cursor: pointer; }
.btn-masuk:hover:not(:disabled) { background: var(--color-primary-dark); }
.btn-masuk:disabled { opacity: 0.65; cursor: progress; }

.atau { display: flex; align-items: center; gap: 14px; margin: 14px 0; color: #55554f; font-size: 1.3rem; }
.atau::before, .atau::after { content: ''; flex: 1; height: 1px; background: #3b3b36; }
.btn-sso { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; height: 62px; border: 2px solid #0d8177; border-radius: 10px; background: #fff; color: #0d8177; font: inherit; font-size: 1.5rem; cursor: pointer; }
.btn-sso:hover { background: #f1f8f7; }
.btn-sso svg { width: 26px; height: 26px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.btn-masuk:focus-visible, .btn-sso:focus-visible { outline: 3px solid var(--color-primary); outline-offset: 3px; }
.form__bantu { margin: 24px 0 0; text-align: center; color: #55554f; font-size: 0.95rem; line-height: 1.5; }

/* ---------- Layar sempit ---------- */
@media (max-width: 900px) {
  .login { grid-template-columns: 1fr; }
  .hero { display: none; }
  .form .form__logo { display: grid; }
}
</style>
