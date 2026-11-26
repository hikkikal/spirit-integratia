<?php

namespace App\Console\Commands;

use App\Support\AkademikKlien;
use Illuminate\Console\Command;

/**
 * Menjelajahi Open API PNJ (Academia dan AcademiaV2) untuk mencari sumber data realisasi perkuliahan dan pertemuan.
 *   php artisan akademik:jelajah
 * Hasil lengkap disimpan di storage/app/jelajah/ (dokumen OpenAPI jika berhasil diambil). Hanya dari jaringan PNJ.
 */
class JelajahAkademikCommand extends Command
{
    protected $signature = 'akademik:jelajah';
    protected $description = 'Cari endpoint/field realisasi dan pertemuan di Academia dan AcademiaV2';

    private const KATA = ['realisasi', 'presensi', 'kehadiran', 'jurnal', 'pertemuan', 'perkuliahan', 'berita-acara', 'bap', 'approval', 'status', 'tatap'];
    private const DOKUMEN = ['/public/openapi.json', '/public/openapi', '/public/docs', '/public/swagger.json', '/public/v3/api-docs', '/openapi.json', '/docs', '/swagger.json'];
    private const KANDIDAT = ['kelas-kuliah', 'rencana-jadwal', 'realisasi', 'realisasi-jadwal', 'realisasi-perkuliahan', 'realisasi-pertemuan', 'presensi',
        'presensi-dosen', 'kehadiran-dosen', 'jurnal', 'jurnal-perkuliahan', 'perkuliahan', 'pertemuan', 'jadwal', 'jadwal-kuliah', 'berita-acara', 'distribusi-sks-dosen', 'kelas-kuliah-dosen'];

    public function handle(): int
    {
        $k = new AkademikKlien(config('academia'));
        $dir = storage_path('app/jelajah');
        @mkdir($dir, 0775, true);
        $this->info('1) Mencari dokumen OpenAPI');
        foreach (['academia', 'academiav2'] as $svc) {
            foreach (self::DOKUMEN as $p) {
                try { $r = $k->get("/$svc$p"); } catch (\Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
                $ada = $r['status'] === 200 && is_array($r['json']);
                $this->line(sprintf('  %-45s HTTP %d %s', "/$svc$p", $r['status'], $ada ? 'JSON ✔' : ''));
                if (! $ada) { continue; }
                file_put_contents("$dir/$svc.json", json_encode($r['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $paths = array_keys($r['json']['paths'] ?? []);
                $this->line("  → $svc: " . count($paths) . ' path. Disimpan di storage/app/jelajah/' . "$svc.json");
                foreach ($paths as $pth) {
                    $cocok = array_filter(self::KATA, fn ($w) => stripos($pth, $w) !== false);
                    $this->line(($cocok ? '    ★ ' : '      ') . $pth);
                }
                $teks = json_encode($r['json']['components']['schemas'] ?? $r['json']);
                foreach (self::KATA as $w) {
                    if (preg_match_all('/"([A-Za-z_]*' . $w . '[A-Za-z_]*)"/i', $teks, $m)) {
                        $this->line("    field/nama berisi '$w': " . implode(', ', array_slice(array_unique($m[1]), 0, 15)));
                    }
                }
                break;
            }
        }
        $this->info('2) Menebak endpoint (200/400/422 = ada, 404 = tidak ada)');
        foreach (['academia', 'academiav2'] as $svc) {
            foreach (self::KANDIDAT as $c) {
                $r = $k->get("/$svc/public/$c", ['per_page' => 1, 'limit' => 1]);
                $st = $r['status'];
                $ket = '';
                if ($st === 200 && is_array($r['json'])) {
                    $baris = $r['json']['data'] ?? $r['json'];
                    $baris = is_array($baris) && isset($baris[0]) ? $baris[0] : $baris;
                    $ket = is_array($baris) ? 'field: ' . implode(', ', array_keys($baris)) : '';
                }
                if ($st !== 404) { $this->line(sprintf('  %-45s HTTP %d %s', "/$svc/public/$c", $st, $ket)); }
            }
        }
        $this->info('Selesai. Kirim seluruh keluaran ini (dan file storage/app/jelajah/*.json bila ada).');
        return self::SUCCESS;
    }
}
