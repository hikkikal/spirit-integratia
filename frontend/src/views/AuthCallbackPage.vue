<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auth, setUser, ensureUser } from '../stores/auth.js'
import { ssoValidate } from '../services/auth.js'
import { homeFor } from '../lib/roles.js'

// CAS mengembalikan browser ke sini dengan ?ticket=ST-... . Tiket hanya berlaku sekali, jadi langsung
// dikirim ke backend dan dihapus dari alamat.
const route = useRoute()
const router = useRouter()
const pesan = ref('')

onMounted(async () => {
  const ticket = typeof route.query.ticket === 'string' ? route.query.ticket : ''
  try {
    if (ticket) {
      history.replaceState(null, '', location.pathname) // jangan biarkan tiket tertinggal di riwayat browser
      const user = await ssoValidate(ticket)
      setUser(user)
      auth.loaded = true
      router.replace(homeFor(user.role))
      return
    }
    // Tanpa tiket: mungkin sesi sudah ada.
    auth.loaded = false
    const user = await ensureUser()
    if (user) router.replace(homeFor(user.role))
    else pesan.value = 'Login gagal.'
  } catch (e) {
    auth.loaded = true // sudah dipastikan tidak ada sesi; jangan tanya /me lagi
    if (e.status === 403) {
      router.replace('/login?error=unregistered')
      return
    }
    pesan.value = e.message || 'Login gagal.'
  }
})
</script>

<template>
  <main class="cb">
    <p v-if="!pesan">Memeriksa sesi…</p>
    <p v-else role="alert">{{ pesan }} <RouterLink to="/login">Kembali ke halaman masuk</RouterLink></p>
  </main>
</template>

<style scoped>
.cb { min-height: 100vh; display: grid; place-items: center; font-size: 20px; padding: 16px; text-align: center; }
</style>
