<?php

namespace App\Support;

/**
 * Mengusulkan pola kelas Kerja Sama dari nama kelas yang benar-benar ada di data SISTER (tabel pengajaran).
 * Kelas reguler berbentuk angka+huruf ("6B", "7A"); kode mitra menempel pada nama kelas ("JGU7A", "6AMSU", "TI-5A-CIT").
 * Kode usulan = deretan huruf yang tersisa setelah bagian angka+huruf ("7A") dibuang. Yang dipakai otomatis HANYA kode yang sama persis
 * dengan nama mitra di master_tarif; kode lain hanya dilaporkan, supaya Admin yang memutuskan (tidak ditebak).
 */
final class PolaKelas
{
    /** @return string[] kode huruf yang tersisa dari satu nama kelas, mis. "TI-5A-CIT" => ['TI','CIT'] */
    public static function kode(string $kelas): array
    {
        $k = mb_strtoupper(trim($kelas));
        $k = preg_replace('/\d+[A-Z]?/', ' ', $k);
        return array_values(array_filter(preg_split('/[^A-Z]+/', $k) ?: [], fn ($t) => strlen($t) >= 2));
    }

    public static function tipe(string $kelas, string $kode): string
    {
        $k = mb_strtoupper(trim($kelas));
        return str_starts_with($k, $kode) ? 'awalan' : (str_ends_with($k, $kode) ? 'akhiran' : 'mengandung');
    }

    /** Apakah kelas sudah dikenali pola yang ada. @param array<object{tipe:string,pola:string}> $pola */
    public static function sudahDikenali(string $kelas, array $pola): bool
    {
        return SinkronSister::jenisKelas($kelas, $pola) === 'Kerja Sama';
    }

    /**
     * @param string[] $kelas  nama kelas berbeda dari tabel pengajaran
     * @param string[] $mitra  nama mitra di master_tarif
     * @param array $pola      pola yang sudah ada
     * @return array{pakai: array[], belum: array[]}  pakai: [tipe, pola, mitra, jumlah, contoh]; belum: [kode, jumlah, contoh]
     */
    public static function usul(array $kelas, array $mitra, array $pola): array
    {
        $mitraBesar = [];
        foreach ($mitra as $m) {
            $mitraBesar[mb_strtoupper(trim($m))] = $m;
        }
        $peta = [];   // kode => [kelas...]
        foreach (array_unique($kelas) as $k) {
            if (self::sudahDikenali($k, $pola)) {
                continue;
            }
            foreach (array_unique(self::kode($k)) as $kode) {
                $peta[$kode][] = $k;
            }
        }
        $pakai = $belum = [];
        ksort($peta);
        foreach ($peta as $kode => $daftar) {
            $contoh = implode(', ', array_slice($daftar, 0, 4));
            if (isset($mitraBesar[$kode])) {
                $tipe = array_values(array_unique(array_map(fn ($k) => self::tipe($k, $kode), $daftar)));
                $pakai[] = ['tipe' => count($tipe) === 1 ? $tipe[0] : 'mengandung', 'pola' => $kode, 'mitra' => $mitraBesar[$kode], 'jumlah' => count($daftar), 'contoh' => $contoh];
            } else {
                $belum[] = ['kode' => $kode, 'jumlah' => count($daftar), 'contoh' => $contoh];
            }
        }
        usort($belum, fn ($a, $b) => $b['jumlah'] <=> $a['jumlah']);
        return ['pakai' => $pakai, 'belum' => $belum];
    }
}
