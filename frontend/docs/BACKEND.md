# Kontrak frontend ↔ backend (Laravel)

Dokumen ini untuk tim backend. Frontend: Vue 3 SPA. Backend: Laravel + Sanctum (mode SPA, sesi cookie).
Login memakai **SSO PNJ (Apereo CAS, protokol CAS 3)**. Referensi: https://github.com/pnjacid/pnj-id-example

## 1. Alur login SSO

```
Vue  ──GET  {API}/auth/sso/login──────────────► Laravel
Laravel ──302──► https://id.pnj.ac.id/cas/login?service={API}/auth/sso/callback
User login di halaman CAS
CAS ──302──► {API}/auth/sso/callback?ticket=ST-...
Laravel ──GET /cas/p3/serviceValidate?service=...&ticket=...──► CAS   (server ke server)
Laravel: cari user di tabel `users` berdasarkan username CAS, buat sesi (regenerate id)
Laravel ──302──► {FRONTEND}/auth/callback
Vue ──GET /api/me──► { data: { nip, name, role } } ──► arahkan ke dashboard sesuai role
```

Aturan keamanan (wajib):
- Ticket divalidasi **hanya di Laravel**, lewat HTTPS dengan verifikasi sertifikat aktif.
  Jangan salin `rejectUnauthorized: false` dari contoh Node.
- `service` saat login dan saat validasi harus identik. Ambil dari config server (`CAS_SERVICE_URL`), **bukan dari input request**.
- Parse XML dengan aman (tanpa entitas eksternal), jangan regex.
- `session()->regenerate()` setelah login berhasil (cegah session fixation).
- Cookie sesi: `Secure`, `HttpOnly`, `SameSite=Lax`. CORS hanya untuk domain frontend, `supports_credentials=true`.
- Redirect akhir ke frontend memakai alamat tetap dari config, bukan parameter dari URL.
- **Role dari tabel `users` milik kita**, bukan dari CAS. User CAS yang belum terdaftar ditolak:
  redirect ke `{FRONTEND}/login?error=unregistered`.
- Otorisasi dicek ulang di tiap endpoint (middleware role). Pembatasan di frontend hanya kenyamanan.
- Rate limit pada `/auth/sso/callback`. Catat (log) login berhasil dan gagal.
- Pendaftaran service: `serviceId` di service registry CAS harus cocok dengan `{API}/auth/sso/callback`. Minta admin CAS PNJ.

## 2. Endpoint

| Method | Path | Role | Keterangan |
|---|---|---|---|
| GET | `/auth/sso/login` | publik | Redirect ke CAS |
| GET | `/auth/sso/callback` | publik | Validasi ticket, buat sesi, redirect ke frontend |
| GET | `/api/me` | semua | `{ data: { nip, name, role } }`, 401 bila tidak ada sesi |
| POST | `/api/logout` | semua | Hapus sesi. Balas `{ logout_url }` (alamat `/cas/logout?service=...`) |
| GET | `/api/keuangan/dashboard?periode=` | keuangan, pimpinan, admin | Ringkasan dan per jurusan |
| GET | `/api/keuangan/honor?periode=&jurusan=&status=&q=` | keuangan, pimpinan, admin | Daftar dosen |
| GET | `/api/keuangan/honor/{dosen}?periode=` | keuangan, pimpinan, admin | Detail per kelas |
| POST | `/api/keuangan/honor/{dosen}/verifikasi` | keuangan | Tandai terverifikasi |
| POST | `/api/keuangan/laporan/ekspor` | keuangan | Body `{ periode, format: "excel"\|"pdf" }`, balas file |
| POST | `/api/keuangan/periode/{id}/kunci` | keuangan | Kunci periode |

Bentuk JSON mengikuti objek di `src/data/mock.js` (dashboard: `jurusan[]`, daftar: `dosen[]`, detail: `detailKelas`).
Honor dihitung **di backend**. Frontend hanya menampilkan.
Endpoint role Dosen (hanya data milik pengguna yang login, jangan terima id dosen dari klien):

| Method | Path | Role | Keterangan |
|---|---|---|---|
| GET | `/api/dosen/ringkasan?periode=` | dosen | SKS wajib/terlaksana/lebih, perhitungan honor, rincian kelas |
| GET | `/api/dosen/rincian?periode=` | dosen | Rincian per minggu |
| GET | `/api/dosen/riwayat` | dosen | Riwayat honor semua periode |
| GET | `/api/dosen/profil` | dosen | Biodata. NIDN dan nomor sertifikat sudah **disamarkan di backend** |
| POST | `/api/role` | semua | Body `{ role }`. Ganti peran aktif. Backend wajib memeriksa bahwa pengguna memang memiliki peran itu, balas `{ data: { nip, name, role, roles } }` |

`/api/me` ikut mengembalikan `roles` (daftar peran yang dimiliki) selain `role` (peran aktif).
Endpoint Admin Input dan Admin Super menyusul setelah desainnya dibaca.

## 3. Contoh `SsoController` (referensi, sesuaikan)

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class SsoController extends Controller
{
    public function login()
    {
        $service = urlencode(config('services.cas.service_url'));
        return redirect(config('services.cas.server') . "/login?service={$service}");
    }

    public function callback(Request $request)
    {
        $ticket = $request->query('ticket');
        if (!$ticket) {
            return redirect(config('app.frontend_url') . '/login');
        }

        $res = Http::timeout(10)->get(config('services.cas.server') . '/p3/serviceValidate', [
            'service' => config('services.cas.service_url'),
            'ticket'  => $ticket,
        ]);
        if (!$res->ok()) {
            return redirect(config('app.frontend_url') . '/login?error=sso');
        }

        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($res->body(), 'SimpleXMLElement', LIBXML_NONET);
        libxml_use_internal_errors($prev);
        $ns = $xml?->children('cas', true);
        $username = $ns?->authenticationSuccess?->user ?? null;
        if (!$username) {
            return redirect(config('app.frontend_url') . '/login?error=sso');
        }

        $user = User::where('username', (string) $username)->where('aktif', true)->first();
        if (!$user) {
            return redirect(config('app.frontend_url') . '/login?error=unregistered');
        }

        Auth::login($user);
        $request->session()->regenerate();
        return redirect(config('app.frontend_url') . '/auth/callback');
    }

    public function me(Request $request)
    {
        $u = $request->user();
        return ['data' => ['nip' => $u->username, 'name' => $u->name, 'role' => $u->role]];
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $service = urlencode(config('app.frontend_url') . '/login');
        return ['logout_url' => config('services.cas.server') . "/logout?service={$service}"];
    }
}
```

`config/services.php`:

```php
'cas' => [
    'server'      => env('CAS_SERVER', 'https://id.pnj.ac.id/cas'),
    'service_url' => env('CAS_SERVICE_URL'), // mis. http://localhost:8000/auth/sso/callback
],
```

Catatan: URL `service` pada logout juga perlu cocok dengan `serviceId` yang terdaftar di CAS. Konfirmasi ke admin CAS.
