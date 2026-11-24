<?php

namespace App\Console\Commands;

use App\Support\PolaKelas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Membaca nama kelas dari data SISTER (tabel pengajaran) dan mengusulkan pola kelas Kerja Sama.
 *   php artisan pola:kelas             hanya menampilkan usulan
 *   php artisan pola:kelas --terapkan  menyimpan usulan yang kodenya sama persis dengan nama mitra, lalu menandai kelas lama sebagai Kerja Sama
 * Sesudah --terapkan, hitung ulang periode yang terdampak (kelas berubah jenis).
 */
class PolaKelasCommand extends Command
{
    protected $signature = 'pola:kelas {--terapkan : simpan usulan yang kodenya = nama mitra}';
    protected $description = 'Usulkan pola kelas Kerja Sama dari nama kelas di data SISTER';

    private const MITRA = "SELECT DISTINCT mitra FROM master_tarif WHERE mitra IS NOT NULL ORDER BY mitra";
    private const TANDAI = "UPDATE pengajaran pg SET jenis_kelas = 'Kerja Sama' WHERE jenis_kelas = 'Reguler' AND EXISTS (
        SELECT 1 FROM pola_kelas_kerja_sama pk WHERE CASE pk.tipe
            WHEN 'akhiran' THEN upper(pg.kelas) LIKE '%' || upper(pk.pola)
            WHEN 'awalan' THEN upper(pg.kelas) LIKE upper(pk.pola) || '%'
            ELSE upper(pg.kelas) LIKE '%' || upper(pk.pola) || '%' END)";

    public function handle(): int
    {
        $kelas = array_column(DB::select('SELECT DISTINCT kelas FROM pengajaran ORDER BY kelas'), 'kelas');
        if (! $kelas) {
            $this->error('Tabel pengajaran masih kosong: sinkronkan data SISTER dulu.');
            return self::FAILURE;
        }
        $mitra = array_column(DB::select(self::MITRA), 'mitra');
        $pola = DB::select('SELECT tipe, pola FROM pola_kelas_kerja_sama');
        $u = PolaKelas::usul($kelas, $mitra, $pola);
        $this->info(count($kelas) . ' nama kelas berbeda. Mitra di master tarif: ' . implode(', ', $mitra));

        if ($u['pakai']) {
            $this->line("\nUsulan pola (kode sama persis dengan nama mitra):");
            $this->table(['Tipe', 'Pola', 'Mitra', 'Jumlah kelas', 'Contoh'], array_map(fn ($x) => array_values($x), $u['pakai']));
        }
        if ($u['belum']) {
            $this->warn("\nKode huruf lain di nama kelas (BELUM dipakai, tentukan mitranya sendiri):");
            $this->table(['Kode', 'Jumlah kelas', 'Contoh'], array_map(fn ($x) => array_values($x), $u['belum']));
            $this->line("Bila sebuah kode adalah mitra: INSERT INTO pola_kelas_kerja_sama (tipe, pola, mitra) VALUES ('akhiran'|'awalan'|'mengandung', '<kode>', '<mitra>');");
        }
        if (! $u['pakai'] && ! $u['belum']) {
            $this->info('Semua kelas berkode sudah dikenali pola yang ada.');
        }
        if ($this->option('terapkan') && $u['pakai']) {
            DB::transaction(function () use ($u) {
                foreach ($u['pakai'] as $x) {
                    DB::insert('INSERT INTO pola_kelas_kerja_sama (tipe, pola, mitra) VALUES (?, ?, ?) ON CONFLICT DO NOTHING', [$x['tipe'], $x['pola'], $x['mitra']]);
                }
                $n = DB::update(self::TANDAI);
                $this->info("Pola disimpan. $n baris pengajaran ditandai Kerja Sama. Hitung ulang periode yang terdampak.");
            });
        } elseif ($this->option('terapkan')) {
            $this->line('Tidak ada usulan yang bisa diterapkan otomatis.');
        }
        return self::SUCCESS;
    }
}
