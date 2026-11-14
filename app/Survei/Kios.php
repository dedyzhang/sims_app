<?php

namespace App\Survei;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class Kios
{
    public function siap(): bool
    {
        foreach ($this->kunci() as $key) {
            if ($this->publicKey($key) !== false) {
                return true;
            }
        }

        return false;
    }

    private function kunci(): array
    {
        return preg_split('/[\s,]+/', trim((string) config('survei.kios_public_keys')), -1, PREG_SPLIT_NO_EMPTY);
    }

    public function tantangan(Request $request, Periode $periode): string
    {
        $key = $this->kunciSesi($request, $periode).'.nonce';
        $nonce = $request->session()->get($key);
        if (! is_string($nonce)) {
            $nonce = Str::random(48);
            $request->session()->put($key, $nonce);
        }

        return $nonce;
    }

    public function verifikasi(Request $request, Periode $periode, string $aksi, string $tandaSesi): bool
    {
        $bukti = $request->input('bukti_kios');
        if (! is_string($bukti) || strlen($bukti) > 120) {
            return false;
        }
        $signature = base64_decode($bukti, true);
        $nonce = $request->session()->get($this->kunciSesi($request, $periode).'.nonce');
        if ($signature === false || ! is_string($nonce)) {
            return false;
        }

        $pesan = implode('|', ['sims-kios-v1', $periode->uuid, $aksi, $nonce, $tandaSesi]);
        foreach ($this->kunci() as $key) {
            $public = $this->publicKey($key);
            if ($public !== false && openssl_verify($pesan, $signature, $public, OPENSSL_ALGO_SHA256) === 1) {
                return true;
            }
        }

        return false;
    }

    private function publicKey(string $key): \OpenSSLAsymmetricKey|false
    {
        $public = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split($key, 64, "\n")."-----END PUBLIC KEY-----");
        if ($public === false) {
            return false;
        }
        $details = openssl_pkey_get_details($public);

        return ($details['ec']['curve_name'] ?? null) === 'prime256v1' ? $public : false;
    }

    public function kunciAkses(Request $request, Periode $periode): string
    {
        return $this->kunciSesi($request, $periode).'.akses';
    }

    private function kunciSesi(Request $request, Periode $periode): string
    {
        return 'survei.kios.'.$periode->uuid.'.'.$request->user()->getKey();
    }
}
