<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengumuman sekolah yang di-broadcast jadi notifikasi (database + FCM).
 * target_roles = daftar peran sasaran; null/[] berarti semua peran.
 */
class Pengumuman extends Model
{
    use HasUuids;

    protected $table = 'pengumuman';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'judul',
        'isi',
        'target_roles',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_roles' => 'array',
        ];
    }

    /** Peran sasaran yang tersedia untuk dipilih admin (selaras RBAC roles). */
    public const TARGET_ROLES = [
        'kepala'    => 'Kepala Sekolah',
        'kurikulum' => 'Kurikulum',
        'kesiswaan' => 'Kesiswaan',
        'sarpras'   => 'Sarpras',
        'guru'      => 'Guru',
        'walikelas' => 'Wali Kelas',
        'orangtua'  => 'Orang Tua',
        'siswa'     => 'Siswa',
    ];

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    /** true bila pengumuman ini ditujukan ke semua peran. */
    public function untukSemua(): bool
    {
        return empty($this->target_roles);
    }

    /** Apakah pengumuman ini relevan bagi user (untuk halaman riwayat). */
    public function menyasar(User $user): bool
    {
        return $this->untukSemua() || in_array((string) $user->access, $this->target_roles, true);
    }

    /**
     * User penerima notifikasi: sesuai target_roles, atau semua user bila kosong.
     * Selalu menyertakan admin/superadmin bila menyasar peran spesifik? Tidak —
     * admin memilih target secara eksplisit; kosong = benar-benar semua.
     */
    public function penerima()
    {
        $q = User::query();
        if (! $this->untukSemua()) {
            $q->whereIn('access', $this->target_roles);
        }

        return $q;
    }
}
