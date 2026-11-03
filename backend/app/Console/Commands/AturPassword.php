<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** php artisan integratia:atur-password demo  -> mengatur password akun lokal (disimpan sebagai hash bcrypt). */
class AturPassword extends Command
{
    protected $signature = 'integratia:atur-password {username : username akun}';
    protected $description = 'Atur password akun lokal (metode_login = lokal). Password diketik tanpa tampil di layar.';

    public function handle(): int
    {
        $username = $this->argument('username');
        $akun = DB::selectOne('SELECT id_user, metode_login FROM users WHERE username = :u', ['u' => $username]);
        if ($akun === null) {
            $this->error('Akun tidak ditemukan.');
            return self::FAILURE;
        }
        if ($akun->metode_login !== 'lokal') {
            $this->error("Akun '$username' memakai SSO. Password kampus diatur di CAS PNJ, bukan di sini.");
            return self::FAILURE;
        }
        $baru = (string) $this->secret('Password baru (minimal 12 karakter)');
        if ($baru !== (string) $this->secret('Ulangi password baru')) {
            $this->error('Kedua isian tidak sama.');
            return self::FAILURE;
        }
        if (mb_strlen($baru) < 12) {
            $this->error('Password minimal 12 karakter.');
            return self::FAILURE;
        }
        DB::update('UPDATE users SET password_hash = :h WHERE id_user = :i', ['h' => Hash::make($baru), 'i' => $akun->id_user]);
        $this->info('Password diperbarui.');
        return self::SUCCESS;
    }
}
