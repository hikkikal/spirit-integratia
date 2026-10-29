<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { setUser } from '../stores/auth.js'
import BrandLogo from '../components/BrandLogo.vue'
import illustration from '../assets/login-illustration.png'
import { AUTH_MODE, mockLogin, startSso } from '../services/auth.js'
import { ROLES, homeFor } from '../lib/roles.js'

const router = useRouter()
const isSso = AUTH_MODE === 'sso'

const nip = ref('')
const password = ref('')
const role = ref('keuangan')
const loading = ref(false)
const error = ref(new URLSearchParams(location.search).get('error') === 'unregistered'
  ? 'Akun SSO Anda belum terdaftar di Integratia. Hubungi admin.'
  : '')

const canSubmit = computed(() => nip.value.trim() !== '' && !loading.value)

// Mode mock saja (pengembangan). Kolom password tidak diperiksa dan tidak dikirim ke mana pun.
async function onSubmit() {
  if (!canSubmit.value) {
    error.value = 'NIP wajib diisi.'
    return
  }
  error.value = ''
  loading.value = true
  try {
    const user = await mockLogin({ nip: nip.value.trim(), role: role.value })
    setUser(user)
    const gagal = await router.push(homeFor(user.role))
    if (gagal) throw new Error('Gagal membuka halaman setelah login.')
  } catch (e) {
    console.error(e)
    error.value = e.message || 'Gagal masuk. Coba lagi.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="login">
    <section class="hero" aria-label="Tentang Integratia">
      <div class="hero__content">
        <p>Selamat Datang di Integratia</p>
        <p>Sistem Perhitungan SKS Dosen</p>
        <p>Politeknik Negeri Jakarta</p>
      </div>
      <img class="hero__art" :src="illustration" alt="" />
    </section>

    <section class="login__panel">
      <!-- Produksi: hanya tombol SSO. Password diketik di halaman CAS PNJ, bukan di aplikasi ini. -->
      <div v-if="isSso" class="form">
        <h1 class="sr-only">Masuk ke Integratia</h1>
        <BrandLogo class="form__logo" :size="72" />
        <p class="sso__hint">Masuk menggunakan akun SSO PNJ Anda.</p>
        <p v-if="error" class="form__error" role="alert">{{ error }}</p>
        <button class="form__submit" type="button" @click="startSso">Masuk dengan SSO PNJ</button>
      </div>

      <!-- Pengembangan (VITE_AUTH_MODE=mock): form tiruan untuk mencoba tiap peran. -->
      <form v-else class="form" novalidate @submit.prevent="onSubmit">
        <h1 class="sr-only">Masuk ke Integratia</h1>
        <BrandLogo class="form__logo" :size="72" />

        <div class="field">
          <label for="nip">NIP</label>
          <input id="nip" v-model="nip" type="text" inputmode="numeric" autocomplete="username" />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" v-model="password" type="password" autocomplete="off" />
        </div>
        <div class="field">
          <label for="role">Peran (khusus mode pengembangan)</label>
          <select id="role" v-model="role">
            <option v-for="(r, k) in ROLES" :key="k" :value="k">{{ r.label }}</option>
          </select>
        </div>

        <p v-if="error" class="form__error" role="alert">{{ error }}</p>

        <button class="form__submit" type="submit" :disabled="loading">
          {{ loading ? 'Memproses…' : 'Login' }}
        </button>
      </form>
    </section>
  </main>
</template>

<style scoped>
.login {
  display: grid;
  grid-template-columns: 49.3% 1fr;
  min-height: 100vh;
  background: var(--color-bg);
}

/* ---------- Panel hijau kiri ---------- */
.hero {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 640px;
  padding: 48px 32px 28vh;
  border-radius: 0 105px 105px 0;
  background: var(--color-primary);
  color: var(--color-surface);
  overflow: hidden;
}

.hero__content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 11px;
  text-align: center;
  font-size: 1.5rem;
  line-height: 32px;
}

.hero__content p {
  margin: 0;
}

.hero__content .brand-logo {
  margin-bottom: 20px;
}

.hero__art {
  position: absolute;
  left: 25px;
  bottom: 12px;
  height: min(38vh, 396px);
  aspect-ratio: 390 / 396;
  max-width: 70%;
  pointer-events: none;
}

/* ---------- Form kanan (polos: tanpa kartu/garis, menyatu dengan latar halaman) ---------- */
.login__panel {
  background: transparent;
  border: 0;
  box-shadow: none;
  display: grid;
  place-items: center;
  padding: 48px 32px;
}

.form {
  width: min(360px, 100%);
}

.form .form__logo {
  display: none;
  margin: 0 auto 32px;
}

.field + .field {
  margin-top: 6px;
}

.field label {
  display: block;
  font-size: 1.5rem;
  line-height: 36px;
}

.field input {
  width: 100%;
  height: 62px;
  padding: 0 18px;
  border: 1px solid var(--color-ink);
  border-radius: 10px;
  background: var(--color-surface);
  color: var(--color-ink);
  font-size: 1.25rem;
}

.field input:focus-visible,
.form__submit:focus-visible {
  outline: 3px solid var(--color-primary);
  outline-offset: 3px;
}

.form__error {
  margin: 14px 0 0;
  color: var(--color-error);
  font-size: 1rem;
}

.form__submit {
  display: block;
  width: 100%;
  height: 62px;
  margin-top: 53px;
  border: 1px solid var(--color-ink);
  border-radius: 10px;
  background: var(--color-primary);
  color: var(--color-surface);
  font-size: 1.5rem;
  cursor: pointer;
}

.form__error + .form__submit {
  margin-top: 24px;
}

.form__submit:hover:not(:disabled) {
  background: var(--color-primary-dark);
}

.form__submit:disabled {
  opacity: 0.65;
  cursor: progress;
}

/* ---------- Layar sempit ---------- */
@media (max-width: 900px) {
  .login {
    grid-template-columns: 1fr;
  }

  .hero {
    display: none;
  }

  .form .form__logo {
    display: grid;
  }
}
.sso__hint { font-size: 20px; margin: 0 0 8px; }
.field select { width: 100%; height: 62px; border-radius: 10px; border: 1px solid #000; padding: 0 14px; font: inherit; font-size: 18px; background: #fff; }
</style>
