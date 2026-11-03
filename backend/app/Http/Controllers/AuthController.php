<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Bentuk;
use App\Support\Cas;
use App\Support\Kueri;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /** Akun contoh per peran (lihat db/seed.sql). Hanya dipakai oleh dev-login. */
    private const AKUN_DEMO = [
        'dosen' => 'dosen.b', 'pimpinan' => 'pimpinan', 'keuangan' => 'keuangan',
        'admin' => 'admin.input', 'admin_super' => 'admin.super',
    ];

    /** Login tiruan: HANYA saat APP_ENV=local. Di produksi rute ini menjawab 404. */
    public function devLogin(Request $request)
    {
        abort_unless(app()->environment('local'), 404);
        $data = $request->validate(['role' => 'required|in:dosen,pimpinan,keuangan,admin,admin_super']);

        $baris = DB::selectOne(Kueri::USER_BY_USERNAME, ['username' => self::AKUN_DEMO[$data['role']]]);
        abort_if($baris === null, 422, 'Akun contoh belum ada. Muat db/seed.sql dulu.');

        Auth::login(User::findOrFail($baris->id_user));
        $request->session()->regenerate();
        $request->session()->put('peran_aktif', $data['role']);
        return ['data' => $this->muatan($baris, $data['role'])];
    }

    /**
     * Login SSO. CAS mengembalikan browser ke FRONTEND bersama ?ticket=...; frontend meneruskan tiket ke sini.
     * `service` TIDAK diterima dari klien: selalu config('cas.service'), supaya tiket tidak bisa dipakai untuk layanan lain.
     */
    public function ssoValidate(Request $request)
    {
        $data = $request->validate(['ticket' => ['required', 'string', 'max:256', 'regex:/^ST-[A-Za-z0-9._\-]+$/']]);
        $cfg = config('cas');

        $hasil = Cas::validasi($cfg['server'], $cfg['service'], $data['ticket'], function (string $url) use ($cfg) {
            try {
                $r = Http::timeout(10)->withOptions(['verify' => $cfg['verify_tls']])->get($url);
                return $r->successful() ? $r->body() : null;
            } catch (\Throwable $e) {
                Log::warning('CAS tidak terjangkau: ' . $e->getMessage());
                return null;
            }
        });

        abort_if($hasil['kode'] === 'CAS_TIDAK_TERJANGKAU', 502, 'Layanan SSO PNJ tidak dapat dihubungi. Coba lagi nanti.');
        abort_unless($hasil['ok'], 401, 'Tiket SSO tidak valid atau sudah kedaluwarsa. Silakan masuk lagi.');

        $baris = DB::selectOne(Kueri::USER_BY_SSO, ['u1' => $hasil['user'], 'u2' => $hasil['user'], 'u3' => $hasil['user']]);
        if ($baris === null || ! $baris->status_aktif) {
            Log::notice('Login SSO ditolak: akun belum terdaftar/nonaktif', ['atribut' => array_keys($hasil['atribut'])]);
            $isi = ['message' => 'Akun SSO Anda belum terdaftar di Integratia. Hubungi admin.'];
            if (app()->environment('local')) {
                // Membantu pengembangan: tahu identitas apa yang dikirim CAS agar bisa didaftarkan di tabel users.
                $isi['debug'] = ['cas_user' => $hasil['user'], 'atribut' => $hasil['atribut']];
            }
            return response()->json($isi, 403);
        }

        $roles = $this->peranPengguna($baris->id_user);
        abort_if($roles === [], 403, 'Akun Anda belum memiliki peran. Hubungi admin.');

        Auth::login(User::findOrFail($baris->id_user));
        $request->session()->regenerate();
        $request->session()->put('peran_aktif', $roles[0]);
        $request->session()->put('via_sso', true);
        DB::insert(Kueri::LOG, ['pelaku' => $baris->id_user, 'aksi' => 'Login SSO', 'objek' => 'peran ' . $roles[0], 'peran' => $roles[0], 'status' => 'berhasil']);
        return ['data' => $this->muatan($baris, $roles[0])];
    }

    /**
     * Login lokal (username/email + password) untuk akun ber-`metode_login = 'lokal'` saja (akun demo/darurat).
     * Akun SSO tidak punya password di sini, jadi selalu ditolak. Dimatikan dengan LOGIN_LOKAL=false di .env.
     * Pesan galat sengaja sama untuk semua sebab (akun tidak ada, salah password, nonaktif).
     */
    public function loginLokal(Request $request)
    {
        abort_unless(config('lokal.aktif'), 404);
        $data = $request->validate(['username' => ['required', 'string', 'max:120'], 'password' => ['required', 'string', 'max:200']]);

        $kunci = 'login-lokal:' . strtolower($data['username']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $detik = RateLimiter::availableIn($kunci);
            return response()->json(['message' => "Terlalu banyak percobaan. Coba lagi dalam $detik detik."], 429);
        }

        $baris = DB::selectOne(Kueri::USER_LOKAL, ['u1' => $data['username'], 'u2' => $data['username'], 'u3' => $data['username']]);
        // Hash tiruan dipakai bila akun tidak ada, supaya waktu respons tidak membocorkan keberadaan akun.
        $cocok = Hash::check($data['password'], $baris->password_hash ?? config('lokal.hash_tiruan'));
        if ($baris === null || ! $cocok || ! $baris->status_aktif) {
            RateLimiter::hit($kunci, 60);
            DB::insert(Kueri::LOG, ['pelaku' => $baris->id_user ?? null, 'aksi' => 'Login lokal',
                'objek' => $baris === null ? 'akun tidak dikenal' : 'password salah atau akun nonaktif', 'peran' => null, 'status' => 'gagal']);
            return response()->json(['message' => 'NIP/Email atau password salah.'], 401);
        }
        $roles = $this->peranPengguna($baris->id_user);
        abort_if($roles === [], 403, 'Akun Anda belum memiliki peran. Hubungi admin.');
        RateLimiter::clear($kunci);

        Auth::login(User::findOrFail($baris->id_user));
        $request->session()->regenerate();
        $request->session()->put('peran_aktif', $roles[0]);
        DB::insert(Kueri::LOG, ['pelaku' => $baris->id_user, 'aksi' => 'Login lokal', 'objek' => 'peran ' . $roles[0], 'peran' => $roles[0], 'status' => 'berhasil']);
        return ['data' => $this->muatan($baris, $roles[0])];
    }

    public function me(Request $request)
    {
        $baris = DB::selectOne("SELECT id_user, username, nama, id_dosen FROM users WHERE id_user = :id", ['id' => $request->user()->id_user]);
        $aktif = $request->session()->get('peran_aktif');
        return ['data' => $this->muatan($baris, $aktif)];
    }

    /** Ganti peran aktif: server memeriksa bahwa pengguna memang memilikinya. */
    public function ganti(Request $request)
    {
        $data = $request->validate(['role' => 'required|string']);
        $baris = DB::selectOne("SELECT id_user, username, nama, id_dosen FROM users WHERE id_user = :id", ['id' => $request->user()->id_user]);
        $punya = $this->peranPengguna($baris->id_user);
        abort_unless(in_array($data['role'], $punya, true), 403, 'Peran tidak tersedia.');
        $request->session()->put('peran_aktif', $data['role']);
        return ['data' => $this->muatan($baris, $data['role'])];
    }

    public function logout(Request $request)
    {
        // Sesi SSO: akhiri juga sesi di CAS PNJ supaya login berikutnya meminta kredensial lagi.
        $url = $request->session()->get('via_sso') ? Cas::logoutUrl(config('cas.server'), config('cas.logout_service')) : null;
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return ['logout_url' => $url];
    }

    private function peranPengguna(string $idUser): array
    {
        return array_map(fn ($r) => Bentuk::kodeKeFrontend($r->kode), DB::select(Kueri::ROLES_USER, ['id_user' => $idUser]));
    }

    private function muatan(object $u, ?string $aktif): array
    {
        $roles = $this->peranPengguna($u->id_user);
        return ['nip' => $u->username, 'name' => $u->nama, 'role' => $aktif ?? ($roles[0] ?? null), 'roles' => $roles];
    }
}
