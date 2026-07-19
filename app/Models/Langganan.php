<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Langganan extends Model
{
    use HasUuids;

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_KADALUARSA = 'kadaluarsa';

    protected $table = 'langganan';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'paket',
        'durasi_bulan',
        'mulai_pada',
        'berakhir_pada',
        'status',
        'catatan',
        'diatur_oleh',
    ];

    protected function casts(): array
    {
        return [
            'durasi_bulan' => 'integer',
            'mulai_pada' => 'date',
            'berakhir_pada' => 'date',
        ];
    }

    public function pengatur()
    {
        return $this->belongsTo(User::class, 'diatur_oleh', 'uuid');
    }

    /** Lisensi berjalan (single-tenant → satu baris; ambil yang terbaru bila ada sisa data lama). */
    public static function current(): ?self
    {
        $langganan = static::orderByDesc('berakhir_pada')->first();

        $langganan?->sinkronkanStatus();

        return $langganan;
    }

    /**
     * Sisa masa aktif dalam HARI. Negatif = sudah kadaluarsa N hari.
     * Dihitung dari awal hari ini agar stabil sepanjang hari.
     */
    public function sisaHari(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->berakhir_pada->copy()->startOfDay(), false);
    }

    /**
     * Panjang masa langganan dalam HARI kalender (mulai → berakhir).
     * Sesuai durasi 3 / 6 / 12 bulan via addMonths, bukan 30×bulan.
     */
    public function totalHari(): int
    {
        return (int) $this->mulai_pada->copy()->startOfDay()
            ->diffInDays($this->berakhir_pada->copy()->startOfDay());
    }

    /**
     * Hitung jumlah hari kalender untuk durasi bulan dari tanggal mulai.
     */
    public static function hariUntukDurasi(int $durasiBulan, mixed $mulaiPada = null): int
    {
        $mulai = $mulaiPada
            ? \Illuminate\Support\Carbon::parse($mulaiPada)->startOfDay()
            : now()->startOfDay();

        return (int) $mulai->diffInDays($mulai->copy()->addMonths($durasiBulan));
    }

    /** Kadaluarsa saat sisa hari ≤ 0 (hari-H sudah terkunci). */
    public function kadaluarsa(): bool
    {
        return $this->sisaHari() <= 0;
    }

    /**
     * Sinkronkan status tersimpan dengan tanggal berakhir tanpa menulis bila
     * statusnya masih sama. Penguncian aplikasi tetap memakai sisaHari() agar
     * akurat meski scheduler belum sempat berjalan.
     */
    public function sinkronkanStatus(): bool
    {
        $status = $this->kadaluarsa() ? self::STATUS_KADALUARSA : self::STATUS_AKTIF;

        if ($this->status === $status) {
            return false;
        }

        $this->forceFill(['status' => $status])->save();

        return true;
    }

    /**
     * Dipanggil scheduler harian untuk menjaga kolom status konsisten walau
     * tidak ada request ke aplikasi pada hari lisensi berakhir.
     */
    public static function sinkronkanStatusKadaluarsa(): int
    {
        return static::query()
            ->where('status', self::STATUS_AKTIF)
            ->whereDate('berakhir_pada', '<=', now()->toDateString())
            ->update(['status' => self::STATUS_KADALUARSA]);
    }

    /**
     * Tingkat peringatan untuk banner superadmin.
     * null = aman; 'info' (≤ H-14), 'kuning' (≤ H-7), 'merah' (≤ H-3), 'kadaluarsa' (≤ 0).
     */
    public function tingkatPeringatan(): ?string
    {
        $sisa = $this->sisaHari();
        $ambang = config('langganan.peringatan');

        return match (true) {
            $sisa <= 0 => 'kadaluarsa',
            $sisa <= $ambang['merah'] => 'merah',
            $sisa <= $ambang['kuning'] => 'kuning',
            $sisa <= $ambang['info'] => 'info',
            default => null,
        };
    }
}
