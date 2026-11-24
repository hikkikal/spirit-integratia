<?php
/**
 * Uji sinkronisasi SISTER: SinkronSister + SisterKlien + SQL terhadap PostgreSQL, dengan SISTER palsu (uji/sister_palsu.php).
 *   PGDATABASE=integratia_uji php uji/uji_sinkron.php   (PGHOST, PGPORT, PGUSER, PGPASSWORD seperti uji_kueri.php)
 * PERINGATAN: uji ini MENULIS dan MENGUBAH data (tidak dibatalkan di akhir), jadi hanya mau jalan di database yang namanya berakhiran _uji.
 * Buat: createdb integratia_uji; lalu muat db/schema.sql dan db/seed.sql ke sana. Jangan jalankan pada database kerja.
 * Controller dan perintah artisan butuh Laravel dan tidak diuji di sini.
 */
require __DIR__ . '/../app/Support/Kueri.php';
require __DIR__ . '/../app/Support/Bentuk.php';
require __DIR__ . '/../app/Support/SisterKlien.php';
require __DIR__ . '/../app/Support/SinkronSister.php';

use App\Support\Bentuk;
use App\Support\Kueri;
use App\Support\SinkronSister;
use App\Support\SisterGalat;
use App\Support\SisterKlien;

$namaDb = getenv('PGDATABASE') ?: 'integratia';
if (! str_ends_with($namaDb, '_uji')) {
    fwrite(STDERR, "Ditolak: uji ini mengubah data. Pakai database khusus uji, mis. PGDATABASE=integratia_uji (nama harus berakhiran _uji). Sekarang: $namaDb\n");
    exit(2);
}
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('PGHOST') ?: 'localhost', getenv('PGPORT') ?: '5432', getenv('PGDATABASE') ?: 'integratia');
$pdo = new PDO($dsn, getenv('PGUSER') ?: 'postgres', getenv('PGPASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]);
function sel(string $sql, array $b = []): array { global $pdo; $s = $pdo->prepare($sql); $s->execute($b); return $s->fetchAll(); }
function val(string $sql, array $b = []) { $r = sel($sql, $b)[0] ?? null; return $r ? array_values((array) $r)[0] : null; }
$gagal = 0;
function cek(string $nama, bool $ok, $info = ''): void
{
    global $gagal;
    echo ($ok ? '  OK    ' : '  GAGAL ') . $nama . ($ok ? '' : '  -> ' . json_encode($info, JSON_UNESCAPED_UNICODE)) . "\n";
    $gagal += $ok ? 0 : 1;
}
function u(int $n): string { return sprintf('00000000-0000-4000-8000-%012d', $n); }

// ---------- SISTER palsu
$tmp = sys_get_temp_dir() . '/sister_palsu_' . getmypid();
@mkdir($tmp);
$fileData = "$tmp/data.json";
$fileState = "$tmp/state.json";
$S = '5b4c8012-ede7-4d0c-bfb8-8309fadb8908';   // dosen yang sudah ada di seed
$A = u(1); $B = u(2); $C = u(3); $T = u(4);
$smt = '20261';
$kred = ['username' => 'akun-uji', 'password' => 'rahasia-xyz', 'id_pengguna' => u(99)];
function dasar(): array
{
    global $S, $A, $B, $C, $T, $smt, $kred;
    return [
        'kredensial' => $kred,
        'jab' => [['id' => '48', 'nama' => 'Lektor'], ['id' => '50', 'nama' => 'Asisten Ahli']],
        'sdm' => [
            ['id_sdm' => $S, 'nama_sdm' => 'Dosen Seed', 'jenis_sdm' => 'Dosen', 'nama_status_aktif' => 'Aktif', 'nidn' => '1111', 'nip' => '2222', 'nuptk' => '3333', 'nama_status_pegawai' => 'PNS'],
            ['id_sdm' => $A, 'nama_sdm' => 'Dosen A', 'jenis_sdm' => 'Dosen', 'nama_status_aktif' => 'Aktif', 'nidn' => '0001', 'nip' => '19900101', 'nama_status_pegawai' => 'PNS'],
            ['id_sdm' => $B, 'nama_sdm' => 'Dosen B', 'jenis_sdm' => 'Dosen', 'nama_status_aktif' => 'Aktif', 'nidn' => '0002'],
            ['id_sdm' => $C, 'nama_sdm' => 'Dosen C', 'jenis_sdm' => 'Dosen', 'nama_status_aktif' => 'Non Aktif'],
            ['id_sdm' => $T, 'nama_sdm' => 'Tendik T', 'jenis_sdm' => 'Tenaga Kependidikan', 'nama_status_aktif' => 'Aktif'],
        ],
        'pengajaran' => [
            $A => [$smt => [['id' => u(11), 'mata_kuliah' => 'Basis Data', 'kelas' => 'TI-5A', 'sks' => 3], ['id' => u(12), 'mata_kuliah' => 'Jaringan', 'kelas' => 'TI-5A-CIT', 'sks' => 2]]],
            $B => [$smt => [['id' => u(13), 'mata_kuliah' => 'Algoritma', 'kelas' => 'TI-1B', 'sks' => 4]]],
        ],
        'detail' => [
            u(11) => ['id_kelas' => u(21), 'kode_mata_kuliah' => 'TI501', 'nama_program_studi' => 'Teknik Informatika', 'jenjang_pendidikan' => 'D4', 'sks_mata_kuliah' => 3, 'tatap_muka_rencana' => 16, 'tatap_muka_realisasi' => 14],
            u(12) => ['id_kelas' => u(22), 'kode_mata_kuliah' => 'TI502'],
            u(13) => ['id_kelas' => u(23), 'kode_mata_kuliah' => 'TI101'],
        ],
        'bkd_ajar' => [$A => [$smt => [['id_katgiat' => 1, 'judul_keg' => 'Basis Data', 'nm_kat' => 'Mengajar', 'beban_sks' => 3, 'nilai' => 3, 'stat_kegiatan' => 'Selesai'],
            ['unsur' => 'ajar', 'id_katgiat' => 2, 'judul_keg' => 'Membimbing Skripsi (pembimbing 1) BUDI  SANTOSO', 'nm_kat' => 'Pembimbing 1', 'beban_sks' => 0.5, 'nilai' => 0.5, 'stat_kegiatan' => 'Beban lebih'],
            ['unsur' => 'ajar', 'id_katgiat' => 3, 'judul_keg' => 'Menguji Skripsi CITRA LESTARI', 'nm_kat' => 'Penguji', 'beban_sks' => 0.3, 'nilai' => 0.3, 'stat_kegiatan' => 'Selesai']]]],
        'bkd_akhir' => [
            $A => [['id_smt' => $smt, 'id_jabfung' => '48', 'sks_kinerja_ajar' => 5, 'sks_lebih_ajar' => 0, 'id_reg_ptk' => u(31), 'stat_kewajiban' => 'Memenuhi'], ['id_smt' => '20252', 'id_jabfung' => '50']],
            $B => [['id_smt' => $smt, 'id_jabfung' => '99', 'sks_kinerja_ajar' => 4]],
        ],
        'bimbingan' => [
            $A => [$smt => [['id' => u(41), 'judul' => 'Skripsi X', 'jenis_bimbingan' => 'Skripsi', 'nm_kat' => 'Pembimbing 1', 'program_studi' => 'TI'], ['id' => u(42), 'judul' => 'Disertasi Y', 'jenis_bimbingan' => 'Disertasi', 'nm_kat' => 'Promotor']]],
            $B => [$smt => [['id' => u(43), 'judul' => 'Skripsi Z', 'jenis_bimbingan' => 'Skripsi', 'nm_kat' => 'Pembimbing 2']]],
        ],
        'pengujian' => [
            $A => [$smt => [['id' => u(51), 'judul' => 'Sidang X', 'jenis_pengujian' => 'Skripsi']]],
            $B => [$smt => [['id' => u(51), 'judul' => 'Sidang X', 'jenis_pengujian' => 'Skripsi']]],
        ],
        'pengujian_detail' => [u(51) => ['dosen' => [['id_sdm' => $A, 'kategori_kegiatan' => 'Penguji 1'], ['id_sdm' => $B, 'kategori_kegiatan' => 'Penguji 2']], 'mahasiswa' => [['nama' => 'Citra Lestari']]]],
        'bimbingan_detail' => [u(41) => ['mahasiswa' => [['nama' => 'Budi Santoso']]]],
        'tugas' => [$A => [['id' => u(61), 'jenis_tugas' => 'Kepala Unit Kerja', 'unit_kerja' => 'Teknik Informatika dan Komputer', 'id_kategori_kegiatan' => 111006, 'tanggal_mulai_tugas' => '2026-01-02', 'tanggal_selesai_tugas' => '2028-12-31']]],
        'tunjang' => [$A => [$smt => [['id_katgiat' => '9', 'judul_keg' => 'Seminar Proposal Mahasiswa X', 'nm_kat' => 'Penguji', 'stat_kegiatan' => 'Beban lebih'], ['id_katgiat' => '8', 'judul_keg' => 'Rapat Jurusan', 'nm_kat' => 'Lain']]]],
    ];
}
function tulisData(array $d): void { global $fileData; file_put_contents($fileData, json_encode($d)); }
function state(array $s): void { global $fileState; file_put_contents($fileState, json_encode($s)); }
function logPanggilan(): array { global $fileState; return array_map(fn ($l) => json_decode($l, true), file($fileState . '.log', FILE_IGNORE_NEW_LINES) ?: []); }
function bersihLog(): void { global $fileState; @unlink($fileState . '.log'); }
tulisData(dasar());
state([]);
$proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:8099', __DIR__ . '/sister_palsu.php'], [0 => ['pipe', 'r'], 1 => ['file', "$tmp/server.out", 'w'], 2 => ['file', "$tmp/server.err", 'w']], $pipes,
    null, ['SISTER_PALSU_DATA' => $fileData, 'SISTER_PALSU_STATE' => $fileState] + getenv());
register_shutdown_function(function () use ($proc, $tmp) { proc_terminate($proc); foreach (glob("$tmp/*") as $f) { @unlink($f); } @rmdir($tmp); });
for ($i = 0; $i < 50; $i++) { if (@fsockopen('127.0.0.1', 8099)) { break; } usleep(100000); }

function cfg(array $tambah = []): array
{
    global $kred;
    return $tambah + ['base_url' => 'http://127.0.0.1:8099', 'username' => $kred['username'], 'password' => $kred['password'], 'id_pengguna' => $kred['id_pengguna'],
        'timeout' => 5, 'ulang' => 2, 'jeda_ulang' => 0.01, 'jeda_ms' => 0];
}
/** Menjalankan satu sinkronisasi dan mengembalikan [baris sinkronisasi, endpoint[]]. */
function jalan(array $cfgTambah = [], array $opsi = []): array
{
    global $pdo, $smt;
    $id = sprintf('%08x-0000-4000-8000-%012x', random_int(1, 0x7fffffff), random_int(1, 0x7fffffffffff));
    $pdo->prepare(Kueri::SINKRON_BARU)->execute(['id' => $id, 'smt' => $smt, 'oleh' => null, 'sim' => 'false', 'mode' => 'sister']);
    (new SinkronSister($pdo, new SisterKlien(cfg($cfgTambah)), $opsi))->jalankan($id, $smt);
    $s = sel('SELECT * FROM sinkronisasi WHERE id_sinkron = :i', ['i' => $id])[0];
    $e = sel(Kueri::SINKRON_EP, ['id' => $id]);
    return [$s, $e];
}
function hitung(): array
{
    return ['dosen' => (int) val('SELECT count(*) FROM dosen'), 'ajar' => (int) val('SELECT count(*) FROM pengajaran'), 'bkd' => (int) val('SELECT count(*) FROM bkd_pengajaran'),
        'np' => (int) val('SELECT count(*) FROM kegiatan_nonpengajaran'), 'akhir' => (int) val('SELECT count(*) FROM bkd_akhir')];
}
function np(string $d): array { global $smt; return sel("SELECT jenis, kategori, honor_bruto, sumber, stat_bkd FROM kegiatan_nonpengajaran WHERE id_dosen = :d AND id_smt = :s AND sumber <> 'manual' ORDER BY id_sumber", ['d' => $d, 's' => $smt]); }
function statusEp(array $e): array { return array_map(fn ($x) => $x->status, $e); }

$jurTes = val('SELECT id_jurusan FROM jurusan LIMIT 1');
$pdo->prepare("INSERT INTO program_studi (nama_prodi, jenjang, id_jurusan) VALUES ('Teknik Informatika', 'D4', :j)")->execute(['j' => $jurTes]);
$awal = hitung();
$npSeed = (int) val("SELECT count(*) FROM kegiatan_nonpengajaran WHERE id_dosen = :d", ['d' => $S]);
$golSeed = val('SELECT golongan FROM dosen WHERE id_dosen = :d', ['d' => $S]);
$jurSeed = val('SELECT id_jurusan FROM dosen WHERE id_dosen = :d', ['d' => $S]);

echo "Kegagalan: tidak ada data yang berubah\n";
[$s, $e] = jalan(['password' => 'salah-total']);
cek('login salah: gagal, 11 endpoint dilewati', $s->status === 'gagal' && statusEp($e) === array_fill(0, 11, 'dilewati'), [$s->status, statusEp($e)]);
cek('login salah: pesan tidak memuat kata sandi', ! str_contains((string) $s->pesan_gagal, 'salah-total') && ! str_contains((string) $s->pesan_gagal, 'rahasia-xyz') && str_contains((string) $s->pesan_gagal, 'Login'), $s->pesan_gagal);
cek('login salah: tahap 1', (int) $s->tahap === 1, $s->tahap);
[$s, $e] = jalan(['base_url' => '']);
cek('SISTER_BASE_URL kosong: pesan menyebut berkas .env', $s->status === 'gagal' && str_contains((string) $s->pesan_gagal, 'SISTER_BASE_URL'), $s->pesan_gagal);

state(['gagal' => ['/bkd/ajar' => 504]]);
[$s, $e] = jalan();
cek('504 di endpoint 5: gagal, tahap 2', $s->status === 'gagal' && (int) $s->tahap === 2, [$s->status, $s->tahap]);
cek('504: endpoint 1-4 berhasil, 5 gagal, 6-11 dilewati', statusEp($e) === ['berhasil', 'berhasil', 'berhasil', 'berhasil', 'gagal', 'dilewati', 'dilewati', 'dilewati', 'dilewati', 'dilewati', 'dilewati'], statusEp($e));
cek('504: pesan menyebut endpoint 5 dan "Tidak ada data yang tersimpan"', str_contains((string) $s->pesan_gagal, 'endpoint 5') && str_contains((string) $s->pesan_gagal, 'Tidak ada data yang tersimpan'), $s->pesan_gagal);
cek('504: database tidak berubah', hitung() === $awal, [hitung(), $awal]);
$kem = SinkronSister::kemajuan($s->status, (int) $s->tahap, 4, 11);
cek('504: kemajuan() = selesai, gagal, menunggu, menunggu', $kem['langkah'] === ['selesai', 'gagal', 'menunggu', 'menunggu'], $kem);
state([]);

$bad = dasar();
$bad['pengajaran'][$A][$smt][0]['id'] = 'bukan-uuid';
tulisData($bad);
[$s, $e] = jalan();
cek('gagal simpan (id bukan UUID): gagal di tahap 4', $s->status === 'gagal' && (int) $s->tahap === 4, [$s->status, $s->tahap, $s->pesan_gagal]);
cek('gagal simpan: semua endpoint sempat berhasil diambil, tak ada yang berubah di database', hitung() === $awal && count(array_filter(statusEp($e), fn ($x) => $x === 'berhasil')) === 11, [hitung(), statusEp($e)]);
cek('gagal simpan: dosen A tidak masuk (transaksi di-rollback)', (int) val('SELECT count(*) FROM dosen WHERE id_dosen = :d', ['d' => $A]) === 0);
tulisData(dasar());

echo "Sinkronisasi berhasil\n";
bersihLog();
[$s, $e] = jalan();
cek('status berhasil, tahap 4, durasi terisi', $s->status === 'berhasil' && (int) $s->tahap === 4 && $s->durasi_detik >= 1, [$s->status, $s->tahap, $s->pesan_gagal]);
cek('11 endpoint tercatat berhasil', statusEp($e) === array_fill(0, 11, 'berhasil'), statusEp($e));
$rek = array_column(array_map(fn ($x) => (array) $x, $e), 'rekord', 'no');
cek('jumlah rekord: jab 2, dosen 3 (aktif+Dosen), pengajaran 3, detail 3, uji detail 1 (dibagi dua dosen)', $rek[1] == 2 && $rek[2] == 3 && $rek[3] == 3 && $rek[4] == 3 && $rek[9] == 1, $rek);
cek('dosen baru A dan B masuk; C (non aktif) dan T (tendik) tidak', (int) val('SELECT count(*) FROM dosen WHERE id_dosen IN (:a, :b)', ['a' => $A, 'b' => $B]) === 2
    && (int) val('SELECT count(*) FROM dosen WHERE id_dosen IN (:c, :t)', ['c' => $C, 't' => $T]) === 0);
cek('golongan dan id_jurusan dosen seed tidak tertimpa', val('SELECT golongan FROM dosen WHERE id_dosen = :d', ['d' => $S]) === $golSeed && val('SELECT id_jurusan FROM dosen WHERE id_dosen = :d', ['d' => $S]) === $jurSeed && $golSeed !== null, [$golSeed, $jurSeed]);
cek('jabatan fungsional A = 48 (BKD akhir terbaru), B = NULL (id 99 tidak dikenal)', val('SELECT id_jabfung FROM dosen WHERE id_dosen = :d', ['d' => $A]) === '48' && val('SELECT id_jabfung FROM dosen WHERE id_dosen = :d', ['d' => $B]) === null);
cek('pengajaran: 3 baris baru; kelas -CIT = Kerja Sama, lainnya Reguler',
    (int) val('SELECT count(*) FROM pengajaran WHERE id_dosen IN (:a, :b)', ['a' => $A, 'b' => $B]) === 3
    && val('SELECT jenis_kelas FROM pengajaran WHERE id_pengajaran = :i', ['i' => u(12)]) === 'Kerja Sama' && val('SELECT jenis_kelas FROM pengajaran WHERE id_pengajaran = :i', ['i' => u(11)]) === 'Reguler');
cek('pengajaran: detail terbawa (prodi, jenjang, tatap muka)', val('SELECT program_studi FROM pengajaran WHERE id_pengajaran = :i', ['i' => u(11)]) !== null
    && val('SELECT tatap_muka_realisasi FROM pengajaran WHERE id_pengajaran = :i', ['i' => u(11)]) == 14);
cek('jurusan A terisi dari tabel program_studi (prodi + jenjang); B (prodi tidak dikenal) tetap kosong', val('SELECT id_jurusan FROM dosen WHERE id_dosen = :d', ['d' => $A]) === $jurTes && val('SELECT id_jurusan FROM dosen WHERE id_dosen = :d', ['d' => $B]) === null);
cek('tugas tambahan A tersimpan; B tidak punya', (int) val('SELECT count(*) FROM tugas_tambahan_dosen WHERE id_dosen = :d', ['d' => $A]) === 1 && (int) val('SELECT count(*) FROM tugas_tambahan_dosen WHERE id_dosen = :d', ['d' => $B]) === 0);
cek('keterangan endpoint 11 menyebut jenis tugas yang belum dipetakan', str_contains((string) ($e[10]->keterangan ?? ''), 'Kepala Unit Kerja'), $e[10] ?? null);
cek('keterangan endpoint 2 merinci pegawai SISTER (jenis dan status)', str_contains((string) ($e[1]->keterangan ?? ''), '5 pegawai di SISTER') && str_contains((string) $e[1]->keterangan, 'Dosen Aktif 3') && str_contains((string) $e[1]->keterangan, 'Tenaga Kependidikan Aktif 1'), $e[1]->keterangan ?? null);
cek('BKD pengajaran A tersimpan (3 baris)', (int) val('SELECT count(*) FROM bkd_pengajaran WHERE id_dosen = :d AND id_smt = :s', ['d' => $A, 's' => $smt]) === 3);
cek('BKD akhir hanya untuk periode yang disinkronkan', (int) val('SELECT count(*) FROM bkd_akhir WHERE id_dosen = :d', ['d' => $A]) === 1);
$npA = np($A); $npB = np($B);
cek('non-pengajaran A: 3 baris (bimbingan, pengujian, seminar proposal); Disertasi dilewati', count($npA) === 3 && ! in_array('Disertasi', array_column($npA, 'jenis'), true), $npA);
cek('non-pengajaran A: jenis Skripsi, Skripsi, Seminar Proposal', array_count_values(array_column($npA, 'jenis')) === ['Skripsi' => 2, 'Seminar Proposal' => 1], $npA);
cek('kategori penguji diambil dari detail pengujian per dosen (A=Penguji 1, B=Penguji 2)', in_array('Penguji 1', array_column($npA, 'kategori'), true) && in_array('Penguji 2', array_column($npB, 'kategori'), true), [$npA, $npB]);
cek('non-pengajaran B: 2 baris', count($npB) === 2, $npB);
$st = []; foreach ($npA as $r) { $st[$r->jenis . '|' . $r->kategori] = $r->stat_bkd; }
cek('status BKD: seminar proposal langsung dari /bkd/tunjang = Beban lebih', ($st['Seminar Proposal|Penguji'] ?? null) === 'Beban lebih', $st);
cek('status BKD: bimbingan dicocokkan lewat nama mahasiswa (spasi ganda, huruf besar/kecil diabaikan) = Beban lebih', ($st['Skripsi|Pembimbing 1'] ?? null) === 'Beban lebih', $st);
cek('status BKD: pengujian cocok lewat nama mahasiswa = Selesai', ($st['Skripsi|Penguji 1'] ?? null) === 'Selesai', $st);
cek('status BKD: tidak ada baris BKD yang cocok -> NULL (tidak ditebak)', array_filter(array_column(array_map(fn ($r) => (array) $r, $npB), 'stat_bkd')) === [], $npB);
cek('statusBkd: dua status berbeda yang cocok -> NULL', SinkronSister::statusBkd([['judul_keg' => 'Membimbing A', 'nm_kat' => 'K', 'stat_kegiatan' => 'Selesai'], ['judul_keg' => 'Membimbing A', 'nm_kat' => 'K', 'stat_kegiatan' => 'Beban lebih']], 'K', ['a'], null) === null);
cek('honor_bruto NULL (tarif SBM belum dihitung), tidak 0', count(array_filter(array_merge($npA, $npB), fn ($r) => $r->honor_bruto !== null)) === 0);
cek('keterangan endpoint 7 menyebut jenis yang dilewati', str_contains((string) ($e[6]->keterangan ?? ''), 'Disertasi'), $e[6] ?? null);
cek('kegiatan manual dosen seed tidak tersentuh', (int) val("SELECT count(*) FROM kegiatan_nonpengajaran WHERE id_dosen = :d", ['d' => $S]) === $npSeed);
cek('atribut endpoint tercatat dari rekord pertama', (int) val("SELECT count(*) FROM sinkronisasi_atribut WHERE id_sinkron = :i AND no = 3", ['i' => $s->id_sinkron]) >= 3);
cek('perhitungan honor tidak dijalankan oleh sinkronisasi', (int) val("SELECT count(*) FROM perhitungan WHERE id_dosen IN (:a, :b)", ['a' => $A, 'b' => $B]) === 0);
cek('kata sandi tidak ada di tabel sinkronisasi', (int) val("SELECT count(*) FROM sinkronisasi WHERE pesan_gagal LIKE '%rahasia-xyz%'") === 0);
$setelah = hitung();

echo "Sinkronisasi ulang\n";
[$s, $e] = jalan();
cek('ulang: berhasil dan jumlah baris tetap sama (idempoten)', $s->status === 'berhasil' && hitung() === $setelah, [hitung(), $setelah]);
$pdo->prepare("INSERT INTO verifikasi_nonpengajaran (id_dosen, id_smt, status_verifikasi) VALUES (:d, :s, 'terverifikasi')")->execute(['d' => $A, 's' => $smt]);
[$s, $e] = jalan();
cek('data tidak berubah: verifikasi tetap terverifikasi', val('SELECT status_verifikasi FROM verifikasi_nonpengajaran WHERE id_dosen = :d AND id_smt = :s', ['d' => $A, 's' => $smt]) === 'terverifikasi');
$ubah = dasar();
$ubah['bimbingan'][$A][$smt][] = ['id' => u(44), 'judul' => 'Skripsi Baru', 'jenis_bimbingan' => 'Skripsi', 'nm_kat' => 'Pembimbing 1'];
tulisData($ubah);
[$s, $e] = jalan();
cek('data berubah: verifikasi dikembalikan ke menunggu (baris dihapus) dan 4 kegiatan', val('SELECT count(*) FROM verifikasi_nonpengajaran WHERE id_dosen = :d AND id_smt = :s', ['d' => $A, 's' => $smt]) == 0 && count(np($A)) === 4, [count(np($A))]);
cek('keterangan endpoint 7 menyebut verifikasi dikembalikan', str_contains((string) ($e[6]->keterangan ?? ''), 'verifikasi'), $e[6] ?? null);
$kurang = dasar();
$kurang['bimbingan'][$A][$smt] = [];
tulisData($kurang);
jalan();
cek('kegiatan yang hilang di SISTER ikut hilang (A tinggal 2)', count(np($A)) === 2, count(np($A)));
tulisData(dasar());

echo "Ketahanan klien\n";
state(['rotasi' => true]); bersihLog();
[$s] = jalan();
$login = count(array_filter(logPanggilan(), fn ($l) => $l[1] === '/authorize'));
cek('token dibatalkan di tengah jalan: login ulang otomatis, hasil berhasil', $s->status === 'berhasil' && $login >= 2, [$s->status, $login, $s->pesan_gagal]);
state(['gagal_sekali' => ['/bkd/ajar' => 429]]); bersihLog();
[$s] = jalan();
cek('429 sekali: dicoba ulang, berhasil', $s->status === 'berhasil', $s->pesan_gagal);
state(['tidak_ada' => ['/pengajaran/' . u(12)]]);
[$s, $e] = jalan();
cek('404 pada satu detail: dilewati dan dicatat, sinkronisasi tetap berhasil', $s->status === 'berhasil' && str_contains((string) $e[3]->keterangan, '1 item dilewati') && $e[3]->rekord == 2, [$s->status, $e[3]]);
state([]);
bersihLog();
[$s, $e] = jalan([], ['id_sdm' => [$A]]);
$ids = array_unique(array_map(fn ($l) => $l[2]['id_sdm'] ?? null, array_filter(logPanggilan(), fn ($l) => isset($l[2]['id_sdm']))));
cek('opsi id_sdm: hanya dosen A yang diminta ke SISTER', $s->status === 'berhasil' && array_values($ids) === [$A], $ids);
bersihLog();
[$s, $e] = jalan([], ['maks' => 1]);
cek('opsi maks=1: rekord endpoint 2 = 1', $e[1]->rekord == 1, $e[1]);
state(['gagal' => ['/referensi/sdm' => 503]]);
$t0 = microtime(true);
[$s, $e] = jalan();
cek('503 terus-menerus: berhenti setelah percobaan ulang, endpoint 2 gagal', $s->status === 'gagal' && $e[1]->status === 'gagal' && (microtime(true) - $t0) < 5, [$s->status, statusEp($e)]);
state([]);

[$s, $e] = jalan([], ['semua_status' => true]);
cek('semua_status: dosen non-aktif C ikut masuk, tendik T tetap tidak', $s->status === 'berhasil' && $e[1]->rekord == 4
    && (int) val('SELECT count(*) FROM dosen WHERE id_dosen = :c', ['c' => $C]) === 1 && (int) val('SELECT count(*) FROM dosen WHERE id_dosen = :t', ['t' => $T]) === 0, [$s->status, $e[1]->rekord]);
cek('semua_status: status_aktif C tersimpan apa adanya (Non Aktif)', val('SELECT status_aktif FROM dosen WHERE id_dosen = :c', ['c' => $C]) === 'Non Aktif');

echo "Unit\n";
cek('daftarSemester 20251..20262 = 4 semester berurutan', SinkronSister::daftarSemester('20251', '20262') === ['20251', '20252', '20261', '20262']);
cek('daftarSemester 20252..20261 = 2 semester (tidak melebar)', SinkronSister::daftarSemester('20252', '20261') === ['20252', '20261']);
cek('daftarSemester satu semester', SinkronSister::daftarSemester('20141', '20141') === ['20141']);
cek('daftarSemester menolak format salah dan urutan terbalik', (function () {
    $salah = 0;
    foreach ([['2025', '20261'], ['20253', '20261'], ['20261', '20252']] as [$d, $s]) {
        try { SinkronSister::daftarSemester($d, $s); } catch (InvalidArgumentException $x) { $salah++; }
    }
    return $salah === 3;
})());
cek('labelPeriode: 20252 = Genap 2025/2026, 20261 = Ganjil 2026/2027', SinkronSister::labelPeriode('20252')['label'] === 'Genap 2025/2026' && SinkronSister::labelPeriode('20261')['label'] === 'Ganjil 2026/2027' && SinkronSister::labelPeriode('20141')['tahun_ajaran'] === '2014/2015');
cek('jenisKelas: akhiran / awalan / mengandung / reguler', (function () {
    $p = fn ($t, $x) => (object) ['tipe' => $t, 'pola' => $x];
    return SinkronSister::jenisKelas('TI-5A-cit', [$p('akhiran', 'CIT')]) === 'Kerja Sama'
        && SinkronSister::jenisKelas('CIT-5A', [$p('awalan', 'CIT')]) === 'Kerja Sama'
        && SinkronSister::jenisKelas('TI-CIT-5A', [$p('mengandung', 'CIT')]) === 'Kerja Sama'
        && SinkronSister::jenisKelas('TI-5A', [$p('akhiran', 'CIT')]) === 'Reguler'
        && SinkronSister::jenisKelas('TI-5A', []) === 'Reguler';
})());
cek('uuidDari stabil dan berbentuk UUID', SinkronSister::uuidDari('a') === SinkronSister::uuidDari('a') && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', SinkronSister::uuidDari('a')) === 1);
$k = SinkronSister::kemajuan('berjalan', 2, 5, 11);
cek('kemajuan berjalan di tahap 2: [selesai, berjalan, menunggu, menunggu], 37% (4 dari 11 endpoint)', $k['langkah'] === ['selesai', 'berjalan', 'menunggu', 'menunggu'] && $k['persen'] === 37, $k);
cek('kemajuan berhasil = 100% semua selesai', SinkronSister::kemajuan('berhasil', 4, 11, 11) === ['status' => 'berhasil', 'persen' => 100, 'langkah' => ['selesai', 'selesai', 'selesai', 'selesai']]);
$pakai = fn (array $balasan) => new SisterKlien(cfg(['jeda_ulang' => 0.001]), function ($m, $url, $h, $b, $t) use (&$balasan) { return array_shift($balasan) ?? [200, '{}']; });
$kl = $pakai([[200, '{"tanpa":"token"}']]);
try { $kl->login(); cek('login tanpa token di respons: galat', false); } catch (SisterGalat $x) { cek('login tanpa token di respons: galat', str_contains($x->getMessage(), 'token')); }
$kl = $pakai([[200, '{"token":"t"}'], [200, 'bukan json']]);
try { $kl->get('/x'); cek('respons bukan JSON: galat', false); } catch (SisterGalat $x) { cek('respons bukan JSON: galat', str_contains($x->getMessage(), 'JSON')); }
$kl = $pakai([[200, '{"token":"t"}'], [429, ''], [429, ''], [200, '[1,2]']]);
cek('429 dua kali lalu 200: hasil dikembalikan', $kl->get('/x') === [1, 2]);
$kl = $pakai([[200, '{"token":"t"}'], [404, '']]);
cek('404: null dan dihitung dilewati', $kl->get('/y') === null && $kl->dilewati['/y'] === 1);
cek('daftar(): list langsung, kunci data, null', SisterKlien::daftar([1]) === [1] && SisterKlien::daftar(['data' => [2]]) === [2] && SisterKlien::daftar(null) === []);

echo "Tutup sinkronisasi macet\n";
$id = u(777);
$pdo->prepare(Kueri::SINKRON_BARU)->execute(['id' => $id, 'smt' => $smt, 'oleh' => null, 'sim' => 'false', 'mode' => 'sister']);
SinkronSister::tutupMacet($pdo, $id, 'Proses berhenti tiba-tiba.');
$r = sel('SELECT status, pesan_gagal FROM sinkronisasi WHERE id_sinkron = :i', ['i' => $id])[0];
cek('tutupMacet: status gagal, 11 endpoint dilewati', $r->status === 'gagal' && count(sel(Kueri::SINKRON_EP, ['id' => $id])) === 11, $r);
cek('tombol tidak terkunci: tidak ada sinkronisasi berjalan', (int) val("SELECT count(*) FROM sinkronisasi WHERE status = 'berjalan'") === 0);
$pdo->prepare(Kueri::SINKRON_BARU)->execute(['id' => u(778), 'smt' => $smt, 'oleh' => null, 'sim' => 'false', 'mode' => 'sister']);
try { $pdo->prepare(Kueri::SINKRON_BARU)->execute(['id' => u(779), 'smt' => $smt, 'oleh' => null, 'sim' => 'false', 'mode' => 'sister']); cek('dua sinkronisasi berjalan sekaligus ditolak indeks unik', false); }
catch (PDOException $x) { cek('dua sinkronisasi berjalan sekaligus ditolak indeks unik', true); }
SinkronSister::tutupMacet($pdo, u(778), 'x');

echo "Bentuk JSON dengan honor kosong\n";
$d = Bentuk::npDetail(['nama' => 'A', 'jabatan' => null, 'jurusan' => null], null, [(object) ['urutan' => 1, 'judul' => 'j', 'jenis' => 'Skripsi', 'kategori' => 'k', 'program_studi' => null, 'perguruan_tinggi' => null, 'honor_bruto' => null]]);
cek('npDetail: honor null, totalHonor null (bukan 0)', $d['kegiatan'][0]['honor'] === null && $d['totalHonor'] === null, $d);
$d = Bentuk::npDetail(['nama' => 'A', 'jabatan' => null, 'jurusan' => null], null, [(object) ['urutan' => 1, 'judul' => 'j', 'jenis' => 'Skripsi', 'kategori' => 'k', 'program_studi' => null, 'perguruan_tinggi' => null, 'honor_bruto' => 100000], (object) ['urutan' => 2, 'judul' => 'j', 'jenis' => 'Skripsi', 'kategori' => 'k', 'program_studi' => null, 'perguruan_tinggi' => null, 'honor_bruto' => null]]);
cek('npDetail: sebagian null, total = jumlah yang ada', $d['totalHonor'] === 100000, $d);
$daftar = Bentuk::npDaftar(sel('SELECT * FROM v_nonpengajaran_dosen WHERE id_smt = :s', ['s' => $smt]));
cek('npDaftar: dosen hasil sinkronisasi muncul dengan honor null', count(array_filter($daftar, fn ($x) => $x['id'] === $A && $x['honor'] === null && $x['jumlahKegiatan'] === 3)) === 1, array_filter($daftar, fn ($x) => $x['id'] === $A));

echo "\n" . ($gagal === 0 ? 'SEMUA LULUS' : "$gagal GAGAL") . "\n";
exit($gagal === 0 ? 0 : 1);
