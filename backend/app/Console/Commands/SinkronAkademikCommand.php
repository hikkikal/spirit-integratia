<?php

namespace App\Console\Commands;

use App\Support\AkademikKlien;
use App\Support\HitungHonor;
use App\Support\HitungNonPengajaran;
use App\Support\Kueri;
use App\Support\SinkronAkademik;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menarik data akademik (Personalia + Academia + AcademiaV2) untuk semua dosen satu periode ke tabel academia_hasil.
 * Otomatis dijalankan sesudah sinkronisasi SISTER berhasil (tombol Sinkronisasi Data); perintah ini untuk mengulanginya saja:
 *   php artisan sinkron:akademik            periode terbaru yang belum terkunci
 *   php artisan sinkron:akademik 20252
 * Kredensial di .env: ACADEMIA_TOKEN, atau ACADEMIA_CLIENT_CREDENTIALS=true + ACADEMIA_CLIENT_ID/SECRET (bawaan: OIDC_CLIENT_ID/SECRET).
 */
class SinkronAkademikCommand extends Command
{
    protected $signature = 'sinkron:akademik {periode? : id semester, mis. 20252}';
    protected $description = 'Tarik data akademik (jadwal, pertemuan, kalender) semua dosen satu periode';

    public function handle(): int
    {
        $smt = $this->argument('periode') ? DB::selectOne(Kueri::PERIODE_SINKRON, ['id' => (string) $this->argument('periode')]) : DB::selectOne(Kueri::PERIODE_SINKRON_AWAL);
        if ($smt === null) {
            $this->error('Periode tidak ditemukan, atau semua periode sudah dikunci.');
            return self::FAILURE;
        }
        return self::tarik($this, null, $smt->id_smt) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Dipakai juga oleh SinkronSisterCommand. $idSinkron: baris sinkronisasi tempat endpoint 13-17 dicatat (null = hanya tampil di layar).
     * Tidak pernah melempar.
     */
    public static function tarik(Command $cmd, ?string $idSinkron, string $smt): bool
    {
        $cfg = config('academia');
        try {
            if (! AkademikKlien::siap($cfg)) {
                $alasan = 'Dilewati: kredensial data akademik belum diisi di .env (ACADEMIA_TOKEN, atau ACADEMIA_CLIENT_CREDENTIALS=true).';
                $cmd->warn("Data akademik: $alasan");
                if ($idSinkron !== null) {
                    foreach (SinkronAkademik::ENDPOINT as $no => [$nama, $path]) {
                        DB::insert('INSERT INTO sinkronisasi_endpoint (id_sinkron, no, nama, path, status, detik, rekord, keterangan) VALUES (?, ?, ?, ?, ?, NULL, 0, ?)
                                    ON CONFLICT (id_sinkron, no) DO NOTHING', [$idSinkron, $no, $nama, $path, 'dilewati', $alasan]);
                    }
                }
                return false;
            }
            $klien = new AkademikKlien($cfg);
            $log = function (string $m) use ($cmd, $idSinkron) {
                $cmd->line($m);
                @file_put_contents(storage_path('logs/sinkron.log'), date('Y-m-d H:i:s') . ' [' . substr((string) $idSinkron, 0, 8) . '] ' . $m . PHP_EOL, FILE_APPEND | LOCK_EX);
            };
            $r = (new SinkronAkademik(DB::connection()->getPdo(), fn (string $p, array $q) => $klien->get($p, $q), ['log' => $log]))->jalankan($smt, $idSinkron);
            $cmd->{$r['ok'] ? 'info' : 'error'}($r['ok'] ? "Data akademik: {$r['tersimpan']} dari {$r['dosen']} dosen tersimpan ({$klien->panggilan} request)." : 'Data akademik gagal: ' . $r['pesan']);
            return $r['ok'];
        } catch (\Throwable $e) {
            $cmd->error('Data akademik gagal: ' . mb_substr($e->getMessage(), 0, 250));
            return false;
        }
    }

    /** Nomor endpoint untuk langkah terakhir sinkronisasi: menghitung honor semua dosen (dicatat di log seperti endpoint lain). */
    public const NO_HITUNG = 18;

    /**
     * Langkah terakhir tombol Sinkronisasi Data: honor semua dosen dihitung dari data terbaru. Perhitungan yang sudah terverifikasi
     * dan periode terkunci tidak disentuh (aturan HitungHonor). Tidak pernah melempar; hasilnya jadi baris endpoint 18.
     */
    public static function hitungHonor(Command $cmd, string $idSinkron, string $smt): void
    {
        $mulai = microtime(true);
        $status = 'berhasil';
        $rekord = 0;
        try {
            $baca = fn (string $sql, array $b) => DB::select($sql, $b);
            $tulis = function (string $sql, array $b) { DB::statement($sql, $b); };
            [$h, $np] = DB::transaction(fn () => [
                HitungHonor::hitungPeriode($baca, $tulis, $smt),
                HitungNonPengajaran::hitungPeriode($baca, $tulis, $smt),
            ]);
            $rekord = (int) $h['dihitung'];
            $ket = "{$h['dihitung']} dosen dihitung, " . array_sum($h['dilewati']) . " dilewati (disesuaikan/terverifikasi/terkunci), non-pengajaran {$np['dihitung']} kegiatan";
            if (count($h['tanpa_tarif']) > 0) {
                $ket .= '; tarif belum ada: ' . count($h['tanpa_tarif']);
            }
            $cmd->info("Honor: $ket.");
        } catch (\Throwable $e) {
            $status = 'gagal';
            $ket = 'Honor tidak dihitung: ' . mb_substr($e->getMessage(), 0, 250);
            $cmd->error($ket);
        }
        try {
            DB::insert('INSERT INTO sinkronisasi_endpoint (id_sinkron, no, nama, path, status, detik, rekord, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ON CONFLICT (id_sinkron, no) DO NOTHING',
                [$idSinkron, self::NO_HITUNG, 'Hitung honor otomatis', '(internal)', $status, round(microtime(true) - $mulai, 1), $rekord, $ket]);
        } catch (\Throwable $e) {
            $cmd->error('Catatan hitung honor gagal disimpan: ' . mb_substr($e->getMessage(), 0, 200));
        }
    }
}
