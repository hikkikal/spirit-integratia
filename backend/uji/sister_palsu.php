<?php
/**
 * SISTER PALSU untuk pengujian sinkronisasi (bukan bagian aplikasi). Dijalankan oleh uji_sinkron.php:
 *   SISTER_PALSU_DATA=data.json SISTER_PALSU_STATE=state.json php -S 127.0.0.1:8099 uji/sister_palsu.php
 * Data dari berkas JSON; kegagalan disuntikkan lewat berkas state (diubah oleh pengujian):
 *   gagal:      {"/bkd/ajar": 504}        endpoint ini selalu menjawab kode itu
 *   tidak_ada:  ["/pengajaran/<id>"]      path persis ini menjawab 404
 *   rotasi:     true                      token yang sedang berlaku dibatalkan SEKALI (GET berikutnya 401)
 *   gagal_sekali: {"/bkd/ajar": 429}      menjawab kode itu hanya pada panggilan pertama
 * Semua panggilan dicatat di <state>.log.
 */
$data = json_decode(file_get_contents(getenv('SISTER_PALSU_DATA')), true);
$stFile = getenv('SISTER_PALSU_STATE');
$st = is_file($stFile) ? (json_decode(file_get_contents($stFile), true) ?: []) : [];
$simpan = function () use (&$st, $stFile) { file_put_contents($stFile, json_encode($st)); };
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$metode = $_SERVER['REQUEST_METHOD'];
file_put_contents($stFile . '.log', json_encode([$metode, $path, $_GET]) . PHP_EOL, FILE_APPEND);

function jawab(int $kode, $isi): never
{
    http_response_code($kode);
    header('Content-Type: application/json');
    echo json_encode($isi);
    exit;
}

if ($path === '/authorize' && $metode === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?: [];
    $k = $data['kredensial'];
    if (($b['username'] ?? '') !== $k['username'] || ($b['password'] ?? '') !== $k['password'] || ($b['id_pengguna'] ?? '') !== $k['id_pengguna']) {
        jawab(401, ['message' => 'Unauthorized']);
    }
    $st['nomor'] = ($st['nomor'] ?? 0) + 1;
    $st['aktif'] = 'tok-' . $st['nomor'];
    $simpan();
    jawab(200, ['token' => $st['aktif']]);
}

if (($st['rotasi'] ?? false) === true) {
    $st['rotasi'] = false;
    $st['aktif'] = 'dibatalkan';
    $simpan();
}
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (($st['aktif'] ?? null) === null || $auth !== 'Bearer ' . $st['aktif']) {
    jawab(401, ['message' => 'Unauthorized']);
}
if (isset($st['gagal'][$path])) {
    jawab((int) $st['gagal'][$path], ['message' => 'galat uji']);
}
if (isset($st['gagal_sekali'][$path])) {
    $kode = (int) $st['gagal_sekali'][$path];
    unset($st['gagal_sekali'][$path]);
    $simpan();
    jawab($kode, ['message' => 'galat uji sekali']);
}
if (in_array($path, $st['tidak_ada'] ?? [], true)) {
    jawab(404, ['message' => 'Not found']);
}

$sdm = $_GET['id_sdm'] ?? '';
$smt = $_GET['id_semester'] ?? ($_GET['id_smt'] ?? '');
switch (true) {
    case $path === '/referensi/jabatan_fungsional': jawab(200, $data['jab']);
    case $path === '/referensi/sdm': jawab(200, $data['sdm']);
    case $path === '/pengajaran': jawab(200, $data['pengajaran'][$sdm][$smt] ?? []);
    case preg_match('#^/pengajaran/([^/]+)$#', $path, $m) === 1: isset($data['detail'][$m[1]]) ? jawab(200, $data['detail'][$m[1]]) : jawab(404, ['message' => 'Not found']);
    case $path === '/bkd/ajar': jawab(200, $data['bkd_ajar'][$sdm][$smt] ?? []);
    case $path === '/bkd/laporan_akhir_bkd': jawab(200, $data['bkd_akhir'][$sdm] ?? []);
    case $path === '/bimbingan_mahasiswa': jawab(200, $data['bimbingan'][$sdm][$smt] ?? []);
    case preg_match('#^/bimbingan_mahasiswa/([^/]+)$#', $path, $m) === 1: isset($data['bimbingan_detail'][$m[1]]) ? jawab(200, $data['bimbingan_detail'][$m[1]]) : jawab(404, ['message' => 'Not found']);
    case $path === '/pengujian_mahasiswa': jawab(200, $data['pengujian'][$sdm][$smt] ?? []);
    case preg_match('#^/pengujian_mahasiswa/([^/]+)$#', $path, $m) === 1: isset($data['pengujian_detail'][$m[1]]) ? jawab(200, $data['pengujian_detail'][$m[1]]) : jawab(404, ['message' => 'Not found']);
    case $path === '/tugas_tambahan': jawab(200, $data['tugas'][$sdm] ?? []);
    case $path === '/bkd/tunjang': jawab(200, $data['tunjang'][$sdm][$smt] ?? []);
}
jawab(404, ['message' => 'Not found']);
