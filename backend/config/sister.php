<?php

/**
 * Pengaturan sinkronisasi SISTER. Isi nilainya di .env (JANGAN ditulis di sini atau dipush ke GitHub):
 *   SISTER_BASE_URL, SISTER_USERNAME, SISTER_PASSWORD, SISTER_ID_PENGGUNA   (nilai persis seperti di man-akses SISTER)
 * Selama SISTER_BASE_URL kosong, tombol Sinkronisasi memakai simulasi (tanpa menghubungi SISTER, tanpa mengubah data).
 */
return [
    // 'sister' = menghubungi SISTER sungguhan; 'tiruan' = simulasi. Bisa dipaksa lewat SINKRON_MODE.
    'mode' => env('SINKRON_MODE', env('SISTER_BASE_URL') ? 'sister' : 'tiruan'),

    'base_url' => rtrim((string) env('SISTER_BASE_URL', ''), '/'),
    'username' => env('SISTER_USERNAME'),
    'password' => env('SISTER_PASSWORD'),
    'id_pengguna' => env('SISTER_ID_PENGGUNA'),

    'timeout' => (int) env('SISTER_TIMEOUT', 30),          // detik per request
    'ulang' => (int) env('SISTER_ULANG', 3),               // percobaan ulang untuk 429, 5xx, atau tidak ada balasan
    'jeda_ulang' => (float) env('SISTER_JEDA_ULANG', 2),   // detik; dikali nomor percobaan
    'jeda_ms' => (int) env('SISTER_JEDA_MS', 125),         // jeda antar request supaya SISTER tidak terbebani
    // Sinkronisasi dianggap mati bila TIDAK ADA denyut (log kemajuan) selama ini; total lama proses tidak dibatasi (satu semester bisa 1,5 jam lebih).
    'batas_diam_menit' => (int) env('SISTER_BATAS_DIAM_MENIT', 10),
];
