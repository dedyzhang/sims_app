<?php

namespace App\Models;

use App\Support\TahunAjaran;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SppPembayaran extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'spp_pembayaran';

    public const STATUS_BELUM         = 'belum';
    public const STATUS_MENUNGGU      = 'menunggu';      // ortu unggah bukti, tunggu cek bendahara
    public const STATUS_TERVERIFIKASI = 'terverifikasi'; // bukti dicek bendahara, tunggu validasi rekening koran
    public const STATUS_LUNAS         = 'lunas';         // divalidasi via rekening koran bank
    public const STATUS_DITOLAK       = 'ditolak';

    protected $fillable = [
        'id_siswa', 'tahun_ajaran', 'bulan', 'batch_id', 'nominal', 'status',
        'bank', 'bukti_path', 'tanggal_bayar', 'jatuh_tempo', 'catatan',
        'diverifikasi_oleh', 'diverifikasi_pada',
    ];

    protected function casts(): array
    {
        return [
            'nominal'           => 'integer',
            'bulan'             => 'integer',
            'tanggal_bayar'     => 'date',
            'jatuh_tempo'       => 'date',
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'uuid');
    }

    public function verifikator()
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh', 'uuid');
    }

    public function getLabelBulanAttribute(): string
    {
        return TahunAjaran::labelBulan($this->tahun_ajaran, (int) $this->bulan);
    }

    /**
     * URL bukti → route terautentikasi (cek kepemilikan/role), BUKAN URL publik.
     * Bukti disimpan di disk privat (local) agar tidak dapat diakses tanpa auth.
     */
    public function getBuktiUrlAttribute(): ?string
    {
        return $this->bukti_path ? route('keuangan.tagihan.bukti', $this) : null;
    }

    public function isLunas(): bool
    {
        return $this->status === self::STATUS_LUNAS;
    }
}
