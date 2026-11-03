<?php
/**
 * Uji service hitung honor langsung ke PostgreSQL (tanpa Laravel). Semua perubahan dibatalkan di akhir.
 *   PGHOST=127.0.0.1 PGPASSWORD=... php uji/uji_hitung.php
 */
require __DIR__ . '/../app/Support/HitungHonor.php';

use App\Support\HitungHonor as H;

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('PGHOST') ?: 'localhost', getenv('PGPORT') ?: '5432', getenv('PGDATABASE') ?: 'integratia');
$pdo = new PDO($dsn, getenv('PGUSER') ?: 'postgres', getenv('PGPASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]);
$baca = function (string $sql, array $b = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($b); return $s->fetchAll(); };
$tulis = function (string $sql, array $b = []) use ($pdo) { $pdo->prepare($sql)->execute($b); };
$one = fn ($sql, $b = []) => ($baca($sql, $b))[0] ?? null;
$gagal = 0;
function cek(string $n, bool $ok, $info = ''): void { global $gagal; echo ($ok ? '  OK    ' : '  GAGAL ') . $n . ($ok ? '' : '  -> ' . json_encode($info)) . "\n"; $gagal += $ok ? 0 : 1; }

echo "Inti hitung (tanpa database)\n";
$t130 = fn ($j) => ['id' => 1, 'honor' => $j === 'Reguler' ? 130000 : 250000];
$r = H::hitung([['id' => 'a', 'sks' => 1000, 'jenis' => 'Reguler'], ['id' => 'b', 'sks' => 1200, 'jenis' => 'Reguler']], 1000, 600, 400, 500, $t130);
cek('10 wajib + 12 lebih: 6/4/2 SKS, bruto 1.040.000, pajak 52.000', $r['total']['s100'] === 600 && $r['total']['s50'] === 400 && $r['total']['s0'] === 200 && $r['total']['bruto'] === 1040000 && $r['total']['pajak'] === 52000 && $r['total']['netto'] === 988000, $r['total']);
$r = H::hitung([['id' => 'a', 'sks' => 600, 'jenis' => 'Reguler']], 1200, 600, 400, 500, $t130);
cek('di bawah wajib: tidak ada honor, SKS lebih 0', $r['total']['bruto'] === 0 && $r['total']['lebih'] === 0 && $r['total']['wajib'] === 600, $r['total']);
$r = H::hitung([['id' => 'a', 'sks' => 250, 'jenis' => 'Reguler']], 0, 600, 400, 1500, fn ($j) => ['id' => 1, 'honor' => 123457]);
cek('SKS pecahan + tarif ganjil: bruto 308.643 (dibulatkan), pajak 46.296', $r['total']['bruto'] === 308643 && $r['total']['pajak'] === 46296, $r['total']);
$r = H::hitung([['id' => 'a', 'sks' => 100, 'jenis' => 'Reguler']], 0, 0, 100, 500, fn ($j) => ['id' => 1, 'honor' => 130001]);
cek('50%: 130001 x 0,5 = 65000,5 dibulatkan ke 65001', $r['total']['bruto'] === 65001, $r['total']);
cek('tanggal acuan Ganjil 2026/2027 = 2026-09-01; Genap 2025/2026 = 2026-03-01', H::tanggalAcuan('Ganjil', '2026/2027') === '2026-09-01' && H::tanggalAcuan('Genap', '2025/2026') === '2026-03-01');
$tr = [(object) ['id_tarif' => 1, 'jenis_kelas' => 'Reguler', 'jabatan' => null, 'tarif_per_sks' => 130000, 'berlaku_mulai' => '2026-01-01', 'berlaku_sampai' => null],
       (object) ['id_tarif' => 5, 'jenis_kelas' => 'Reguler', 'jabatan' => 'Lektor III/c - Penata', 'tarif_per_sks' => 140000, 'berlaku_mulai' => '2026-01-01', 'berlaku_sampai' => '2026-12-31'],
       (object) ['id_tarif' => 6, 'jenis_kelas' => 'Reguler', 'jabatan' => 'Lektor III/d - Penata Tingkat I', 'tarif_per_sks' => 140000, 'berlaku_mulai' => '2026-01-01', 'berlaku_sampai' => '2026-12-31']];
cek('tarif per jabatan dipakai bila nilainya tunggal', H::pilihTarif($tr, 'Reguler', 'Lektor', 'III', '2026-09-01')['honor'] === 140000);
$tr[2]->tarif_per_sks = 150000;
cek('nilai berbeda untuk jabatan yang sama (ambigu) -> tarif umum', H::pilihTarif($tr, 'Reguler', 'Lektor', 'III', '2026-09-01')['sumber'] === 'umum');
cek('tarif kedaluwarsa tidak dipakai -> tarif umum', H::pilihTarif($tr, 'Reguler', 'Lektor', 'III', '2027-02-01')['sumber'] === 'umum');
cek('"Lektor" tidak salah cocok dengan "Lektor Kepala IV/a"', H::pilihTarif([(object) ['id_tarif' => 9, 'jenis_kelas' => 'Reguler', 'jabatan' => 'Lektor Kepala IV/a - Pembina', 'tarif_per_sks' => 1, 'berlaku_mulai' => null, 'berlaku_sampai' => null]], 'Reguler', 'Lektor', 'III', '2026-09-01') === null);

echo "Database (dibatalkan di akhir)\n";
$pdo->beginTransaction();
$ambil = function () use ($baca) {
    $p = []; foreach ($baca("SELECT id_dosen, id_smt, sks_wajib::float AS w, sks_terlaksana::float AS t, sks_lebih::float AS l, sks_100::float AS a, sks_50::float AS b, sks_0::float AS c, bruto, pajak, netto, sumber_sks_wajib FROM perhitungan ORDER BY id_dosen, id_smt") as $r) $p["$r->id_dosen|$r->id_smt"] = (array) $r;
    $d = []; foreach ($baca("SELECT p.id_dosen, p.id_smt, dp.id_pengajaran, dp.urutan, dp.sks_kelas::float AS k, dp.sks_wajib_terpakai::float AS w, dp.sks_100::float AS a, dp.sks_50::float AS b, dp.sks_0::float AS c, dp.jenis_kelas, dp.tarif_per_sks, dp.tarif_pph::float AS pph, dp.bruto, dp.pajak, dp.netto FROM detail_perhitungan dp JOIN perhitungan p USING (id_perhitungan)") as $r) $d["$r->id_dosen|$r->id_smt|$r->id_pengajaran"] = (array) $r;
    return [$p, $d];
};
$smts = array_map(fn ($r) => $r->id_smt, $baca('SELECT id_smt FROM periode ORDER BY id_smt'));
[$p0, $d0] = $ambil();
$statusAsli = $baca("SELECT id_dosen, id_smt, status_verifikasi, diverifikasi_oleh, diverifikasi_pada FROM perhitungan");

// (1) tanpa tarif per jabatan, semua draf: hasil harus SAMA dengan seed (skrip Python)
$tulis("UPDATE master_tarif SET aktif = false WHERE jabatan IS NOT NULL");
$tulis("UPDATE perhitungan SET status_verifikasi = 'draf', diverifikasi_oleh = NULL, diverifikasi_pada = NULL");
$tot = 0; foreach ($smts as $s) { $h = H::hitungPeriode($baca, $tulis, $s); $tot += $h['dihitung']; }
[$p1, $d1] = $ambil();
cek('menghitung 76 dosen-periode', $tot === 76 && count($p1) === 76, [$tot, count($p1)]);
$beda = 0; $contoh = null;
foreach ($p0 as $k => $a) { $b = $p1[$k] ?? null; if ($b === null || $a !== $b) { $beda++; $contoh ??= [$a, $b]; } }
cek('ringkasan 76 perhitungan IDENTIK dengan hasil seed (SKS, bruto, pajak, netto, sumber wajib)', $beda === 0, $contoh);
$bedaD = 0; $contohD = null;
foreach ($d0 as $k => $a) { $b = $d1[$k] ?? null; if ($b === null || $a !== $b) { $bedaD++; $contohD ??= [$a, $b]; } }
cek('388 baris detail kelas IDENTIK dengan seed (urutan, 100/50/0, tarif, pajak)', $bedaD === 0 && count($d1) === 388, [$bedaD, count($d1), $contohD]);
$idDosenB = $one("SELECT id_dosen FROM dosen WHERE nama_sdm = 'Dosen B'")->id_dosen;
$b = $one("SELECT sks_wajib::float AS w, sumber_sks_wajib, bruto, pajak, netto FROM perhitungan WHERE id_dosen = :i AND id_smt = '20252'", ['i' => $idDosenB]);
cek('Dosen B: wajib manual 10 dipertahankan; bruto 1.040.000, pajak 52.000, netto 988.000', $b->w == 10 && $b->sumber_sks_wajib === 'manual' && $b->bruto == 1040000 && $b->pajak == 52000 && $b->netto == 988000, $b);
[$pa] = $ambil(); H::hitungPeriode($baca, $tulis, '20252'); [$pb] = $ambil();
cek('hitung ulang dua kali menghasilkan angka yang sama (idempoten)', $pa === $pb);

// (2) tarif per jabatan (data Admin Input) ikut berpengaruh
$tulis("UPDATE master_tarif SET aktif = true WHERE jabatan IS NOT NULL");
foreach ($smts as $s) H::hitungPeriode($baca, $tulis, $s);
[$p2, $d2] = $ambil();
$ubah = array_filter($p2, fn ($r, $k) => $r['bruto'] != $p1[$k]['bruto'], ARRAY_FILTER_USE_BOTH);
$dipakai = (int) $one("SELECT count(*) AS n FROM detail_perhitungan dp JOIN master_tarif t ON t.id_tarif = dp.id_tarif WHERE t.jabatan IS NOT NULL")->n;
cek('tarif Admin Input dipakai di sebagian detail (dan tercatat di id_tarif)', $dipakai > 0 && count($ubah) > 0 && count($ubah) < 76, [$dipakai, count($ubah)]);
$konsis = $one("SELECT count(*) AS n FROM perhitungan p WHERE bruto <> (SELECT coalesce(sum(bruto),0) FROM detail_perhitungan WHERE id_perhitungan = p.id_perhitungan) OR pajak <> (SELECT coalesce(sum(pajak),0) FROM detail_perhitungan WHERE id_perhitungan = p.id_perhitungan) OR netto <> bruto - pajak")->n;
cek('total tiap perhitungan = jumlah detailnya (bruto, pajak, netto)', (int) $konsis === 0, $konsis);

// (3) mengubah tarif umum mengubah honor
$tulis("UPDATE master_tarif SET aktif = false WHERE jabatan IS NOT NULL");
$tulis("UPDATE master_tarif SET tarif_per_sks = 200000 WHERE jabatan IS NULL AND jenis_kelas = 'Reguler'");
H::hitungPeriode($baca, $tulis, '20252');
$b2 = $one("SELECT bruto FROM perhitungan WHERE id_dosen = :i AND id_smt = '20252'", ['i' => $idDosenB]);
cek('tarif reguler 130.000 -> 200.000: honor Dosen B naik', $b2->bruto > 1040000, $b2);

// (4) status terverifikasi/terkunci tidak ditimpa
$tulis("UPDATE perhitungan SET status_verifikasi = 'terverifikasi' WHERE id_dosen = :i AND id_smt = '20252'", ['i' => $idDosenB]);
$brutoSebelum = $one("SELECT bruto FROM perhitungan WHERE id_dosen = :i AND id_smt = '20252'", ['i' => $idDosenB])->bruto;
$tulis("UPDATE master_tarif SET tarif_per_sks = 300000 WHERE jabatan IS NULL AND jenis_kelas = 'Reguler'");
$h = H::hitungPeriode($baca, $tulis, '20252');
$sesudah = $one("SELECT bruto, status_verifikasi FROM perhitungan WHERE id_dosen = :i AND id_smt = '20252'", ['i' => $idDosenB]);
cek('perhitungan terverifikasi TIDAK berubah dan dilaporkan dilewati', $sesudah->bruto == $brutoSebelum && $sesudah->status_verifikasi === 'terverifikasi' && $h['dilewati']['terverifikasi'] >= 1, [$sesudah, $h['dilewati']]);
$tulis("UPDATE periode SET terkunci = true WHERE id_smt = '20252'");
try { H::hitungPeriode($baca, $tulis, '20252'); cek('periode terkunci ditolak', false); } catch (DomainException $e) { cek('periode terkunci ditolak (409)', $e->getCode() === 409); }
try { H::hitungPeriode($baca, $tulis, '19999'); cek('periode tak ada ditolak', false); } catch (DomainException $e) { cek('periode tidak ada -> 404', $e->getCode() === 404); }
$pdo->rollBack();
$sisa = $one("SELECT count(*) AS n FROM perhitungan WHERE status_verifikasi = 'terverifikasi'")->n;
cek('semua perubahan uji dibatalkan', (int) $sisa === count(array_filter($statusAsli, fn ($r) => $r->status_verifikasi === 'terverifikasi')));

echo $gagal === 0 ? "\nSEMUA UJI LOLOS\n" : "\n$gagal UJI GAGAL\n";
exit($gagal === 0 ? 0 : 1);
