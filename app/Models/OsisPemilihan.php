<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class OsisPemilihan extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'osis_pemilihan';
    protected $fillable = ['nama', 'tahun_ajaran', 'status', 'aktif', 'jadwal_mulai', 'jadwal_selesai', 'dibuka_pada', 'ditutup_pada', 'dibuat_oleh'];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'jadwal_mulai' => 'datetime',
            'jadwal_selesai' => 'datetime',
            'dibuka_pada' => 'datetime',
            'ditutup_pada' => 'datetime',
        ];
    }

    /**
     * Gerbang tunggal dipakai OsisVoteController (show & store) — status DB 'dibuka' saja
     * tak cukup kalau admin sudah set jadwal: publik ditolak sebelum jadwal_mulai tiba, dan
     * otomatis dianggap tertutup begitu jadwal_selesai terlewati (walau status msh 'dibuka').
     */
    public function bolehMemilihSekarang(): bool
    {
        if ($this->status !== 'dibuka') {
            return false;
        }
        $now = now();
        if ($this->jadwal_mulai && $now->lt($this->jadwal_mulai)) {
            return false;
        }
        if ($this->jadwal_selesai && $now->gt($this->jadwal_selesai)) {
            return false;
        }

        return true;
    }

    /** Dipakai halaman "belum-dibuka" utk pesan yg tepat: draft/terjadwal/ditutup. */
    public function statusEfektif(): string
    {
        if ($this->status === 'dibuka' && $this->jadwal_mulai && now()->lt($this->jadwal_mulai)) {
            return 'terjadwal';
        }
        if ($this->status === 'dibuka' && $this->jadwal_selesai && now()->gt($this->jadwal_selesai)) {
            return 'ditutup';
        }

        return $this->status;
    }

    public function paslon()
    {
        return $this->hasMany(OsisPaslon::class, 'id_pemilihan', 'uuid')->orderBy('urutan_tampil')->orderBy('nomor_urut');
    }

    public function pemilih()
    {
        return $this->hasMany(OsisPemilih::class, 'id_pemilihan', 'uuid');
    }

    /** Memo per-request, pola identik Semester::aktif() — dipanggil di banyak tempat (dashboard, sidebar, generate token). */
    private static function memo(): \ArrayObject
    {
        if (! App::bound('osis.memo_aktif')) {
            App::instance('osis.memo_aktif', new \ArrayObject(['loaded' => false, 'value' => null]));
        }

        return App::make('osis.memo_aktif');
    }

    public static function aktif(): ?self
    {
        $memo = self::memo();

        if (! $memo['loaded']) {
            $memo['value'] = static::where('aktif', true)->first();
            $memo['loaded'] = true;
        }

        return $memo['value'];
    }

    /** Wajib dipanggil manual setelah mass-update (query()->update(...) tak memicu event model). */
    public static function clearCache(): void
    {
        if (App::bound('osis.memo_aktif')) {
            App::forgetInstance('osis.memo_aktif');
        }
    }

    protected static function booted(): void
    {
        static::saved(fn (self $m) => self::clearCache());
        static::deleted(fn (self $m) => self::clearCache());
    }
}
