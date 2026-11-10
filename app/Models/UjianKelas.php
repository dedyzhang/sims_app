<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UjianKelas extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ujian_kelas';
    protected $primaryKey = 'uuid';

    protected $fillable = ['id_ujian', 'id_kelas', 'id_guru_pengampu', 'token_masuk', 'dibuka_mulai', 'dibuka_sampai', 'status'];

    protected function casts(): array
    {
        return [
            'dibuka_mulai'  => 'datetime',
            'dibuka_sampai' => 'datetime',
        ];
    }

    public function ujian()
    {
        return $this->belongsTo(Ujian::class, 'id_ujian', 'uuid');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'uuid');
    }

    public function guruPengampu()
    {
        return $this->belongsTo(Guru::class, 'id_guru_pengampu', 'uuid');
    }

    public function attempts()
    {
        return $this->hasMany(UjianAttempt::class, 'id_ujian_kelas', 'uuid');
    }

    protected static function booted(): void
    {
        static::saved(fn (self $uk) => \Illuminate\Support\Facades\Cache::forget('ujian_' . $uk->id_ujian . '_kelas_all'));
        static::deleted(fn (self $uk) => \Illuminate\Support\Facades\Cache::forget('ujian_' . $uk->id_ujian . '_kelas_all'));
    }

    public static function generateToken(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function isOpenNow(): bool
    {
        if ($this->status === 'closed' || !$this->ujian?->isPublished()) {
            return false;
        }
        if ($this->dibuka_mulai && now()->lt($this->dibuka_mulai)) {
            return false;
        }
        if ($this->dibuka_sampai && now()->gt($this->dibuka_sampai)) {
            return false;
        }
        return true;
    }

    /** Sudah terbit & py jadwal, tapi jam bukanya belum sampai — beda dari "ditutup"/belum terbit sama sekali, dipakai utk tampilkan badge "Belum Dimulai" + jam buka ke siswa. */
    public function belumDimulai(): bool
    {
        return $this->status !== 'closed'
            && $this->ujian?->isPublished()
            && $this->dibuka_mulai
            && now()->lt($this->dibuka_mulai);
    }

    /** Apakah guru pengampu saat ini terdaftar di data Ngajar untuk mapel & kelas/tingkat ini? */
    public function pengampuSesuaiNgajar(): bool
    {
        if (!$this->id_guru_pengampu) {
            return false;
        }

        $idPelajaran = $this->ujian?->id_pelajaran ?? $this->ujian()->value('id_pelajaran');
        if (!$idPelajaran) {
            return false;
        }

        // 1. Cocok persis di kelas ini
        $cocokKelas = Ngajar::where('id_guru', $this->id_guru_pengampu)
            ->where('id_kelas', $this->id_kelas)
            ->where('id_pelajaran', $idPelajaran)
            ->exists();

        if ($cocokKelas) {
            return true;
        }

        // 2. Atau guru ini mengajar mapel yang sama di tingkat kelas yang sama (guru mapel paralel)
        $tingkat = $this->kelas?->tingkat ?? $this->kelas()->value('tingkat');
        if ($tingkat) {
            $cocokTingkat = Ngajar::where('id_guru', $this->id_guru_pengampu)
                ->where('id_pelajaran', $idPelajaran)
                ->whereHas('kelas', fn ($q) => $q->where('tingkat', $tingkat))
                ->exists();

            if ($cocokTingkat) {
                return true;
            }
        }

        // 3. Atau guru mengajar mapel ini di salah satu kelas yang terdaftar dalam ujian yang sama
        $ujian = $this->ujian;
        if ($ujian) {
            $idKelasUjian = $ujian->kelas()->pluck('id_kelas')->filter()->all();
            if (!empty($idKelasUjian)) {
                return Ngajar::where('id_guru', $this->id_guru_pengampu)
                    ->where('id_pelajaran', $idPelajaran)
                    ->whereIn('id_kelas', $idKelasUjian)
                    ->exists();
            }
        }

        return false;
    }

    /** Apakah pengampu adalah guru di luar data ngajar (pengampu pengganti)? */
    public function pengampuLuarNgajar(): bool
    {
        return !empty($this->id_guru_pengampu) && !$this->pengampuSesuaiNgajar();
    }
}
