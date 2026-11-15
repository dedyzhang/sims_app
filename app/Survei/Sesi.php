<?php

namespace App\Survei;

use Illuminate\Http\Request;

class Sesi extends ModelSurvei
{
    protected $table = 'survei_sesis';

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'kelas_ids' => 'array',
            'token' => 'encrypted',
            'berlaku_sampai' => 'datetime',
            'diakhiri_pada' => 'datetime',
        ];
    }

    public static function buatToken(?string $sebelumnya = null): string
    {
        do {
            $token = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while ($token === $sebelumnya);

        return $token;
    }

    public function mencakup(string $kelasId): bool
    {
        $kelas = array_map(strval(...), (array) $this->kelas_ids);

        return in_array($kelasId, $kelas, true);
    }

    public function beriAkses(Request $request, string $kelasId): void
    {
        $request->session()->put($this->kunciAkses(), $this->tandaAkses($request, $kelasId));
    }

    public function akses(Request $request, string $kelasId): ?string
    {
        $tanda = $this->tandaAkses($request, $kelasId);

        return $this->mencakup($kelasId) && $request->session()->get($this->kunciAkses()) === $tanda
            ? $tanda
            : null;
    }

    private function kunciAkses(): string
    {
        return 'survei.sesi.'.$this->periode_id;
    }

    private function tandaAkses(Request $request, string $kelasId): string
    {
        return hash('sha256', implode('|', [
            $this->uuid, $this->getRawOriginal('token'), $request->user()->getKey(), $kelasId,
        ]));
    }
}
