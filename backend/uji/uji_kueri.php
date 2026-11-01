<?php
/**
 * Uji kueri dan pembentuk JSON langsung ke PostgreSQL, tanpa Laravel.
 *   php uji/uji_kueri.php
 * Lingkungan: PGHOST (localhost), PGPORT (5432), PGDATABASE (integratia), PGUSER (postgres), PGPASSWORD.
 */
require __DIR__ . '/../app/Support/Kueri.php';
require __DIR__ . '/../app/Support/Bentuk.php';

use App\Support\Bentuk;
use App\Support\Kueri;

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('PGHOST') ?: 'localhost', getenv('PGPORT') ?: '5432', getenv('PGDATABASE') ?: 'integratia');
$pdo = new PDO($dsn, getenv('PGUSER') ?: 'postgres', getenv('PGPASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]);

function sel(string $sql, array $b = []): array { global $pdo; $s = $pdo->prepare($sql); $s->execute($b); return $s->fetchAll(); }
function one(string $sql, array $b = []) { return sel($sql, $b)[0] ?? null; }

$gagal = 0;
function cek(string $nama, bool $ok, $info = ''): void
{
    global $gagal;
    echo ($ok ? '  OK    ' : '  GAGAL ') . $nama . ($ok ? '' : "  -> " . json_encode($info)) . "\n";
    $gagal += $ok ? 0 : 1;
}

echo "Periode\n";
$smt = sel(Kueri::PERIODE_SEMUA);
cek('daftar periode berisi 2 label', count($smt) === 2, $smt);
$p = one(Kueri::PERIODE_TERBARU_BERDATA);
cek('periode terbaru berdata ada', $p !== null);
$p2 = one(Kueri::PERIODE_BY_LABEL, ['label' => 'Genap 2025/2026']);
cek('periode berdasarkan label', $p2 && $p2->id_smt === '20252', $p2);

echo "Pengguna dan peran\n";
$b = one(Kueri::USER_BY_USERNAME, ['username' => 'dosen.b']);
cek('akun dosen.b ada dan terhubung ke dosen', $b && $b->id_dosen, $b);
$roles = array_map(fn ($r) => Bentuk::kodeKeFrontend($r->kode), sel(Kueri::ROLES_USER, ['id_user' => $b->id_user]));
cek('Dosen B berperan dosen + pimpinan', $roles === ['dosen', 'pimpinan'], $roles);
$ka = one(Kueri::USER_BY_USERNAME, ['username' => 'admin.input']);
$rk = array_map(fn ($r) => Bentuk::kodeKeFrontend($r->kode), sel(Kueri::ROLES_USER, ['id_user' => $ka->id_user]));
cek("peran admin_input dipetakan menjadi 'admin'", $rk === ['admin'], $rk);
cek('akun tidak dikenal tidak ditemukan', one(Kueri::USER_BY_USERNAME, ['username' => "x' OR '1'='1"]) === null);

echo "Halaman Dosen (Dosen B)\n";
$id = $b->id_dosen;
$profil = Bentuk::profil(one(Kueri::PROFIL, ['id' => $id]));
cek('profil: nama dan nomor tersamar', $profil['nama'] === 'Dosen B' && str_contains($profil['nip'], '*') && ! preg_match('/^\d+$/', $profil['nip']), $profil);
$riw = Bentuk::riwayat(sel(Kueri::RIWAYAT, ['id' => $id]));
cek('riwayat: 2 periode, terbaru di atas', count($riw) === 2 && $riw[0]['periode'] === 'Ganjil 2026/2027', $riw);
cek('riwayat: membawa tingkat 6/4/2 dan bruto', $riw[1]['sks100'] === 6 && $riw[1]['sks50'] === 4 && $riw[1]['sks0'] === 2 && $riw[1]['bruto'] === 1040000, $riw[1]);
cek('profil: email dari tabel users', str_contains((string) $profil['email'], '@'), $profil);
cek('riwayat: status Genap 2025/2026 = terverifikasi', $riw[1]['status'] === 'terverifikasi' && $riw[0]['status'] === 'menunggu', $riw);
$kel = Bentuk::kelasPeriode(sel(Kueri::KELAS_PERIODE, ['id' => $id, 'smt' => '20252']));
cek('rincian kelas: 6 kelas, total 22 SKS', count($kel) === 6 && array_sum(array_column($kel, 'sks')) === 22, $kel);
$d = Bentuk::detailHonor(one(Kueri::PERHITUNGAN, ['id' => $id, 'smt' => '20252']), sel(Kueri::DETAIL_KELAS, ['id' => $id, 'smt' => '20252']), sel(Kueri::TARIF_AKTIF));
cek('detail: 12 SKS lebih, bruto 1.040.000, pajak 52.000, netto 988.000',
    $d['sksLebih'] === 12 && $d['total']['bruto'] === 1040000 && $d['total']['pajak'] === 52000 && $d['total']['netto'] === 988000, $d['total']);
cek('detail: tingkat 100/50/0 = 6/4/2', [$d['total']['lebih100'], $d['total']['lebih50'], $d['total']['lebih0']] === [6, 4, 2], $d['total']);
cek('detail: tarif reguler 130.000 dan kerja sama 250.000', $d['tarifReguler'] === 130000 && $d['tarifKerjaSama'] === 250000, [$d['tarifReguler'], $d['tarifKerjaSama']]);
cek('detail: jumlah bruto per kelas = total', array_sum(array_column($d['kelas'], 'bruto')) === $d['total']['bruto']);
cek('detail: label kelas "Mata Kuliah (Kelas)"', str_contains($d['kelas'][0]['mk'], '(') && $d['golongan'] === 'Gol III', $d['kelas'][0]);
cek('detail periode tanpa data -> null', Bentuk::detailHonor(one(Kueri::PERHITUNGAN, ['id' => $id, 'smt' => '20199']), [], []) === null);

echo "Halaman Keuangan dan Pimpinan\n";
$dash = Bentuk::dashboard(sel(Kueri::DASHBOARD, ['smt' => '20252']), (int) one(Kueri::HONOR_TERVERIFIKASI, ['smt' => '20252'])->n);
$dh = Bentuk::dataHonor(sel(Kueri::DATA_HONOR, ['smt' => '20252', 'jur1' => '', 'jur2' => '', 'q1' => '', 'q2' => '']));
cek('dashboard: 7 jurusan', count($dash['jurusan']) === 7, count($dash['jurusan']));
cek('dashboard: total dosen = jumlah baris data honor', $dash['total']['dosen'] === count($dh), [$dash['total']['dosen'], count($dh)]);
cek('dashboard: total honor = jumlah honor per dosen', $dash['total']['honor'] === array_sum(array_column($dh, 'honor')));
cek('dashboard: honor terverifikasi <= total honor dan > 0', $dash['honorTerverifikasi'] > 0 && $dash['honorTerverifikasi'] <= $dash['total']['honor'], $dash['honorTerverifikasi']);
cek('dashboard: terverifikasi + menunggu = dosen', $dash['total']['terverifikasi'] + $dash['total']['menunggu'] === $dash['total']['dosen']);
$f = Bentuk::dataHonor(sel(Kueri::DATA_HONOR, ['smt' => '20252', 'jur1' => 'Akuntansi', 'jur2' => 'Akuntansi', 'q1' => '', 'q2' => '']));
cek('filter jurusan Akuntansi', count($f) > 0 && count(array_unique(array_column($f, 'jurusan'))) === 1, count($f));
$c = Bentuk::dataHonor(sel(Kueri::DATA_HONOR, ['smt' => '20252', 'jur1' => '', 'jur2' => '', 'q1' => 'dosen b', 'q2' => 'dosen b']));
cek('cari nama "dosen b" (tak peka huruf besar)', count($c) === 1 && $c[0]['nama'] === 'Dosen B', $c);
$inj = sel(Kueri::DATA_HONOR, ['smt' => '20252', 'jur1' => '', 'jur2' => '', 'q1' => "'; DROP TABLE dosen;--", 'q2' => "'; DROP TABLE dosen;--"]);
cek('input berbahaya diperlakukan sebagai teks biasa', count($inj) === 0 && one('SELECT count(*) AS n FROM dosen')->n > 0);
$tar = Bentuk::tarif(sel(Kueri::TARIF_AKTIF), sel(Kueri::PAJAK), sel(Kueri::ATURAN));
cek('tarif & ketentuan terisi', count($tar['tarif']) === 2 && count($tar['pajak']) === 2 && isset($tar['aturan']['batas_sks_100']), $tar);

echo "Verifikasi (dibatalkan di akhir)\n";
$pdo->beginTransaction();
$draf = one("SELECT id_dosen FROM perhitungan WHERE id_smt='20252' AND status_verifikasi IN ('draf','disesuaikan') LIMIT 1");
$keu = one(Kueri::USER_BY_USERNAME, ['username' => 'keuangan']);
if ($draf) {
    $s = $pdo->prepare(Kueri::VERIFIKASI); $s->execute(['pengguna' => $keu->id_user, 'id' => $draf->id_dosen, 'smt' => '20252']);
    cek('verifikasi pertama berhasil (1 baris)', $s->rowCount() === 1);
    $s->execute(['pengguna' => $keu->id_user, 'id' => $draf->id_dosen, 'smt' => '20252']);
    cek('verifikasi kedua ditolak (0 baris)', $s->rowCount() === 0);
    $st = one("SELECT status_verifikasi, diverifikasi_oleh FROM perhitungan WHERE id_dosen=:id AND id_smt='20252'", ['id' => $draf->id_dosen]);
    cek('status dan pengverifikasi tercatat', $st->status_verifikasi === 'terverifikasi' && $st->diverifikasi_oleh === $keu->id_user);
    $s = $pdo->prepare(Kueri::LOG); $s->execute(['pelaku' => $keu->id_user, 'aksi' => 'Verifikasi honor', 'objek' => 'uji']);
    cek('log aktivitas tertulis', $s->rowCount() === 1);
} else {
    cek('ada perhitungan berstatus draf untuk diuji', false);
}
$pdo->rollBack();

echo $gagal === 0 ? "\nSEMUA UJI LOLOS\n" : "\n$gagal UJI GAGAL\n";
exit($gagal === 0 ? 0 : 1);
