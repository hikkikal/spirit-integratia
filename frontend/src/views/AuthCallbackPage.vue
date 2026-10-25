<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth, ensureUser } from '../stores/auth.js'
import { homeFor } from '../lib/roles.js'

// Backend mengarahkan ke sini setelah memvalidasi ticket CAS dan membuat sesi (cookie).
const router = useRouter()
const gagal = ref(false)

onMounted(async () => {
  auth.loaded = false
  const user = await ensureUser().catch(() => null)
  if (user) router.replace(homeFor(user.role))
  else gagal.value = true
})
</script>

<template>
  <main class="cb">
    <p v-if="!gagal">Memeriksa sesi…</p>
    <p v-else>Login gagal. <RouterLink to="/login">Kembali ke halaman masuk</RouterLink></p>
  </main>
</template>

<style scoped>
.cb { min-height: 100vh; display: grid; place-items: center; font-size: 20px; }
</style>
