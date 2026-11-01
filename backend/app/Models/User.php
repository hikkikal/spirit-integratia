<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** Tabel `users` milik Integratia (kunci uuid, tanpa kata sandi: login lewat SSO atau login tiruan lokal). */
class User extends Authenticatable
{
    protected $table = 'users';
    protected $primaryKey = 'id_user';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    public function getAuthPassword()
    {
        return '';
    }

    public function getRememberToken()
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName()
    {
        return '';
    }
}
