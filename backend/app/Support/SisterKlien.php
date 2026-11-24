<?php

namespace App\Support;

/** Kegagalan yang menghentikan sinkronisasi. Pesannya boleh tampil ke Admin Input, jadi tidak boleh memuat kredensial. */
final class SisterGalat extends \RuntimeException
{
}

/**
 * Klien HTTP untuk SISTER Web Service (hanya baca). Murni PHP + ekstensi curl, tidak bergantung Laravel.
 *
 * Alur yang sama dengan skrip tarik_gabung_sister.py:
 *   POST /authorize {username, password, id_pengguna} -> {token}; GET lainnya memakai "Authorization: Bearer <token>".
 * - 401: login ulang satu kali, lalu coba lagi. Bila masih 401, berhenti.
 * - 429, 5xx, atau tidak ada balasan (timeout): coba ulang sampai `ulang` kali, jeda makin panjang, lalu berhenti dengan SisterGalat.
 * - 4xx lain (mis. 404 untuk satu detail): item itu dilewati dan dihitung di $dilewati, sinkronisasi jalan terus.
 */
final class SisterKlien
{
    public int $panggilan = 0;
    /** @var array<string,int> jumlah item yang dilewati karena 4xx, per path */
    public array $dilewati = [];
    /** @var array<string,array<int,array{n:int,contoh:string}>> per path lalu kode HTTP: jumlah dan satu contoh pesan dari SISTER (id dibuang) */
    public array $ditolak = [];

    private ?string $token = null;
    /** @var callable */
    private $transport;

    /**
     * @param array{base_url:string,username:?string,password:?string,id_pengguna:?string,timeout?:int,ulang?:int,jeda_ulang?:float,jeda_ms?:int} $cfg
     * @param callable|null $transport fn(string $metode, string $url, array $header, ?string $badan, int $timeout): array{0:int,1:string}
     *        [kode HTTP, isi]; kode 0 = tidak ada balasan. Diganti saat pengujian.
     */
    public function __construct(private array $cfg, ?callable $transport = null)
    {
        $this->transport = $transport ?? [self::class, 'curl'];
    }

    public static function curl(string $metode, string $url, array $header, ?string $badan, int $timeout): array
    {
        $h = [];
        foreach ($header as $k => $v) {
            $h[] = "$k: $v";
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $metode, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => $h, CURLOPT_FOLLOWLOCATION => false]);
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

    private function url(string $path, array $params = []): string
    {
        return rtrim((string) $this->nilai('base_url', ''), '/') . $path . ($params ? '?' . http_build_query($params) : '');
    }

    public function login(): void
    {
        foreach (['base_url' => 'SISTER_BASE_URL', 'username' => 'SISTER_USERNAME', 'password' => 'SISTER_PASSWORD', 'id_pengguna' => 'SISTER_ID_PENGGUNA'] as $k => $env) {
            if (trim((string) $this->nilai($k, '')) === '') {
                throw new SisterGalat("Pengaturan SISTER belum lengkap: isi $env di berkas .env.");
            }
        }
        [$st, $isi] = ($this->transport)('POST', $this->url('/authorize'), ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            json_encode(['username' => $this->cfg['username'], 'password' => $this->cfg['password'], 'id_pengguna' => $this->cfg['id_pengguna']]),
            (int) $this->nilai('timeout', 30));
        if ($st === 0) {
            throw new SisterGalat('Tidak bisa menghubungi SISTER (tidak ada balasan). Periksa SISTER_BASE_URL dan jaringan server.');
        }
        if ($st !== 200) {
            $petunjuk = ($st === 403 && stripos($isi, '<html') !== false)
                ? ' Ditolak sebelum sampai ke aplikasi SISTER (jaringan, IP, atau pelindung server).'
                : ($st === 400 || $st === 401 ? ' Periksa SISTER_USERNAME, SISTER_PASSWORD, dan SISTER_ID_PENGGUNA (harus berbentuk UUID).' : '');
            throw new SisterGalat("Login ke SISTER gagal (HTTP $st)." . $petunjuk);
        }
        $d = json_decode($isi, true);
        if (! is_array($d) || ! is_string($d['token'] ?? null) || $d['token'] === '') {
            throw new SisterGalat('Login ke SISTER berhasil tetapi respons tidak memuat token.');
        }
        $this->token = $d['token'];
    }

    /**
     * GET satu endpoint. Mengembalikan isi JSON yang sudah di-decode, atau null bila item ini dilewati (4xx).
     * @throws SisterGalat
     */
    public function get(string $path, array $params = []): ?array
    {
        if ($this->token === null) {
            $this->login();
        }
        $ulang = (int) $this->nilai('ulang', 3);
        $coba = 0;
        $loginUlang = false;
        while (true) {
            $coba++;
            [$st, $isi] = ($this->transport)('GET', $this->url($path, $params), ['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'],
                null, (int) $this->nilai('timeout', 30));
            $this->panggilan++;
            if ($st === 401) {
                if ($loginUlang) {
                    throw new SisterGalat("SISTER menolak token (HTTP 401) pada $path meskipun sudah login ulang.");
                }
                $loginUlang = true;
                $this->login();
                $coba--;
                continue;
            }
            if ($st === 200) {
                $d = json_decode($isi, true);
                if ($d === null && trim($isi) !== 'null') {
                    throw new SisterGalat("Respons SISTER pada $path bukan JSON yang sah.");
                }
                $jeda = (int) $this->nilai('jeda_ms', 0);
                if ($jeda > 0) {
                    usleep($jeda * 1000);
                }
                return is_array($d) ? $d : [];
            }
            if ($st === 0 || $st === 429 || $st >= 500) {
                if ($coba > $ulang) {
                    $timeout = (int) $this->nilai('timeout', 30);
                    throw new SisterGalat($st === 0
                        ? "Request ke SISTER ($path) tidak dibalas dalam $timeout detik. Sudah dicoba ulang $ulang kali."
                        : "Request ke SISTER ($path) gagal (HTTP $st). Sudah dicoba ulang $ulang kali.");
                }
                usleep((int) ($this->nilai('jeda_ulang', 2) * 1000000 * $coba));
                continue;
            }
            $kunci = preg_replace('#/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}#i', '/{id}', $path);   // cocok dengan path di SinkronSister::ENDPOINT
            $this->dilewati[$kunci] = ($this->dilewati[$kunci] ?? 0) + 1;
            $x = json_decode($isi, true);
            $pesan = is_array($x) ? (string) ($x['message'] ?? $x['error_description'] ?? $x['error'] ?? '') : '';
            $pesan = mb_substr(trim(preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '{id}', $pesan !== '' ? $pesan : strip_tags((string) $isi))), 0, 100);
            $this->ditolak[$kunci][$st]['n'] = ($this->ditolak[$kunci][$st]['n'] ?? 0) + 1;
            $this->ditolak[$kunci][$st]['contoh'] ??= $pesan;
            return null;
        }
    }

    /** Daftar baris dari respons: bisa langsung list, atau di dalam kunci seperti "data". */
    public static function daftar(?array $x): array
    {
        if ($x === null) {
            return [];
        }
        if (array_is_list($x)) {
            return $x;
        }
        foreach (['data', 'result', 'results', 'items'] as $k) {
            if (isset($x[$k]) && is_array($x[$k]) && array_is_list($x[$k])) {
                return $x[$k];
            }
        }
        return [];
    }
}
