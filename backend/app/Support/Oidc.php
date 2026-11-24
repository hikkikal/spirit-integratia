<?php

namespace App\Support;

/**
 * Bagian OpenID Connect yang tidak bergantung pada Laravel (supaya bisa diuji sendiri):
 * PKCE, alamat otorisasi, dan pemeriksaan klaim ID token.
 *
 * Catatan keamanan: ID token diterima LANGSUNG dari token endpoint lewat TLS (back-channel), jadi menurut
 * OpenID Connect Core §3.1.3.7 tanda tangannya boleh tidak diperiksa; yang wajib diperiksa adalah klaim
 * iss, aud, exp, dan nonce. Identitas pengguna sendiri diambil dari UserInfo yang diminta dengan access token.
 */
final class Oidc
{
    public static function base64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    /** @return array{verifier:string, challenge:string} */
    public static function pkce(): array
    {
        $verifier = self::base64url(random_bytes(48)); // 64 karakter
        return ['verifier' => $verifier, 'challenge' => self::base64url(hash('sha256', $verifier, true))];
    }

    public static function acak(): string
    {
        return self::base64url(random_bytes(24));
    }

    public static function urlDiscovery(string $issuer): string
    {
        return rtrim($issuer, '/') . '/.well-known/openid-configuration';
    }

    public static function urlOtorisasi(string $endpoint, string $clientId, string $redirect, string $scopes, string $state, string $nonce, string $challenge): string
    {
        $q = http_build_query([
            'response_type' => 'code', 'client_id' => $clientId, 'redirect_uri' => $redirect, 'scope' => $scopes,
            'state' => $state, 'nonce' => $nonce, 'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
        return $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . $q;
    }

    /** Membaca isi (payload) JWT tanpa memeriksa tanda tangan. Null bila bentuknya salah. */
    public static function payload(string $jwt): ?array
    {
        $bagian = explode('.', $jwt);
        if (count($bagian) !== 3) {
            return null;
        }
        $json = base64_decode(strtr($bagian[1], '-_', '+/'), true);
        $data = $json === false ? null : json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Memeriksa klaim ID token. Mengembalikan pesan galat, atau null bila semua benar.
     * @param array<string,mixed> $klaim
     */
    public static function periksaKlaim(array $klaim, string $issuer, string $clientId, string $nonce, int $sekarang): ?string
    {
        if (rtrim((string) ($klaim['iss'] ?? ''), '/') !== rtrim($issuer, '/')) {
            return 'iss tidak cocok';
        }
        $aud = $klaim['aud'] ?? null;
        $aud = is_array($aud) ? $aud : [$aud];
        if (! in_array($clientId, $aud, true)) {
            return 'aud tidak memuat client id';
        }
        if (! isset($klaim['exp']) || ! is_numeric($klaim['exp']) || $klaim['exp'] < $sekarang - 60) {
            return 'token kedaluwarsa';
        }
        if (! hash_equals($nonce, (string) ($klaim['nonce'] ?? ''))) {
            return 'nonce tidak cocok';
        }
        if (! is_string($klaim['sub'] ?? null) || $klaim['sub'] === '') {
            return 'sub kosong';
        }
        return null;
    }

    public static function urlLogout(?string $endpoint, ?string $idToken, ?string $kembali): ?string
    {
        if (! $endpoint) {
            return null;
        }
        $q = array_filter(['id_token_hint' => $idToken, 'post_logout_redirect_uri' => $kembali]);
        return $q === [] ? $endpoint : $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }
}
