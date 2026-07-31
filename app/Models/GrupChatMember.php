<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Keanggotaan grup chat. Diturunkan otomatis dari struktur sekolah lewat
 * App\Services\GrupChatService — jangan dibuat/diedit manual dari controller lain.
 */
class GrupChatMember extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'grup_chat_members';

    /** Peran yang riwayat bacanya dibatasi sejak joined_seq (privasi angkatan/kelas lain). */
    public const PERAN_RIWAYAT_TERBATAS = ['siswa', 'orangtua'];

    protected $fillable = [
        'grup_id', 'user_id', 'peran', 'id_siswa', 'can_write',
        'joined_at', 'joined_seq', 'left_at',
        'last_read_seq', 'last_read_at',
        'last_notified_seq', 'last_notified_at', 'muted_until',
    ];

    protected function casts(): array
    {
        return [
            'can_write' => 'boolean',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'last_read_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'muted_until' => 'datetime',
            'joined_seq' => 'integer',
            'last_read_seq' => 'integer',
            'last_notified_seq' => 'integer',
        ];
    }

    public function grup()
    {
        return $this->belongsTo(GrupChat::class, 'grup_id', 'uuid');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'uuid');
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->whereNull('left_at');
    }

    public function isStaf(): bool
    {
        return in_array($this->peran, GrupChat::PERAN_STAF, true);
    }

    /** Siswa & ortu hanya boleh membaca pesan sejak mereka bergabung. */
    public function riwayatDibatasi(): bool
    {
        return in_array($this->peran, self::PERAN_RIWAYAT_TERBATAS, true);
    }

    /**
     * Seq TERENDAH yang boleh dibaca anggota ini.
     *
     * joined_seq menyimpan seq terakhir yang SUDAH ada saat ia bergabung, jadi pesan
     * pertama yang berhak ia baca adalah joined_seq + 1. Tanpa +1, anggota baru ikut
     * membaca satu pesan terakhir dari sebelum ia masuk.
     */
    public function batasSeq(): int
    {
        return $this->riwayatDibatasi() ? ((int) $this->joined_seq) + 1 : 0;
    }
}
