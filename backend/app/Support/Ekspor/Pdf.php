<?php

namespace App\Support\Ekspor;

/**
 * Penulis PDF tabel minimal tanpa paket tambahan: A4 landscape, font Helvetica bawaan PDF,
 * header tabel diulang tiap halaman, nomor halaman. Teks yang kepanjangan dipotong dengan "…".
 * Blok tanda tangan belum ada (menyusul).
 */
class Pdf
{
    private const W = 841.89;   // A4 landscape (pt)
    private const H = 595.28;
    private const M = 36;       // margin
    private const FS = 8;       // ukuran huruf isi
    private const RH = 14;      // tinggi baris

    /** Lebar glyph Helvetica per 1000 em untuk ASCII 32..126. */
    private const LEBAR = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];

    /** @param array{judul:string[],kolom:array,baris:array,total:?array} $s */
    public static function bangun(array $s): string
    {
        $kolom = $s['kolom'];
        $lebarTotal = self::W - 2 * self::M;
        $bobot = array_sum(array_map(fn ($k) => $k['lebar'] ?? 14, $kolom)) ?: 1;
        $lebar = array_map(fn ($k) => $lebarTotal * ($k['lebar'] ?? 14) / $bobot, $kolom);

        $baris = $s['baris'];
        if (!empty($s['total'])) {
            $baris[] = ['__total' => true] + $s['total'];
        }
        $halaman = [];
        $ops = '';
        $y = 0;
        $mulai = function () use (&$ops, &$y, $s, $kolom, $lebar) {
            $ops = '';
            $y = self::H - self::M;
            foreach ($s['judul'] as $i => $t) {
                $ukuran = $i === 0 ? 13 : 9;
                $ops .= self::teks(self::M, $y - $ukuran, $t, $ukuran, $i === 0);
                $y -= $ukuran + 5;
            }
            $y -= 6;
            // Header tabel
            $ops .= '0.85 g '.self::M.' '.($y - self::RH).' '.(self::W - 2 * self::M).' '.self::RH." re f 0 g\n";
            $x = self::M;
            foreach ($kolom as $c => $k) {
                $ops .= self::sel($x, $y - self::RH + 4, $lebar[$c], $k['judul'], $k['tipe'] !== 'teks', true);
                $x += $lebar[$c];
            }
            $y -= self::RH;
        };
        $mulai();
        foreach ($baris as $b) {
            if ($y - self::RH < self::M + 16) {
                $halaman[] = $ops;
                $mulai();
            }
            $tebal = !empty($b['__total']);
            if ($tebal) {
                $ops .= '0.5 w '.self::M.' '.($y).' m '.(self::W - self::M).' '.($y)." l S\n";
            }
            $x = self::M;
            foreach ($kolom as $c => $k) {
                $nilai = $b[$k['k']] ?? null;
                $ops .= self::sel($x, $y - self::RH + 4, $lebar[$c], self::format($nilai, $k['tipe']), $k['tipe'] !== 'teks', $tebal);
                $x += $lebar[$c];
            }
            $y -= self::RH;
            $ops .= '0.9 G 0.25 w '.self::M.' '.$y.' m '.(self::W - self::M).' '.$y." l S 0 G\n";
        }
        $halaman[] = $ops;

        // Susun objek PDF: 1 katalog, 2 pages, 3 font, 4 font tebal, lalu pasangan (halaman, isi).
        $n = count($halaman);
        $objek = [];
        $objek[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = '';
        for ($i = 0; $i < $n; $i++) {
            $kids .= (5 + 2 * $i).' 0 R ';
        }
        $objek[2] = '<< /Type /Pages /Kids ['.trim($kids).'] /Count '.$n.' >>';
        $objek[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objek[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        foreach ($halaman as $i => $isi) {
            $isi .= self::teks(self::W - self::M - 80, self::M - 8, 'Halaman '.($i + 1).' dari '.$n, 8, false);
            $objek[5 + 2 * $i] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::W.' '.self::H.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.(6 + 2 * $i).' 0 R >>';
            $objek[6 + 2 * $i] = "<< /Length ".strlen($isi)." >>\nstream\n".$isi."endstream";
        }
        $out = "%PDF-1.4\n";
        $offset = [];
        foreach ($objek as $no => $isi) {
            $offset[$no] = strlen($out);
            $out .= $no." 0 obj\n".$isi."\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 ".(count($objek) + 1)."\n0000000000 65535 f \n";
        foreach ($offset as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }
        $out .= "trailer\n<< /Size ".(count($objek) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
        return $out;
    }

    private static function format(mixed $v, string $tipe): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        if ($tipe === 'teks' || !is_numeric($v)) {
            return (string) $v;
        }
        if ($tipe === 'desimal') {
            return rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
        }
        return number_format((float) $v, 0, ',', '.');
    }

    /** Satu sel: teks dipotong sesuai lebar, rata kanan untuk angka. */
    private static function sel(float $x, float $y, float $lebar, string $t, bool $kanan, bool $tebal): string
    {
        $maks = $lebar - 6;
        $t = self::potong($t, $maks, self::FS);
        $w = self::ukur($t, self::FS);
        $tx = $kanan ? $x + $lebar - 3 - $w : $x + 3;
        return self::teks($tx, $y, $t, self::FS, $tebal);
    }

    private static function teks(float $x, float $y, string $t, float $ukuran, bool $tebal): string
    {
        return 'BT /'.($tebal ? 'F2' : 'F1').' '.$ukuran.' Tf '.round($x, 2).' '.round($y, 2).' Td ('.self::esc($t).") Tj ET\n";
    }

    private static function esc(string $t): string
    {
        $t = self::winansi($t);
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $t);
    }

    private static function winansi(string $t): string
    {
        $c = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $t);
        return $c === false ? preg_replace('/[^\x20-\x7E]/', '?', $t) : $c;
    }

    private static function ukur(string $t, float $ukuran): float
    {
        $t = self::winansi($t);
        $w = 0;
        for ($i = 0, $n = strlen($t); $i < $n; $i++) {
            $o = ord($t[$i]);
            $w += ($o >= 32 && $o <= 126) ? self::LEBAR[$o - 32] : 556;
        }
        return $w * $ukuran / 1000;
    }

    private static function potong(string $t, float $maks, float $ukuran): string
    {
        if (self::ukur($t, $ukuran) <= $maks) {
            return $t;
        }
        while ($t !== '' && self::ukur($t.'...', $ukuran) > $maks) {
            $t = mb_substr($t, 0, -1);
        }
        return $t.'...';
    }
}
