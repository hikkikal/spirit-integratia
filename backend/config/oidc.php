<?php

// Pengaturan SSO PNJ (OpenID Connect: Authorization Code + PKCE). Nilai diambil dari .env.
return [
    // Alamat issuer. Endpoint authorize/token/userinfo/logout dibaca dari {issuer}/.well-known/openid-configuration.
    'issuer' => rtrim((string) env('OIDC_ISSUER', 'https://dev-id.pnj.ac.id/cas/oidc'), '/'),

    'client_id' => env('OIDC_CLIENT_ID'),
    // RAHASIA: hanya di .env backend. Jangan di frontend, jangan di-commit.
    'client_secret' => env('OIDC_CLIENT_SECRET'),

    // Harus sama persis dengan serviceId yang terdaftar di SSO, dan alamat FRONTEND (browser kembali ke sini).
    'redirect_uri' => env('OIDC_REDIRECT_URI', 'http://localhost:3000/auth/callback'),

    'scopes' => env('OIDC_SCOPES', 'openid profile email publicacademia.read publicpersonalia.read'),

    // Claim yang dipakai sebagai identitas (NIP/NIM/username). Sesuaikan setelah melihat isi UserInfo.
    'claim_username' => env('OIDC_CLAIM_USERNAME', 'preferred_username'),
    'claim_email' => env('OIDC_CLAIM_EMAIL', 'email'),
    'claim_nama' => env('OIDC_CLAIM_NAMA', 'name'),

    // Tujuan setelah logout SSO (harus terdaftar di SSO). Kosongkan bila belum didaftarkan.
    'post_logout_uri' => env('OIDC_POST_LOGOUT_URI'),

    // Sementara untuk mencoba: catat isi UserInfo (data pribadi!) ke storage/logs/laravel.log. Matikan setelah claim diketahui.
    'log_claims' => filter_var(env('OIDC_LOG_CLAIMS', false), FILTER_VALIDATE_BOOLEAN),

    // Jangan dimatikan di produksi.
    'verify_tls' => filter_var(env('OIDC_VERIFY_TLS', true), FILTER_VALIDATE_BOOLEAN),
];
