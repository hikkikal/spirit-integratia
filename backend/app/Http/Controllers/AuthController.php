<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Bentuk;
use App\Support\Kueri;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return ['logout_url' => null];   // SSO: isi dengan alamat logout CAS
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
