<?php

namespace App\Http\Controllers;

use App\Support\Bentuk;
use App\Support\Kueri;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Admin Super: pengguna, peran, dan log aktivitas. Rute dibatasi peran:admin_super. */
class AdminSuperController extends Controller
{
    use Catat;

    public function pengguna()
    {
        return ['data' => array_map([Bentuk::class, 'pengguna'], DB::select(Kueri::PENGGUNA_SEMUA))];
    }

    private function satu(string $username): array
    {
        foreach (DB::select(Kueri::PENGGUNA_SEMUA) as $u) {
            if ($u->username === $username) {
                return Bentuk::pengguna($u);
            }
        }
        abort(404, 'Pengguna tidak ditemukan.');
    }

    private function target(string $username): object
    {
        $t = DB::selectOne(Kueri::PENGGUNA_BY_USERNAME, ['username' => $username]);
        abort_if($t === null, 404, 'Pengguna tidak ditemukan.');
        return $t;
    }

    private function peranSekarang(string $idUser): array
    {
        return array_map(fn ($r) => Bentuk::kodeKeFrontend($r->kode), DB::select(Kueri::ROLES_USER, ['id_user' => $idUser]));
    }

    /** Mengganti seluruh peran pengguna (frontend mengirim daftar akhir). */
    public function ubahRole(Request $request, string $username)
    {
        $d = $request->validate(['roles' => ['required', 'array', 'min:1', 'max:5'], 'roles.*' => ['required', 'string', Rule::in(array_keys(Bentuk::LABEL_PERAN))]],
            ['roles.min' => 'Pengguna harus memiliki minimal satu role.', 'roles.required' => 'Pengguna harus memiliki minimal satu role.']);
        $t = $this->target($username);
        $baru = array_values(array_unique($d['roles']));
        $lama = $this->peranSekarang($t->id_user);
        $tambah = array_values(array_diff($baru, $lama));
        $hapus = array_values(array_diff($lama, $baru));
        $sendiri = $t->id_user === $request->user()->id_user;

        if (in_array('admin_super', $hapus, true)) {
            abort_if($sendiri, 422, 'Anda tidak dapat menghapus role Admin Super dari akun Anda sendiri.');
            abort_if(DB::selectOne(Kueri::SUPER_AKTIF_LAIN, ['id_user' => $t->id_user])->n < 1, 422, 'Harus tersisa minimal satu Admin Super aktif.');
        }
        abort_if(in_array('dosen', $tambah, true) && $t->id_dosen === null, 422, 'Pengguna ini tidak terhubung ke data dosen, jadi tidak bisa diberi role Dosen.');

        $id = [];
        foreach (DB::select(Kueri::ROLE_SEMUA) as $r) {
            $id[Bentuk::kodeKeFrontend($r->kode)] = $r->id_role;
        }
        DB::transaction(function () use ($request, $t, $tambah, $hapus, $id, $username) {
            foreach ($tambah as $k) {
                DB::insert(Kueri::ROLE_TAMBAH, ['id_user' => $t->id_user, 'id_role' => $id[$k]]);
                $this->catat($request, 'Tambah role', "pengguna $username: $k");
            }
            foreach ($hapus as $k) {
                DB::delete(Kueri::ROLE_HAPUS, ['id_user' => $t->id_user, 'id_role' => $id[$k]]);
                $this->catat($request, 'Hapus role', "pengguna $username: $k");
            }
        });
        return ['data' => $this->satu($username)];
    }

    public function ubahStatus(Request $request, string $username)
    {
        $d = $request->validate(['aktif' => ['required', 'boolean']]);
        $t = $this->target($username);
        $aktif = filter_var($d['aktif'], FILTER_VALIDATE_BOOLEAN);
        abort_if($t->id_user === $request->user()->id_user, 422, 'Anda tidak dapat mengubah status akun Anda sendiri.');
        abort_if(in_array('admin_super', $this->peranSekarang($t->id_user), true), 422, 'Admin Super tidak dapat dinonaktifkan dari halaman ini.');
        DB::update(Kueri::STATUS_PENGGUNA, ['aktif' => $aktif ? 'true' : 'false', 'id_user' => $t->id_user]);
        $this->catat($request, $aktif ? 'Aktifkan pengguna' : 'Nonaktifkan pengguna', "pengguna $username");
        return ['data' => $this->satu($username)];
    }

    public function log(Request $request)
    {
        $hari = (int) $request->query('hari', 7);
        $hari = in_array($hari, [0, 7, 30], true) ? $hari : 7;
        $status = strtolower(trim((string) $request->query('status', '')));
        $status = in_array($status, ['berhasil', 'gagal'], true) ? $status : '';
        $q = addcslashes(trim((string) $request->query('q', '')), "%_\\");
        $baris = DB::select(Kueri::LOG_AKTIVITAS, ['hari1' => $hari, 'hari2' => $hari, 'status1' => $status, 'status2' => $status,
            'q1' => $q, 'q2' => $q, 'q3' => $q]);
        return ['data' => array_map([Bentuk::class, 'logAktivitas'], $baris)];
    }
}
