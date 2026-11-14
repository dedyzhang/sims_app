<?php

namespace App\Survei;

use App\Models\User;

class Periode extends ModelSurvei
{
    protected $table = 'survei_periodes';

    protected function casts(): array
    {
        return [
            'mulai' => 'datetime',
            'selesai' => 'datetime',
            'ditutup' => 'boolean',
            'pertanyaan' => 'array',
            'wajib_token' => 'boolean',
            'wajib_seb' => 'boolean',
            'wajib_kios' => 'boolean',
        ];
    }

    public function targets()
    {
        return $this->hasMany(Target::class, 'periode_id', 'uuid');
    }

    public function sesi()
    {
        return $this->hasMany(Sesi::class, 'periode_id', 'uuid');
    }

    public function sesiAktif(): ?Sesi
    {
        return $this->terbuka()
            ? $this->sesi()->whereNull('diakhiri_pada')->where('berlaku_sampai', '>', now())->first()
            : null;
    }

    public function terbuka(): bool
    {
        return ! $this->ditutup && now()->betweenIncluded($this->mulai, $this->selesai);
    }

    public function berakhir(): bool
    {
        return $this->ditutup || now()->greaterThan($this->selesai);
    }

    public function bolehMelihatIdentitas(User $user): bool
    {
        return $user->isAdmin() && $this->dibuat_oleh === $user->getKey();
    }

    public function bolehMemanduSesi(User $user): bool
    {
        return $this->bolehMelihatIdentitas($user) || $user->canFacilitateSurvei();
    }

    public function butir(string $jenis): array
    {
        return $this->pertanyaan[$jenis] ?? Pertanyaan::untuk($jenis);
    }

    public function pertanyaanTerbuka(string $jenis): array
    {
        return $this->pertanyaan['komentar_'.$jenis] ?? Pertanyaan::terbuka($jenis);
    }

    public function pilihanKendala(string $jenis): array
    {
        return $this->pertanyaan['kendala_'.$jenis] ?? Pertanyaan::kendala($jenis);
    }

    public function skor(string $jenis, int $index, int $jawaban): ?int
    {
        return Pertanyaan::skor($jenis, $index, $jawaban, $this->butirNegatif($jenis));
    }

    public function butirNegatif(string $jenis): array
    {
        // Periode sebelum v2 memakai butir berbeda dan seluruhnya berskor positif.
        return $this->pertanyaan['dibalik_'.$jenis]
            ?? ($this->butir($jenis) === Pertanyaan::untuk($jenis) ? Pertanyaan::dibalik($jenis) : []);
    }
}
