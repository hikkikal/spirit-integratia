<?php

// Open API PNJ (Academia). Dipanggil dengan access token SSO milik pengguna yang sedang login (scope publicacademia.read).
// Hanya bisa dijangkau dari jaringan PNJ.
return [
    // Cara mengambil token Academia (urutan): 
    //  1) ACADEMIA_TOKEN  = token tetap dari TIK (seperti token SISTER), dipakai langsung;
    //  2) ACADEMIA_CLIENT_ID/SECRET = token otomatis lewat grant client_credentials (harus diaktifkan TIK pada layanan SSO);
    //  3) selain itu memakai token SSO milik pengguna yang login.
    'token' => env('ACADEMIA_TOKEN'),
    'client_id' => env('ACADEMIA_CLIENT_ID', env('OIDC_CLIENT_ID')),
    'client_secret' => env('ACADEMIA_CLIENT_SECRET', env('OIDC_CLIENT_SECRET')),
    'client_credentials' => filter_var(env('ACADEMIA_CLIENT_CREDENTIALS', false), FILTER_VALIDATE_BOOLEAN),
    'token_url' => env('ACADEMIA_TOKEN_URL', rtrim((string) env('OIDC_ISSUER', 'https://dev-id.pnj.ac.id/cas/oidc'), '/') . '/accessToken'),
    'scope' => env('ACADEMIA_SCOPE', 'publicacademia.read publicpersonalia.read'),   // kosongkan (ACADEMIA_SCOPE=) untuk tidak mengirim scope
    'token_auth' => env('ACADEMIA_TOKEN_AUTH', 'basic'),    // 'basic' (header) atau 'post' (client_id/secret di badan permintaan)

    'url' => rtrim((string) env('PNJ_API_URL', 'https://dev-api.pnj.ac.id'), '/'),

    'timeout' => (int) env('ACADEMIA_TIMEOUT', 30),         // detik per request
    'ulang' => (int) env('ACADEMIA_ULANG', 3),              // percobaan ulang untuk 429, 5xx, atau tidak ada balasan
    'jeda_ulang' => (float) env('ACADEMIA_JEDA_ULANG', 2),
    'jeda_ms' => (int) env('ACADEMIA_JEDA_MS', 100),        // jeda antar request supaya Open API tidak terbebani

    // Jangan dimatikan di produksi.
    'verify_tls' => filter_var(env('ACADEMIA_VERIFY_TLS', true), FILTER_VALIDATE_BOOLEAN),
];
