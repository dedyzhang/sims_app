<?php

namespace App\Survei;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class SafeExamBrowser
{
    private function keys(): array
    {
        return preg_split('/[\s,]+/', trim((string) config('survei.seb_browser_exam_keys')), -1, PREG_SPLIT_NO_EMPTY);
    }

    public function siap(): bool
    {
        $keys = $this->keys();

        return Str::isUuid((string) config('survei.seb_quit_id'))
            && $keys !== []
            && collect($keys)->every(fn (string $key) => preg_match('/\A[a-f0-9]{64}\z/', $key) === 1);
    }

    public function verifikasi(?string $hash, string $url): bool
    {
        if (! $this->siap() || $hash === null || ! preg_match('/\A[a-f0-9]{64}\z/', $hash)) {
            return false;
        }

        foreach ($this->keys() as $key) {
            if (hash_equals(hash('sha256', $url.$key), $hash)) {
                return true;
            }
        }

        return false;
    }

    public function tantangan(Request $request, Periode $periode): string
    {
        $key = 'survei.seb.tantangan.'.$periode->uuid.'.'.$request->user()->getKey();
        if (! $request->session()->has($key)) {
            $request->session()->put($key, Str::random(32));
        }

        return $request->session()->get($key);
    }

    public function urlForm(Request $request, Periode $periode): string
    {
        return route('survei.form', ['periode' => $periode, 'verifikasi' => $this->tantangan($request, $periode)]);
    }

    public function kunciAkses(Request $request, Periode $periode): string
    {
        return 'survei.seb.akses.'.$periode->uuid.'.'.$request->user()->getKey();
    }

    public function urlKeluar(): string
    {
        return route('survei.seb.keluar', ['kode' => config('survei.seb_quit_id')]);
    }
}
