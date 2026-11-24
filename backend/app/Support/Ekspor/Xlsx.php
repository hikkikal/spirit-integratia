<?php

namespace App\Support\Ekspor;

use RuntimeException;
use ZipArchive;

/**
 * Penulis .xlsx minimal tanpa paket tambahan (hanya ekstensi zip bawaan PHP).
 * Satu sheet: judul (beberapa baris teks), baris header, data, baris total.
 * Tipe kolom: 'teks', 'angka' (bulat, pemisah ribuan), 'uang' (sama dengan angka), 'desimal'.
 */
class Xlsx
{
    /** @param array{judul:string[],kolom:array,baris:array,total:?array,nama:string} $s */
    public static function bangun(array $s): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP zip belum aktif (aktifkan extension=zip di php.ini).');
        }
        $kolom = $s['kolom'];
        $rows = [];
        $r = 1;
        foreach ($s['judul'] as $i => $t) {
            $rows[] = '<row r="'.$r.'">'.self::sel($r, 0, $t, $i === 0 ? 3 : 0, 'teks').'</row>';
            $r++;
        }
        $r++; // baris kosong pemisah
        $cells = '';
        foreach ($kolom as $c => $k) {
            $cells .= self::sel($r, $c, $k['judul'], 1, 'teks');
        }
        $rows[] = '<row r="'.$r.'">'.$cells.'</row>';
        $headerRow = $r;
        $r++;
        foreach ($s['baris'] as $b) {
            $cells = '';
            foreach ($kolom as $c => $k) {
                $cells .= self::sel($r, $c, $b[$k['k']] ?? null, 0, $k['tipe']);
            }
            $rows[] = '<row r="'.$r.'">'.$cells.'</row>';
            $r++;
        }
        if (!empty($s['total'])) {
            $cells = '';
            foreach ($kolom as $c => $k) {
                $cells .= self::sel($r, $c, $s['total'][$k['k']] ?? null, 2, $k['tipe']);
            }
            $rows[] = '<row r="'.$r.'">'.$cells.'</row>';
        }
        $cols = '';
        foreach ($kolom as $c => $k) {
            $cols .= '<col min="'.($c + 1).'" max="'.($c + 1).'" width="'.($k['lebar'] ?? 14).'" customWidth="1"/>';
        }
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$headerRow.'" topLeftCell="A'.($headerRow + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$cols.'</cols><sheetData>'.implode('', $rows).'</sheetData></worksheet>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0"/><numFmt numFmtId="165" formatCode="#,##0.0#"/></numFmts>'
            .'<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFD9D9D9"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            // 0 biasa, 1 header, 2 total (angka tebal), 3 judul, 4 angka biasa
            .'<cellXfs count="6">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

        $nama = self::x(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $s['nama']), 0, 31));
        $zip = new ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$nama.'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        $isi = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $isi;
    }

    private static function sel(int $baris, int $kol, mixed $nilai, int $gaya, string $tipe): string
    {
        $ref = self::huruf($kol).$baris;
        if ($nilai === null || $nilai === '') {
            return $gaya ? '<c r="'.$ref.'" s="'.$gaya.'"/>' : '';
        }
        if ($tipe !== 'teks' && is_numeric($nilai)) {
            // Tetap numerik di Excel (bisa dijumlah); hanya tampilannya berpemisah ribuan.
            return '<c r="'.$ref.'" s="'.($tipe === 'desimal' ? 5 : ($gaya === 2 ? 2 : 4)).'"><v>'.(0 + $nilai).'</v></c>';
        }
        return '<c r="'.$ref.'" t="inlineStr"'.($gaya ? ' s="'.$gaya.'"' : '').'><is><t xml:space="preserve">'.self::x((string) $nilai).'</t></is></c>';
    }

    private static function huruf(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }
        return $s;
    }

    private static function x(string $t): string
    {
        // Buang karakter kontrol yang tidak sah di XML 1.0.
        $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $t) ?? '';
        return htmlspecialchars($t, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
