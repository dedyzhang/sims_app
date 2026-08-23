<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Ruang ujian FISIK (bukan pengganti UjianKelas/token/attempt) — murni lapisan
 * pengawasan+administrasi: siapa duduk di ruang mana (lintas kelas), daftar
 * hadir & berita acara per hari. Siswa tetap login pakai token per-tingkat &
 * attempt seperti biasa, terlepas dari ruangan ini. TIDAK ADA penugasan
 * pengawas tersimpan — siapa yg "mengawasi" ditentukan on-the-fly lewat scan
 * QR (lihat UjianRuanganScanController & UjianRuanganPolicy::awasi()).
 */
class UjianRuangan extends Model
{
    use HasUuids;

    protected $table = 'ujian_ruangan';
    protected $primaryKey = 'uuid';

    protected $fillable = ['id_ujian_paket', 'nama', 'kapasitas', 'denah_ruangan_id', 'keterangan'];

    protected function casts(): array
    {
        return ['kapasitas' => 'integer'];
    }

    public function paket()
    {
        return $this->belongsTo(UjianPaket::class, 'id_ujian_paket', 'uuid');
    }

    public function peserta()
    {
        return $this->hasMany(UjianRuanganPeserta::class, 'id_ruangan', 'uuid');
    }

    public function daftarHadir()
    {
        return $this->hasMany(UjianDaftarHadir::class, 'id_ruangan', 'uuid');
    }

    public function beritaAcara()
    {
        return $this->hasMany(UjianBeritaAcara::class, 'id_ruangan', 'uuid');
    }

    /** Ada ujian dijadwalkan (UjianJadwal) pada tanggal X — dasar gerbang scan QR (siswa & guru). */
    public function adaJadwalPada(?\Illuminate\Support\Carbon $tanggal = null): bool
    {
        $tanggal ??= now();

        return UjianJadwal::where('id_ujian_paket', $this->id_ujian_paket)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->exists();
    }

    /**
     * Semua sesi paket ini pada tanggal X — dasar daftar "Berita Acara per Sesi" di monitor.
     * `whereHas('jadwal')` PENTING: kalau admin edit/pindah jadwal satu-satunya dari suatu
     * sesi, sesi itu jadi "yatim" (0 jadwal) — filter ini menyaringnya dari listing aktif
     * TANPA menghapus row UjianSesi-nya (data BA/hadir historis yg masih menunjuk ke situ
     * tetap aman & tetap bisa dicetak lewat link langsung).
     */
    public function sesiPada(?\Illuminate\Support\Carbon $tanggal = null)
    {
        $tanggal ??= now();

        return UjianSesi::where('id_ujian_paket', $this->id_ujian_paket)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->whereHas('jadwal')
            ->with('jadwal.ujian.pelajaran')
            ->orderBy('jam_mulai')
            ->get();
    }

    /**
     * Sesi yg JAM-nya sedang berjalan SEKARANG (dipakai scan guru utk otomatis catat siapa
     * pengawas sesi itu) — kalau ada >1 sesi yg overlap (mis. 2 mapel jam sama di produksi),
     * ambil yg jam_mulai-nya paling dekat dgn sekarang. Utk siswa (checkin), JANGAN pakai ini
     * — pakai resolusi berbasis eligibility kelas di UjianRuanganScanController, krn tie-break
     * jam-doang bisa salah pilih sesi kalau 2 sesi jamnya identik (skenario produksi nyata).
     */
    public function sesiAktifSekarang(): ?UjianSesi
    {
        $sekarang = now()->format('H:i:s');

        return $this->sesiPada()
            ->filter(fn (UjianSesi $s) => $s->jam_mulai <= $sekarang && $sekarang <= $s->jam_selesai)
            ->sortByDesc('jam_mulai')
            ->first();
    }
}
