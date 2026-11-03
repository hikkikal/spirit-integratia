<?php

return [
    // Login username + password untuk akun ber-metode_login 'lokal' (akun demo/darurat). Matikan di produksi bila hanya SSO yang dipakai.
    'aktif' => (bool) env('LOGIN_LOKAL', env('APP_ENV') === 'local'),
    // Hash bcrypt dari teks acak; hanya untuk menyamakan waktu pemeriksaan saat akun tidak ditemukan.
    'hash_tiruan' => '$2y$10$NQZeXeNgInttSC2NtmXfZudDIAGtjiVqFV2lqSJwZyk4V75bQl7/G',
];
