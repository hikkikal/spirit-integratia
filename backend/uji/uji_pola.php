<?php
/** Uji usulan pola kelas (PolaKelas) dan SQL penandaan jenis kelas. Perubahan database dibatalkan di akhir.
 *  PGDATABASE=integratia_uji php uji/uji_pola.php  (hanya database *_uji) */
namespace App\Support {
    // Tiruan SinkronSister::jenisKelas dengan aturan yang sama (SinkronSister asli membutuhkan kelas lain).
    final class SinkronSister
    {
        public static function jenisKelas(string $kelas, array $pola): string
        {
            $k = mb_strtoupper(trim($kelas));
            foreach ($pola as $p) {
                $x = mb_strtoupper(trim((string) $p->pola));
                if ($x === '') { continue; }
                $c = match ($p->tipe) { 'akhiran' => str_ends_with($k, $x), 'awalan' => str_starts_with($k, $x), default => str_contains($k, $x) };
                if ($c) { return 'Kerja Sama'; }
            }
            return 'Reguler';
        }
    }
}
namespace {
require __DIR__ . '/../app/Support/PolaKelas.php';
use App\Support\PolaKelas;

$gagal = 0;
function cek(string $n, bool $ok, $info = null) { global $gagal; echo ($ok ? '  OK  ' : ' GAGAL ') . $n . "\n"; if (! $ok) { $gagal++; if ($info !== null) { echo '        ' . json_encode($info) . "\n"; } } }

echo "Kode dan tipe\n";
cek('kode: JGU7A -> JGU; 6AMSU -> MSU; TI-5A-CIT -> TI, CIT; 6B -> tidak ada', PolaKelas::kode('JGU7A') === ['JGU'] && PolaKelas::kode('6AMSU') === ['MSU'] && PolaKelas::kode('TI-5A-CIT') === ['TI', 'CIT'] && PolaKelas::kode('6B') === [] && PolaKelas::kode('(7A)') === []);
cek('tipe: JGU7A awalan, 6AMSU akhiran, X-JGU-7A mengandung', PolaKelas::tipe('JGU7A', 'JGU') === 'awalan' && PolaKelas::tipe('6AMSU', 'MSU') === 'akhiran' && PolaKelas::tipe('7A-JGU-X', 'JGU') === 'mengandung');

echo "Usulan\n";
$pola = [(object) ['tipe' => 'akhiran', 'pola' => 'CIT']];
$u = PolaKelas::usul(['6B', '7C', 'JGU7A', 'JGU5B', '6AMSU', '4BMSU', 'TI-5A-CIT', 'TI-3A', 'BDK6A', 'JGU7A'], ['CCIT', 'JGU', 'MSU', 'BADAK'], $pola);
$p = array_column($u['pakai'], null, 'pola');
cek('JGU -> awalan, mitra JGU, 2 kelas berbeda', ($p['JGU']['tipe'] ?? '') === 'awalan' && $p['JGU']['mitra'] === 'JGU' && $p['JGU']['jumlah'] === 2, $u);
cek('MSU -> akhiran, mitra MSU', ($p['MSU']['tipe'] ?? '') === 'akhiran' && $p['MSU']['mitra'] === 'MSU');
cek('kelas yang sudah dikenali pola CIT tidak diusulkan ulang (TI-5A-CIT tidak membuat kode TI/CIT)', ! isset($p['CIT']) && ! in_array('TI', array_column($u['belum'], 'kode'), true) || count(array_filter($u['belum'], fn ($b) => $b['kode'] === 'TI')) === 1);
cek('kode yang bukan nama mitra (BDK) hanya dilaporkan, tidak dipakai', array_column($u['belum'], 'kode') === array_values(array_filter(array_column($u['belum'], 'kode'), fn ($k) => ! isset($p[$k]))) && in_array('BDK', array_column($u['belum'], 'kode'), true) && ! isset($p['BDK']));
cek('kelas reguler (6B, 7C) tidak menghasilkan kode', ! in_array('B', array_column($u['belum'], 'kode'), true) && count(PolaKelas::kode('7C')) === 0);
$u2 = PolaKelas::usul(['JGU7A', '6AJGU'], ['JGU'], []);
cek('posisi kode tidak konsisten -> mengandung', $u2['pakai'][0]['tipe'] === 'mengandung', $u2);

echo "SQL penandaan\n";
$src = file_get_contents(__DIR__ . '/../app/Console/Commands/PolaKelasCommand.php');
preg_match('/TANDAI = "(.*?)";/s', $src, $m);
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('PGHOST') ?: 'localhost', getenv('PGPORT') ?: '5432', getenv('PGDATABASE') ?: 'integratia_uji');
$pdo = new PDO($dsn, getenv('PGUSER') ?: 'postgres', getenv('PGPASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]);
$nama = (string) $pdo->query('select current_database()')->fetchColumn();
if (! str_ends_with($nama, '_uji')) { fwrite(STDERR, "Menolak: database '$nama' bukan *_uji.\n"); exit(2); }
$pdo->beginTransaction();
$pdo->exec("INSERT INTO periode (id_smt, label, semester, tahun_ajaran) VALUES ('29991','Uji pola','Ganjil','2999/3000') ON CONFLICT DO NOTHING");
$d = '11111111-1111-4111-8111-111111111111';
$pdo->exec("INSERT INTO dosen (id_dosen, nama_sdm, jenis_sdm, status_aktif) VALUES ('$d','Uji Pola','Dosen','Aktif')");
foreach (['JGU7A', '6B', '6AMSU', 'TI-5A-CIT'] as $i => $kls) {
    $pdo->exec(sprintf("INSERT INTO pengajaran (id_pengajaran, id_dosen, id_smt, mata_kuliah, kelas, jenis_kelas, sks) VALUES ('22222222-2222-4222-8222-22222222222%d','$d','29991','MK','%s','Reguler',2)", $i, $kls));
}
$pdo->exec("DELETE FROM pola_kelas_kerja_sama");
foreach ([['awalan', 'JGU'], ['akhiran', 'MSU']] as [$t, $pl]) { $pdo->exec("INSERT INTO pola_kelas_kerja_sama (tipe, pola, mitra) VALUES ('$t','$pl','$pl')"); }
$n = $pdo->exec($m[1]);
$hasil = $pdo->query("SELECT kelas, jenis_kelas FROM pengajaran WHERE id_dosen = '$d' ORDER BY kelas")->fetchAll();
$j = array_column(array_map(fn ($r) => (array) $r, $hasil), 'jenis_kelas', 'kelas');
cek('SQL: JGU7A dan 6AMSU menjadi Kerja Sama; 6B dan TI-5A-CIT (belum ada polanya) tetap Reguler; 2 baris berubah', $j['JGU7A'] === 'Kerja Sama' && $j['6AMSU'] === 'Kerja Sama' && $j['6B'] === 'Reguler' && $j['TI-5A-CIT'] === 'Reguler' && $n === 2, [$j, $n]);
$pdo->rollBack();
echo $gagal === 0 ? "\nSEMUA UJI LOLOS\n" : "\n$gagal GAGAL\n";
exit($gagal === 0 ? 0 : 1);
}
