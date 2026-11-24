<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Bentuk;
use App\Support\Kueri;
use App\Support\Oidc;
use App\Support\SsoPencocok;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Login SSO PNJ (OpenID Connect, Authorization Code + PKCE). Rahasia klien dan token hanya ada di backend.
 *
 *  POST /api/auth/sso/mulai     -> { url }   (frontend lalu mengalihkan browser ke url itu)
 *  POST /api/auth/sso/callback  <- { code, state }   (frontend meneruskan hasil dari /auth/callback)
 *  POST /api/logout             -> { logout_url }
 */
class SsoController extends Controller
{
    /** Langkah 1: siapkan state, nonce, dan PKCE di sesi, lalu kembalikan alamat login SSO. */
    public function mulai(Request $request)
    {
        $cfg = config('oidc');
        abort_if(! $cfg['client_id'] || ! $cfg['client_secret'], 503, 'SSO belum dikonfigurasi (OIDC_CLIENT_ID/OIDC_CLIENT_SECRET).');
        $meta = $this->metadata();

        $pkce = Oidc::pkce();
        $state = Oidc::acak();
        $nonce = Oidc::acak();
        $request->session()->put('oidc', ['state' => $state, 'nonce' => $nonce, 'verifier' => $pkce['verifier'], 'waktu' => time()]);

        return ['url' => Oidc::urlOtorisasi($meta['authorization_endpoint'], $cfg['client_id'], $cfg['redirect_uri'], $cfg['scopes'], $state, $nonce, $pkce['challenge'])];
    }

    /** Langkah 2: tukar code dengan token, baca UserInfo, cocokkan ke akun Integratia, buat sesi. */
    public function callback(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:2048'], 'state' => ['required', 'string', 'max:256']]);
        $cfg = config('oidc');

        $tx = $request->session()->pull('oidc'); // sekali pakai
        abort_if(! is_array($tx) || (time() - ($tx['waktu'] ?? 0)) > 600, 401, 'Sesi login habis. Silakan masuk lagi.');
        abort_unless(hash_equals($tx['state'], $data['state']), 401, 'Permintaan login tidak valid. Silakan masuk lagi.');

        $meta = $this->metadata();
        $token = $this->panggil(fn () => $this->http()->withBasicAuth($cfg['client_id'], $cfg['client_secret'])->asForm()->post($meta['token_endpoint'], [
            'grant_type' => 'authorization_code', 'code' => $data['code'], 'redirect_uri' => $cfg['redirect_uri'], 'code_verifier' => $tx['verifier'],
        ]), 'penukaran token');
        $idToken = (string) ($token['id_token'] ?? '');
        $access = (string) ($token['access_token'] ?? '');
        abort_if($idToken === '' || $access === '', 401, 'SSO tidak mengirim token. Silakan masuk lagi.');

        $klaim = Oidc::payload($idToken);
        $salah = $klaim === null ? 'ID token tidak terbaca' : Oidc::periksaKlaim($klaim, $meta['issuer'] ?? $cfg['issuer'], $cfg['client_id'], $tx['nonce'], time());
        if ($salah !== null) {
            Log::warning('Login SSO ditolak: ' . $salah);
            abort(401, 'Token SSO tidak valid. Silakan masuk lagi.');
        }

        $info = $this->panggil(fn () => $this->http()->withToken($access)->acceptJson()->get($meta['userinfo_endpoint']), 'pembacaan profil');
        abort_unless(($info['sub'] ?? null) === $klaim['sub'], 401, 'Profil SSO tidak cocok dengan token.');

        if ($cfg['log_claims']) {
            Log::info('OIDC claim (sementara)', ['userinfo' => $info, 'id_token' => array_diff_key($klaim, array_flip(['nonce', 'at_hash']))]);
        }

        // PNJ menaruh nama/username/email di dalam `attributes` pada UserInfo (bukan di level atas), dan di level atas ID token.
        // Urutan: UserInfo level atas, lalu UserInfo.attributes, lalu ID token.
        $gabung = array_merge($klaim, is_array($info['attributes'] ?? null) ? $info['attributes'] : [], $info);
        $username = trim((string) ($gabung[$cfg['claim_username']] ?? $gabung['id'] ?? ''));
        $email = trim((string) ($gabung[$cfg['claim_email']] ?? ''));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = ''; // di SSO PNJ kolom email bisa hanya berisi nama pendek; bukan kunci yang aman untuk mencocokkan akun
        }
        $nama = (string) ($gabung[$cfg['claim_nama']] ?? $username);

        $pakai = SsoPencocok::cari(
            fn (string $sql, array $b) => DB::select($sql, $b),
            fn (string $sql, array $b) => DB::statement($sql, $b),
            $klaim['sub'], $username, $email, $nama, (string) Str::uuid()
        );

        $pesan = [
            'tidak_terdaftar' => 'Akun SSO Anda belum terdaftar di Integratia. Hubungi admin.',
            'ambigu' => 'Identitas Anda cocok dengan lebih dari satu data. Hubungi admin.',
            'bentrok' => 'Identitas Anda bentrok dengan akun lain. Hubungi admin.',
            'nonaktif' => 'Akun Anda nonaktif. Hubungi admin.',
        ];
        if ($pakai['status'] !== 'ok') {
            Log::notice('Login SSO ditolak: ' . $pakai['status']);
            $isi = ['message' => $pesan[$pakai['status']] ?? 'Login ditolak.'];
            if (app()->environment('local')) {
                // Membantu pengembangan: terlihat claim apa yang dikirim SSO (hanya APP_ENV=local).
                $isi['debug'] = ['sub' => $klaim['sub'], 'userinfo' => $info];
            }
            return response()->json($isi, 403);
        }

        $u = $pakai['user'];
        $roles = array_map(fn ($r) => Bentuk::kodeKeFrontend($r->kode), DB::select(Kueri::ROLES_USER, ['id_user' => $u->id_user]));
        abort_if($roles === [], 403, 'Akun Anda belum memiliki peran. Hubungi admin.');

        Auth::login(User::findOrFail($u->id_user));
        $request->session()->regenerate();
        $request->session()->put('peran_aktif', $roles[0]);
        $request->session()->put('via_sso', true);
        $request->session()->put('oidc_id_token', $idToken);
        $request->session()->put('oidc_access_token', $access);
        $request->session()->put('oidc_access_exp', time() + (int) ($token['expires_in'] ?? 0));
        DB::insert(Kueri::LOG, ['pelaku' => $u->id_user, 'aksi' => 'Login SSO', 'objek' => 'peran ' . $roles[0] . ($pakai['baru'] ? ' (akun dosen dibuat otomatis)' : ''), 'peran' => $roles[0], 'status' => 'berhasil']);

        return ['data' => ['nip' => $u->username, 'name' => $u->nama, 'role' => $roles[0], 'roles' => $roles]];
    }

    /** Keluar dari Integratia; sesi SSO PNJ ikut diakhiri lewat end_session_endpoint bila tersedia. */
    public function logout(Request $request)
    {
        $url = null;
        if ($request->session()->get('via_sso')) {
            try {
                $meta = $this->metadata();
                $url = Oidc::urlLogout($meta['end_session_endpoint'] ?? null, $request->session()->get('oidc_id_token'), config('oidc.post_logout_uri'));
            } catch (\Throwable $e) {
                Log::warning('Alamat logout SSO tidak terbaca: ' . $e->getMessage());
            }
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return ['logout_url' => $url];
    }

    private function http()
    {
        return Http::timeout(15)->withOptions(['verify' => config('oidc.verify_tls')]);
    }

    /** Metadata provider (alamat endpoint) dibaca dari discovery, disimpan 1 jam. */
    private function metadata(): array
    {
        $issuer = config('oidc.issuer');
        return Cache::remember('oidc.meta.' . md5($issuer), 3600, function () use ($issuer) {
            $m = $this->panggil(fn () => $this->http()->acceptJson()->get(Oidc::urlDiscovery($issuer)), 'pembacaan discovery');
            foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'] as $k) {
                abort_if(empty($m[$k]), 502, "Metadata SSO tidak memuat $k.");
            }
            return $m;
        });
    }

    /** Menjalankan permintaan HTTP ke SSO dan mengubah kegagalan menjadi pesan yang jelas. */
    private function panggil(callable $kirim, string $tahap): array
    {
        try {
            /** @var Response $r */
            $r = $kirim();
        } catch (\Throwable $e) {
            Log::warning("SSO tidak terjangkau ($tahap): " . $e->getMessage());
            abort(502, 'Layanan SSO PNJ tidak dapat dihubungi. Pastikan terhubung ke jaringan PNJ, lalu coba lagi.');
        }
        if (! $r->successful()) {
            Log::warning("SSO menolak ($tahap): HTTP {$r->status()}", ['isi' => Str::limit($r->body(), 300)]);
            abort(in_array($r->status(), [400, 401], true) ? 401 : 502, $tahap === 'penukaran token'
                ? 'SSO menolak permintaan login. Silakan masuk lagi.'
                : 'Layanan SSO PNJ membalas galat. Coba lagi nanti.');
        }
        $j = $r->json();
        abort_unless(is_array($j), 502, 'Balasan SSO tidak terbaca.');
        return $j;
    }
}
