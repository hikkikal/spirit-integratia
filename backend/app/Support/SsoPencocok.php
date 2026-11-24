<?php

namespace App\Support;

/**
 * Mencocokkan identitas dari SSO (OpenID Connect) ke akun Integratia. Tidak bergantung pada Laravel:
 * query dijalankan lewat closure $baca(sql, bind): array dan $tulis(sql, bind): void, seperti HitungHonor.
 *
 * Urutan:
 *  1. Akun SSO yang sudah terikat ke `sub` ini.
 *  2. Akun SSO yang didaftarkan Super Admin (username = claim username, atau email), yang belum terikat ke sub lain.
 *  3. Dosen di data SISTER dengan NIP/NIDN/NUPTK = claim username, atau yang sudah punya akun SSO terhubung:
 *     akun dosen dibuat otomatis dengan peran Dosen (hanya melihat data sendiri).
 *  Pencocokan harus TEPAT SATU; bila lebih, ditolak ('ambigu') supaya tidak salah masuk ke data orang lain.
 */
final class SsoPencocok
{
    public const BY_SUB = "SELECT id_user, username, nama, id_dosen, status_aktif FROM users WHERE metode_login = 'sso' AND sso_sub = :sub";
    public const BY_USERNAME = "SELECT id_user, username, nama, id_dosen, status_aktif FROM users
        WHERE metode_login = 'sso' AND username = :u AND (sso_sub IS NULL OR sso_sub = :sub)";
    public const BY_EMAIL = "SELECT id_user, username, nama, id_dosen, status_aktif FROM users
        WHERE metode_login = 'sso' AND :e <> '' AND lower(email) = lower(:e2) AND (sso_sub IS NULL OR sso_sub = :sub)";
    public const DOSEN = "SELECT id_dosen, nama_sdm FROM dosen WHERE :u <> '' AND (nip = :a OR nidn = :b OR nuptk = :c)";
    public const BY_DOSEN = "SELECT id_user, username, nama, id_dosen, status_aktif FROM users
        WHERE metode_login = 'sso' AND id_dosen = :d AND (sso_sub IS NULL OR sso_sub = :sub)";
    public const AKUN_DOSEN_ADA = "SELECT 1 AS ada FROM users WHERE metode_login = 'sso' AND id_dosen = :d";
    public const USERNAME_ADA = "SELECT 1 AS ada FROM users WHERE username = :u";
    public const BUAT_USER = "INSERT INTO users (id_user, username, email, nama, id_dosen, metode_login, sso_sub) VALUES (:id, :u, :e, :n, :d, 'sso', :sub)";
    public const BUAT_PERAN = "INSERT INTO user_roles (id_user, id_role) SELECT :id, id_role FROM roles WHERE kode = 'dosen'";
    public const IKAT_SUB = "UPDATE users SET sso_sub = :sub WHERE id_user = :id AND sso_sub IS NULL";

    /**
     * @return array{status:string, user:?object, baru:bool}
     *   status: ok | nonaktif | tidak_terdaftar | ambigu | bentrok
     */
    public static function cari(callable $baca, callable $tulis, string $sub, string $username, string $email, string $nama, string $idBaru): array
    {
        $username = trim($username);
        $email = trim($email);
        $hasil = fn (string $status, ?object $user = null, bool $baru = false) => ['status' => $status, 'user' => $user, 'baru' => $baru];
        $pilih = function (object $u) use ($tulis, $sub, $hasil) {
            if (! $u->status_aktif) {
                return $hasil('nonaktif');
            }
            $tulis(self::IKAT_SUB, ['sub' => $sub, 'id' => $u->id_user]);
            return $hasil('ok', $u);
        };

        // 1. sudah terikat
        $a = $baca(self::BY_SUB, ['sub' => $sub]);
        if (count($a) === 1) {
            return $pilih($a[0]);
        }

        // 2. akun SSO yang didaftarkan manual: username dulu, baru email
        foreach ([[self::BY_USERNAME, ['u' => $username, 'sub' => $sub], $username], [self::BY_EMAIL, ['e' => $email, 'e2' => $email, 'sub' => $sub], $email]] as [$sql, $bind, $kunci]) {
            if ($kunci === '') {
                continue;
            }
            $r = $baca($sql, $bind);
            if (count($r) > 1) {
                return $hasil('ambigu');
            }
            if (count($r) === 1) {
                return $pilih($r[0]);
            }
        }

        // 3. dosen dari data SISTER
        if ($username === '') {
            return $hasil('tidak_terdaftar');
        }
        $d = $baca(self::DOSEN, ['u' => $username, 'a' => $username, 'b' => $username, 'c' => $username]);
        if (count($d) > 1) {
            return $hasil('ambigu');
        }
        if (count($d) === 0) {
            return $hasil('tidak_terdaftar');
        }
        $dosen = $d[0];
        $punya = $baca(self::BY_DOSEN, ['d' => $dosen->id_dosen, 'sub' => $sub]);
        if (count($punya) > 1) {
            return $hasil('ambigu');
        }
        if (count($punya) === 1) {
            return $pilih($punya[0]);
        }
        if ($baca(self::AKUN_DOSEN_ADA, ['d' => $dosen->id_dosen]) !== []) {
            return $hasil('bentrok'); // dosen ini sudah punya akun SSO yang terikat ke identitas SSO lain
        }
        if ($baca(self::USERNAME_ADA, ['u' => $username]) !== []) {
            return $hasil('bentrok'); // username sudah dipakai akun lain (mis. akun lokal)
        }
        $tulis(self::BUAT_USER, ['id' => $idBaru, 'u' => $username, 'e' => $email !== '' ? $email : null, 'n' => $dosen->nama_sdm, 'd' => $dosen->id_dosen, 'sub' => $sub]);
        $tulis(self::BUAT_PERAN, ['id' => $idBaru]);
        return $hasil('ok', (object) ['id_user' => $idBaru, 'username' => $username, 'nama' => $dosen->nama_sdm, 'id_dosen' => $dosen->id_dosen, 'status_aktif' => true], true);
    }
}
