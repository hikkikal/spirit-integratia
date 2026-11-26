<?php

namespace App\Console\Commands;

use App\Support\Kueri;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung ulang honor (pengajaran dan non-pengajaran) semua dosen satu periode dari data yang sudah ada di database,
 * tanpa menarik data dari SISTER atau Academia. Berguna sesudah migrasi data (mis. isi ulang status BKD).
 *   php artisan honor:hitung            periode terbaru yang belum terkunci
 *   php artisan honor:hitung 20251
 * Honor yang sudah terverifikasi atau disesuaikan, dan periode terkunci, tidak disentuh.
 */
class HitungHonorCommand extends Command
{
    protected $signature = 'honor:hitung {periode? : id semester, mis. 20251}';
    protected $description = 'Hitung ulang honor semua dosen satu periode dari data yang sudah ada';

    public function handle(): int
    {
        $smt = $this->argument('periode') ? DB::selectOne(Kueri::PERIODE_SINKRON, ['id' => (string) $this->argument('periode')]) : DB::selectOne(Kueri::PERIODE_SINKRON_AWAL);
        if ($smt === null) {
            $this->error('Periode tidak ditemukan, atau semua periode sudah dikunci.');
            return self::FAILURE;
        }
        SinkronAkademikCommand::hitungHonor($this, null, $smt->id_smt);
        return self::SUCCESS;
    }
}
