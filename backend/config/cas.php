<?php

// Pengaturan SSO PNJ (CAS). Nilai diambil dari .env.
return [
    // Alamat dasar CAS, tanpa garis miring akhir. Pengembangan: https://dev-id.pnj.ac.id/cas
    'server' => env('CAS_SERVER', 'https://dev-id.pnj.ac.id/cas'),

    // `service` yang didaftarkan di CAS. HARUS alamat FRONTEND (CAS hanya mengenal http://localhost:8081).
    // Frontend mengirim browser ke CAS dengan nilai yang sama persis.
    'service' => env('CAS_SERVICE_URL', 'http://localhost:8081/auth/callback'),

    // Tujuan setelah logout CAS (juga harus cocok dengan serviceId).
    'logout_service' => env('CAS_LOGOUT_SERVICE', 'http://localhost:8081/login'),

    // Jangan dimatikan di produksi.
    'verify_tls' => filter_var(env('CAS_VERIFY_TLS', true), FILTER_VALIDATE_BOOLEAN),
];
