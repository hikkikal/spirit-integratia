<?php

namespace App\Support;

/** Kegagalan yang menghentikan penarikan data akademik. Pesannya boleh tampil ke Admin Input, jadi tidak boleh memuat kredensial. */
final class AkademikGalat extends \RuntimeException
{
}

/**
 * Klien HTTP untuk Open API PNJ (Personalia, Academia, AcademiaV2), hanya baca. Murni PHP + curl, tanpa Laravel,
 * dan berjalan di proses sinkronisasi latar belakang (tidak ada sesi pengguna), jadi kredensialnya dari .env:
 *   1) ACADEMIA_TOKEN                       token tetap dari TIK, dipakai apa adanya; atau
 *   2) ACADEMIA_CLIENT_CREDENTIALS=true     token diminta otomatis (client_id + client_secret), diperbarui sendiri bila habis.
 * 401 pada mode 2: minta token baru satu kali lalu ulangi. 0/429/5xx: ulangi beberapa kali. 4xx lain dikembalikan ke pemanggil.
 */
final class AkademikKlien
{
    public int $panggilan = 0;
    private ?string $token = null;
    /** @var callable */
    private $transport;

    /**
     * @param array{url:string,token?:?string,client_id?:?string,client_secret?:?string,client_credentials?:bool,token_url?:string,scope?:string,verify_tls?:bool,timeout?:int,ulang?:int,jeda_ulang?:float,jeda_ms?:int} $cfg
     * @param callable|null $transport fn(string $metode, string $url, array $header, ?string $badan, int $timeout, bool $verifikasi): array{0:int,1:string}; kode 0 = tidak ada balasan
     */
    public function __construct(private array $cfg, ?callable $transport = null)
    {
        $this->transport = $transport ?? [self::class, 'curl'];
    }

    /** Ada kredensial yang bisa dipakai tanpa pengguna? */
    public static function siap(array $cfg): bool
    {
        if (trim((string) ($cfg['token'] ?? '')) !== '') {
            return true;
        }
        return ! empty($cfg['client_credentials']) && trim((string) ($cfg['client_id'] ?? '')) !== '' && trim((string) ($cfg['client_secret'] ?? '')) !== '';
    }

    public static function curl(string $metode, string $url, array $header, ?string $badan, int $timeout, bool $verifikasi = true): array
    {
        $h = [];
        foreach ($header as $k => $v) {
            $h[] = "$k: $v";
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $metode, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $h, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => $verifikasi, CURLOPT_SSL_VERIFYHOST => $verifikasi ? 2 : 0]);
        if ($badan !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $badan);
        }
        $isi = curl_exec($ch);
        if ($isi === false) {
            $e = curl_error($ch);
            curl_close($ch);
            return [0, $e];
        }
        $kode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$kode, (string) $isi];
    }

    private function nilai(string $k, $awal = null)
    {
        return $this->cfg[$k] ?? $awal;
    }

    private function ambilToken(): string
    {
        $tetap = trim((string) $this->nilai('token', ''));
        if ($tetap !== '') {
            return $tetap;
        }
        if (! self::siap($this->cfg)) {
            throw new AkademikGalat('Kredensial data akademik belum diisi: isi ACADEMIA_TOKEN, atau ACADEMIA_CLIENT_CREDENTIALS=true beserta client id dan secret, di berkas .env.');
        }
        // Otentikasi klien: 'basic' (bawaan, header Authorization) atau 'post' (client_id/client_secret di badan), sesuai pengaturan layanan di SSO.
        $post = strtolower((string) $this->nilai('token_auth', 'basic')) === 'post';
        $badan = ['grant_type' => 'client_credentials'];
        $scope = trim((string) $this->nilai('scope', ''));
        if ($scope !== '') {
            $badan['scope'] = $scope;
        }
        $header = ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'];
        if ($post) {
            $badan += ['client_id' => $this->cfg['client_id'], 'client_secret' => $this->cfg['client_secret']];
        } else {
            $header['Authorization'] = 'Basic ' . base64_encode($this->cfg['client_id'] . ':' . $this->cfg['client_secret']);
        }
        [$st, $isi] = ($this->transport)('POST', (string) $this->nilai('token_url', ''), $header, http_build_query($badan),
            (int) $this->nilai('timeout', 30), (bool) $this->nilai('verify_tls', true));
        if ($st === 0) {
            throw new AkademikGalat('Server SSO PNJ tidak terjangkau. Pastikan server berada di jaringan PNJ atau VPN.');
        }
        $d = json_decode($isi, true);
        if ($st !== 200 || ! is_array($d) || ! is_string($d['access_token'] ?? null) || $d['access_token'] === '') {
            // Kode galat dari SSO (mis. invalid_scope, unauthorized_client) ikut ditampilkan; tidak berisi rahasia.
            $kode = is_array($d) ? trim((string) ($d['error'] ?? '') . ' ' . (string) ($d['error_description'] ?? '')) : '';
            $kode = $kode !== '' ? ' SSO menjawab: ' . mb_substr(preg_replace('/[^\x20-\x7E]/', '', $kode), 0, 200) . '.' : '';
            throw new AkademikGalat("Gagal meminta token data akademik (HTTP $st).$kode Kemungkinan grant client_credentials atau scope belum diaktifkan TIK untuk layanan ini.");
        }
        return $d['access_token'];
    }

    /** @return array{status:int,json:mixed} */
    public function get(string $path, array $query = []): array
    {
        $url = rtrim((string) $this->nilai('url', ''), '/') . $path . ($query ? '?' . http_build_query($query) : '');
        $ulang = (int) $this->nilai('ulang', 3);
        $coba = 0;
        $tokenBaru = false;
        while (true) {
            $coba++;
            $this->token ??= $this->ambilToken();
            [$st, $isi] = ($this->transport)('GET', $url, ['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'],
                null, (int) $this->nilai('timeout', 30), (bool) $this->nilai('verify_tls', true));
            $this->panggilan++;
            if ($st === 401 && ! $tokenBaru && trim((string) $this->nilai('token', '')) === '') {
                $tokenBaru = true;
                $this->token = null;
                $coba--;
                continue;
            }
            if ($st === 0 || $st === 429 || $st >= 500) {
                if ($coba > $ulang) {
                    if ($st === 0) {
                        throw new AkademikGalat("Request ke $path tidak dibalas (" . mb_substr($isi, 0, 80) . "). Pastikan server berada di jaringan PNJ atau VPN.");
                    }
                    return ['status' => $st, 'json' => null];
                }
                usleep((int) ((float) $this->nilai('jeda_ulang', 2) * $coba * 1000000));
                continue;
            }
            $jeda = (int) $this->nilai('jeda_ms', 0);
            if ($jeda > 0) {
                usleep($jeda * 1000);
            }
            return ['status' => $st, 'json' => json_decode($isi, true)];
        }
    }
}
