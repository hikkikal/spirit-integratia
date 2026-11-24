<?php

namespace App\Console\Commands;

use App\Support\AkademikKlien;
use App\Support\Kueri;
use App\Support\SinkronAkademik;
use App\Support\SinkronSister;
use App\Support\SisterKlien;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menarik data dari SISTER dan menyimpannya (dosen, pengajaran, BKD, non-pengajaran). Sama dengan tombol Sinkronisasi,
 * tetapi bisa dijadwalkan (routes/console.php: Schedule::command('sinkron:sister')->dailyAt('01:00');) atau dicoba untuk sedikit dosen.
 *   php artisan sinkron:sister                       periode terbaru yang belum terkunci
 *   php artisan sinkron:sister 20252 --maks=3         uji coba 3 dosen
 *   php artisan sinkron:sister 20252 --id-sdm=<uuid>  satu dosen tertentu
 *   php artisan sinkron:sister --dari=20141 --sampai=20252 --semua-status --lewati-berhasil
 *       banyak semester satu per satu (periode yang belum ada dibuat otomatis), termasuk dosen non-aktif;
 *       semester yang sudah pernah berhasil dilewati, jadi aman diulang bila terputus.
 */
class SinkronSisterCommand extends Command
{
    protected $signature = 'sinkron:sister {periode? : id semester, mis. 20252} {--id-sdm=* : hanya dosen ini (uji coba)} {--maks=0 : batasi jumlah dosen (uji coba)} {--id-sinkron= : (dipakai tombol Sinkronisasi) lanjutkan baris sinkronisasi yang sudah dibuat}
        {--dari= : id semester awal untuk menarik banyak semester sekaligus, mis. 20141}
        {--sampai= : id semester akhir (bawaan: periode terbaru di database)}
        {--semua-status : sertakan dosen non-aktif (perlu untuk semester lama)}
        {--lewati-berhasil : lewati semester yang sudah pernah berhasil disinkronkan}';
    protected $description = 'Tarik data dari SISTER ke database Integratia';

    public function handle(): int
    {
        if (config('sister.base_url') === '') {
            $this->error('SISTER_BASE_URL belum diisi di .env.');
            return self::FAILURE;
        }
        if ($this->option('dari')) {
            return $this->banyakSemester();
        }
        $smt = $this->argument('periode') ? DB::selectOne(Kueri::PERIODE_SINKRON, ['id' => (string) $this->argument('periode')]) : DB::selectOne(Kueri::PERIODE_SINKRON_AWAL);
        if ($smt === null) {
            $this->error('Periode tidak ditemukan (tambahkan ke tabel periode, atau pakai --dari/--sampai yang membuatnya otomatis), atau semua periode sudah dikunci.');
            return self::FAILURE;
        }
        return $this->satu($smt);
    }

    /** --dari/--sampai: satu semester per putaran; periode yang belum ada dibuat. Satu semester gagal tidak menghentikan yang lain. */
    private function banyakSemester(): int
    {
        try {
            $sampai = $this->option('sampai') ?: (DB::selectOne('SELECT max(id_smt) AS m FROM periode')->m ?? null);
            if ($sampai === null) {
                $this->error('Isi --sampai, karena tabel periode masih kosong.');
                return self::FAILURE;
            }
            $daftar = SinkronSister::daftarSemester((string) $this->option('dari'), (string) $sampai);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->warn(count($daftar) . ' semester akan ditarik satu per satu (' . $daftar[0] . ' sampai ' . end($daftar) . '). Bisa memakan waktu lama; aman dihentikan dan diulang dengan --lewati-berhasil.');
        $gagal = [];
        foreach ($daftar as $idSmt) {
            $smt = DB::selectOne(Kueri::PERIODE_SINKRON, ['id' => $idSmt]);
            if ($smt === null) {
                $l = SinkronSister::labelPeriode($idSmt);
                DB::insert('INSERT INTO periode (id_smt, label, semester, tahun_ajaran) VALUES (?, ?, ?, ?) ON CONFLICT DO NOTHING', [$idSmt, $l['label'], $l['semester'], $l['tahun_ajaran']]);
                $smt = DB::selectOne(Kueri::PERIODE_SINKRON, ['id' => $idSmt]);
            }
            if ($smt !== null && $this->option('lewati-berhasil') && DB::selectOne("SELECT 1 AS ada FROM sinkronisasi WHERE id_smt = ? AND status = 'berhasil' AND mode = 'sister' LIMIT 1", [$idSmt]) !== null) {
                $this->line("Periode {$smt->label} ($idSmt): sudah pernah berhasil, dilewati.");
                continue;
            }
            if ($smt === null || $this->satu($smt) === self::FAILURE) {
                $gagal[] = $idSmt;
            }
        }
        $this->line($gagal ? 'Semester gagal: ' . implode(', ', $gagal) . ' (data semester itu tidak berubah; ulangi dengan --lewati-berhasil).' : 'Semua semester selesai.');
        return $gagal ? self::FAILURE : self::SUCCESS;
    }

    /** Menyinkronkan satu periode (baris dari tabel periode). */
    private function satu(object $smt): int
    {
        if ((bool) $smt->terkunci) {
            $this->error("Periode {$smt->label} sudah dikunci dan tidak bisa disinkronkan.");
            return self::FAILURE;
        }
        $batas = (int) config('sister.batas_menit', 60);
        foreach (DB::select(Kueri::SINKRON_BERJALAN) as $b) {
            if ($b->mode === 'sister' && (float) $b->lewat > ($batas + 2) * 60) {
                SinkronSister::tutupMacet(DB::connection()->getPdo(), $b->id_sinkron, "Proses sinkronisasi terhenti (lebih dari $batas menit tanpa selesai). Tidak ada data yang tersimpan.");
            }
        }
        $id = (string) $this->option('id-sinkron');
        if ($id !== '') {
            // Dipanggil dari tombol: barisnya sudah dibuat oleh controller (dan sudah lolos pengecekan "sedang berjalan").
            $ada = DB::selectOne(Kueri::SINKRON_BY_ID, ['id' => $id]);
            if ($ada === null || $ada->status !== 'berjalan') {
                $this->error('Baris sinkronisasi tidak ditemukan atau sudah selesai.');
                return self::FAILURE;
            }
        } else {
            $id = (string) Str::uuid();
            try {
                DB::insert(Kueri::SINKRON_BARU, ['id' => $id, 'smt' => $smt->id_smt, 'oleh' => null, 'sim' => 'false', 'mode' => 'sister']);
            } catch (UniqueConstraintViolationException $e) {
                $this->error('Sinkronisasi lain sedang berjalan.');
                return self::FAILURE;
            }
        }
        $this->info("Sinkronisasi {$smt->label} ({$smt->id_smt}) dimulai…");
        (new SinkronSister(DB::connection()->getPdo(), new SisterKlien(config('sister')), [
            'id_sdm' => array_filter((array) $this->option('id-sdm')), 'maks' => (int) $this->option('maks'),
            // Banyak semester lama bisa jauh lebih lama dari satu periode: tanpa batas waktu per semester pada mode --dari.
            'batas_detik' => $this->option('dari') ? 0 : $batas * 60, 'semua_status' => (bool) $this->option('semua-status'),
            // Kemajuan ditulis ke layar (bila dijalankan manual) dan ke storage/logs/sinkron.log (juga saat dijalankan tombol, outputnya tidak terlihat).
            'log' => function (string $m) use ($id) {
                $this->line($m);
                @file_put_contents(storage_path('logs/sinkron.log'), date('Y-m-d H:i:s') . ' [' . substr($id, 0, 8) . '] ' . $m . PHP_EOL, FILE_APPEND | LOCK_EX);
            },
        ]))->jalankan($id, $smt->id_smt);

        $r = DB::selectOne(Kueri::SINKRON_BY_ID, ['id' => $id]);
        if ($r->status === 'berhasil') {
            // Sesudah SISTER berhasil: tarik juga data akademik (jadwal, pertemuan, kalender). Kegagalannya tidak mengubah hasil SISTER.
            SinkronAkademikCommand::tarik($this, $id, $smt->id_smt);
            // Terakhir: honor semua dosen dihitung otomatis dari data terbaru (menjadi endpoint 18 di log).
            SinkronAkademikCommand::hitungHonor($this, $id, $smt->id_smt);
        }
        foreach (DB::select(Kueri::SINKRON_EP, ['id' => $id]) as $e) {
            $this->line(sprintf('  %2d. %-36s %-9s %6s rekord%s', $e->no, $e->nama, $e->status, $e->rekord, $e->keterangan ? "  ({$e->keterangan})" : ''));
        }
        if ($r->status === 'berhasil') {
            $n = DB::selectOne("SELECT count(*) FILTER (WHERE sumber <> 'manual') AS dari_sister, count(*) FILTER (WHERE sumber = 'manual') AS manual FROM kegiatan_nonpengajaran WHERE id_smt = ?", [$smt->id_smt]);
            $j = DB::selectOne('SELECT count(*) FILTER (WHERE id_jurusan IS NULL) AS kosong, count(*) AS total FROM dosen');
            $this->line("  Non-pengajaran periode ini: {$n->dari_sister} baris dari SISTER, {$n->manual} manual. Dosen tanpa jurusan: {$j->kosong} dari {$j->total}.");
            $this->info("Selesai dalam {$r->durasi_detik} detik. Honor sudah dihitung otomatis.");
            return self::SUCCESS;
        }
        $this->error($r->pesan_gagal);
        return self::FAILURE;
    }
}
