<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { honorState, tandaiTerverifikasi } from '../../stores/honor.js'
import { detailKelas } from '../../data/mock.js'
import { hitungBaris } from '../../lib/honor.js'
import { rupiah } from '../../lib/format.js'
import { useBase } from '../../lib/useBase.js'
import { auth } from '../../stores/auth.js'
import StatusBadge from '../../components/StatusBadge.vue'

const props = defineProps({ id: String, backTo: String })
const router = useRouter()
const base = useBase()
const backTo = props.backTo
// Hanya Bidang Keuangan yang boleh memverifikasi. Backend tetap harus memeriksa ulang.
const bisaVerifikasi = computed(() => auth.user?.role === 'keuangan')

const dosen = computed(() => honorState.dosen.find((d) => d.id === props.id))
const detail = computed(() => detailKelas[props.id])

const baris = computed(() =>
  (detail.value?.kelas ?? []).map((k) => ({ ...k, ...hitungBaris(k, detail.value.tarifReguler) })),
)
const total = computed(() => ({
  wajib: baris.value.reduce((a, k) => a + k.wajib, 0),
  lebih100: baris.value.reduce((a, k) => a + k.lebih100, 0),
  lebih50: baris.value.reduce((a, k) => a + k.lebih50, 0),
  lebih0: baris.value.reduce((a, k) => a + k.lebih0, 0),
  subtotal: baris.value.reduce((a, k) => a + k.subtotal, 0),
  pajak: baris.value.reduce((a, k) => a + k.pajak, 0),
  honor: baris.value.reduce((a, k) => a + k.honor, 0),
}))
</script>

<template>
  <button class="btn btn--ghost back" @click="router.push(backTo || `${base}/data-honor`)">← Kembali</button>

  <p v-if="!dosen" class="card">Dosen tidak ditemukan.</p>
  <template v-else>
    <h1 class="page-title">Detail Honor Dosen</h1>
    <p class="page-sub">{{ dosen.nama }} · {{ dosen.jabatan }} · {{ dosen.jurusan }} · {{ honorState.periode }}</p>

    <section class="grid cards">
      <div class="card"><div class="lbl">SKS wajib</div><div class="val">{{ dosen.sksWajib }}</div></div>
      <div class="card"><div class="lbl">SKS lebih</div><div class="val">{{ dosen.sksLebih }}</div></div>
      <div class="card"><div class="lbl">Total honor</div><div class="val">{{ rupiah(dosen.honor) }}</div></div>
      <div class="card"><div class="lbl">Status</div><div class="val"><StatusBadge :status="dosen.status" /></div></div>
    </section>

    <section class="card">
      <h2>Rincian kelas</h2>
      <p v-if="!detail" class="kosong">Rincian kelas dosen ini belum tersedia (menunggu data dari SISTER).</p>
      <div v-else class="scroll-x">
        <table class="tbl">
          <thead>
            <tr>
              <th>Mata kuliah / kelas</th><th>Jenis kelas</th><th class="num">SKS wajib</th>
              <th class="num">SKS lebih (100%)</th><th class="num">SKS lebih (50%)</th><th class="num">SKS lebih (0%)</th>
              <th class="num">Subtotal</th><th class="num">Pajak</th><th class="num">Honor</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="k in baris" :key="k.mk">
              <td>{{ k.mk }}</td><td>{{ k.jenis }}</td><td class="num">{{ k.wajib }}</td>
              <td class="num">{{ k.lebih100 }}</td><td class="num">{{ k.lebih50 }}</td><td class="num">{{ k.lebih0 }}</td>
              <td class="num">{{ rupiah(k.subtotal) }}</td><td class="num">{{ rupiah(k.pajak) }}</td><td class="num">{{ rupiah(k.honor) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="2">Total</th><th class="num">{{ total.wajib }}</th>
              <th class="num">{{ total.lebih100 }}</th><th class="num">{{ total.lebih50 }}</th><th class="num">{{ total.lebih0 }}</th>
              <th class="num">{{ rupiah(total.subtotal) }}</th><th class="num">{{ rupiah(total.pajak) }}</th><th class="num">{{ rupiah(total.honor) }}</th>
            </tr>
          </tfoot>
        </table>
      </div>
      <p v-if="detail" class="note">Tarif reguler {{ rupiah(detail.tarifReguler) }}/SKS · pajak 5% (sementara, belum dikonfirmasi).</p>
    </section>

    <div v-if="bisaVerifikasi" class="actions">
      <button class="btn" :disabled="dosen.status === 'terverifikasi'" @click="tandaiTerverifikasi(dosen.id)">
        {{ dosen.status === 'terverifikasi' ? 'Sudah terverifikasi' : 'Tandai terverifikasi' }}
      </button>
    </div>
  </template>
</template>

<style scoped>
.back { margin-bottom: 8px; }
.cards { grid-template-columns: repeat(4, 1fr); margin-bottom: 16px; }
.lbl { color: #55554f; }
.val { font-size: 26px; font-weight: 600; margin-top: 6px; color: var(--color-primary-dark); }
h2 { font-size: 20px; margin: 0 0 12px; }
.note { font-size: 14px; color: #77776f; }
.kosong { color: #77776f; }
.actions { margin-top: 16px; }
.tbl tfoot th { background: #ecebe3; }
@media (max-width: 900px) { .cards { grid-template-columns: repeat(2, 1fr); } }
</style>
