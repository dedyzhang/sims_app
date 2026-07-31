<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Grup percakapan otomatis per kelas. Dua tipe: 'kelas' & 'paguyuban'.
 * Lihat migration 2026_07_30_100001 untuk alasan scope (id_kelas, tipe, tahun_ajaran).
 */
class GrupChat extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'grup_chats';

    public const TIPE_KELAS = 'kelas';
    public const TIPE_PAGUYUBAN = 'paguyuban';

    public const MODE_DISKUSI = 'diskusi';
    public const MODE_PENGUMUMAN = 'pengumuman';

    /**
     * Peran yang boleh menulis walau grup sedang mode pengumuman.
     *
     * 'guru' SENGAJA tidak ada di sini: guru pengajar/mapel tidak lagi jadi
     * anggota Grup Kelas (lihat GrupChatService) — kalau ada baris lama peran
     * 'guru' yang belum sempat direkonsiliasi syncKelas(), ia harus diperlakukan
     * SEBAGAI NON-STAF (sabuk dan bretel), bukan diam-diam tetap punya hak staf.
     */
    public const PERAN_STAF = ['walikelas', 'admin'];

    protected $fillable = [
        'tipe', 'id_kelas', 'tahun_ajaran', 'id_semester',
        'nama', 'mode', 'status',
        'last_seq', 'last_message_at', 'last_message_preview', 'last_message_by',
    ];

    /**
     * Default di level model, bukan hanya di kolom DB: firstOrCreate() tidak
     * membaca ulang nilai default dari database, jadi tanpa ini instance yang baru
     * dibuat punya last_seq = null dan setiap penulisan turunannya melanggar
     * NOT NULL di grup_chat_members.
     */
    protected $attributes = [
        'mode' => self::MODE_DISKUSI,
        'status' => 'aktif',
        'last_seq' => 0,
    ];

    protected function casts(): array
    {
        return [
            'last_seq' => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'uuid');
    }

    public function members()
    {
        return $this->hasMany(GrupChatMember::class, 'grup_id', 'uuid');
    }

    /** Anggota yang belum keluar — dasar semua pengecekan akses & penerima notif. */
    public function activeMembers()
    {
        return $this->members()->whereNull('left_at');
    }

    public function messages()
    {
        return $this->hasMany(GrupChatMessage::class, 'grup_id', 'uuid');
    }

    /**
     * Alias murni untuk Route::scopeBindings() di grup.pesan.* & grup.lampiran.unduh:
     * Laravel mencari method lewat Str::plural() dari nama parameter rute {pesan},
     * yaitu `pesans()` (bukan `pesan()` tunggal, dan bukan `messages()`).
     */
    public function pesans()
    {
        return $this->messages();
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('status', 'aktif');
    }

    /** Grup yang keanggotaannya masih aktif untuk user ini. */
    public function scopeUntukUser(Builder $q, User $user): Builder
    {
        return $q->whereHas('members', fn ($m) => $m
            ->where('user_id', $user->getKey())
            ->whereNull('left_at'));
    }

    public function isPaguyuban(): bool
    {
        return $this->tipe === self::TIPE_PAGUYUBAN;
    }

    public function isArsip(): bool
    {
        return $this->status === 'arsip';
    }

    public function isModePengumuman(): bool
    {
        return $this->mode === self::MODE_PENGUMUMAN;
    }

    /** Nama default saat grup dibuat, mis. "Grup Kelas 7 A" / "Paguyuban Kelas 7 A". */
    public static function namaDefault(string $tipe, Kelas $kelas): string
    {
        $label = "{$kelas->tingkat} {$kelas->kelas}";

        return $tipe === self::TIPE_PAGUYUBAN
            ? "Paguyuban Kelas {$label}"
            : "Grup Kelas {$label}";
    }
}
