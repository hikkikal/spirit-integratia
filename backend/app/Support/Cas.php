<?php

namespace App\Support;

/**
 * Klien CAS (protokol 3) tanpa ketergantungan Laravel, supaya bisa diuji sendiri.
 * Nilai `service` saat login dan saat validasi harus SAMA PERSIS dan cocok dengan serviceId di CAS.
 */
final class Cas
{
    public static function loginUrl(string $server, string $service): string
    {
        return rtrim($server, '/') . '/login?service=' . rawurlencode($service);
    }

    public static function logoutUrl(string $server, string $service): string
    {
        return rtrim($server, '/') . '/logout?service=' . rawurlencode($service);
    }

    public static function validateUrl(string $server, string $service, string $ticket): string
    {
        return rtrim($server, '/') . '/p3/serviceValidate?service=' . rawurlencode($service) . '&ticket=' . rawurlencode($ticket);
    }

    /**
     * Membaca respons serviceValidate.
     * @return array{ok:bool, user:?string, atribut:array<string,string>, kode:?string}
     */
    public static function parse(string $xml): array
    {
        $gagal = ['ok' => false, 'user' => null, 'atribut' => [], 'kode' => 'RESPONS_TIDAK_VALID'];
        // Tolak DOCTYPE/ENTITY (XXE) sebelum diurai; respons CAS sah tidak memuatnya.
        if ($xml === '' || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            return $gagal;
        }
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($prev);
        if ($doc === false) {
            return $gagal;
        }
        $cas = $doc->children('http://www.yale.edu/tp/cas');
        if (isset($cas->authenticationFailure)) {
            $kode = (string) ($cas->authenticationFailure->attributes()['code'] ?? 'GAGAL');
            return ['ok' => false, 'user' => null, 'atribut' => [], 'kode' => $kode];
        }
        if (! isset($cas->authenticationSuccess)) {
            return $gagal;
        }
        $ok = $cas->authenticationSuccess;
        $user = trim((string) $ok->user);
        if ($user === '') {
            return $gagal;
        }
        $atribut = [];
        if (isset($ok->attributes)) {
            foreach ($ok->attributes->children('http://www.yale.edu/tp/cas') as $k => $v) {
                $atribut[$k] = trim((string) $v);
            }
        }
        return ['ok' => true, 'user' => $user, 'atribut' => $atribut, 'kode' => null];
    }

    /**
     * @param callable(string):?string $ambil  mengambil isi URL (null bila gagal terhubung)
     * @return array{ok:bool, user:?string, atribut:array<string,string>, kode:?string}
     */
    public static function validasi(string $server, string $service, string $ticket, callable $ambil): array
    {
        $isi = $ambil(self::validateUrl($server, $service, $ticket));
        if ($isi === null) {
            return ['ok' => false, 'user' => null, 'atribut' => [], 'kode' => 'CAS_TIDAK_TERJANGKAU'];
        }
        return self::parse($isi);
    }
}
