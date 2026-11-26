<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use App\Support\RichText;

class Ujian extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'ujians';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'id_pelajaran', 'id_materi', 'id_ujian_paket', 'created_by', 'judul', 'instruksi', 'jenis',
        'target_nilai', 'mode_skor', 'durasi_menit', 'acak_soal', 'acak_opsi', 'tampilkan_pembahasan', 'status',
    ];

    protected function casts(): array
    {
        return [
            'durasi_menit'          => 'integer',
            'acak_soal'             => 'boolean',
            'acak_opsi'             => 'boolean',
            'tampilkan_pembahasan'  => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Ujian $ujian) {
            $idSesiList = $ujian->jadwalUjian()->pluck('id_sesi')->filter()->unique();
            $ujian->jadwalUjian()->delete();
            if ($idSesiList->isNotEmpty()) {
                UjianSesi::whereIn('uuid', $idSesiList)
                    ->whereDoesntHave('jadwal')
                    ->whereDoesntHave('beritaAcara')
                    ->whereDoesntHave('daftarHadir')
                    ->delete();
            }
        });
    }

    public function pelajaran()
    {
        return $this->belongsTo(Pelajaran::class, 'id_pelajaran', 'uuid');
    }

    public function materi()
    {
        return $this->belongsTo(Materi::class, 'id_materi', 'uuid');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function soal()
    {
        return $this->hasMany(UjianSoal::class, 'id_ujian', 'uuid')->orderBy('urutan');
    }

    public function getCachedSoalDanOpsi()
    {
        return Cache::remember(
            'ujian_' . $this->uuid . '_soal_opsi',
            now()->addHours(6),
            fn () => $this->soal()->with('opsi')->get()
        );
    }

    /**
     * Semua kunci cache yg diturunkan dari soal ujian ini — dibuang BERSAMAAN supaya tak
     * pernah ada satu cache yg basi sementara yg lain sudah segar.
     */
    public static function kunciCacheSoal(string $idUjian): array
    {
        return [
            'ujian_' . $idUjian . '_soal_opsi',
            'ujian_' . $idUjian . '_soal_tampil_v1',
            'ujian_' . $idUjian . '_tipe_soal_v1',
        ];
    }

    /** Dipanggil juga dari event model UjianSoal/UjianSoalOpsi (lihat booted() masing2). */
    public static function lupakanCacheSoal(?string $idUjian): void
    {
        if (! $idUjian) {
            return;
        }
        foreach (self::kunciCacheSoal($idUjian) as $kunci) {
            Cache::forget($kunci);
        }
    }

    public function clearSoalCache()
    {
        self::lupakanCacheSoal($this->uuid);
    }

    /**
     * Isi soal yg SUDAH disanitasi (RichText::clean) & siap dikirim ke browser siswa —
     * dihitung SEKALI per ujian lalu di-cache, bukan diulang utk tiap siswa tiap kali
     * halaman kerjakan dibuka (dulu: ±1.800 siswa × N soal × regex sanitasi pada menit yg
     * sama). Hanya bagian yg IDENTIK utk semua siswa yg disimpan di sini; urutan acak per
     * siswa tetap diterapkan di UjianSiswaController::kerjakan() dari ujian_attempts.
     * is_benar TIDAK PERNAH ikut disimpan di sini — payload ini memang utk browser siswa.
     *
     * Bentuk per soal: ['uuid','tipe','teks_soal','poin'] + 'pairs' (match, sudah difilter
     * pasangan kosong) ATAU 'opsi' (mcq/mcq_complex/true_false: [uuid => teks bersih], urut
     * sesuai kolom urutan; opsi yg teks-nya null dibuang spt perilaku lama).
     */
    public function getCachedSoalTampil(): array
    {
        return Cache::remember(
            'ujian_' . $this->uuid . '_soal_tampil_v1',
            now()->addHours(6),
            function () {
                $hasil = [];
                foreach ($this->getCachedSoalDanOpsi() as $s) {
                    $item = [
                        'uuid'      => $s->uuid,
                        'tipe'      => $s->tipe,
                        'teks_soal' => RichText::clean($s->teks_soal),
                        'poin'      => $s->poinEfektif(),
                    ];
                    if ($s->tipe === 'match') {
                        $item['pairs'] = collect($s->meta['pairs'] ?? [])->map(fn ($p) => [
                            'left'  => RichText::clean($p['left'] ?? $p['kiri'] ?? ''),
                            'right' => RichText::clean($p['right'] ?? $p['kanan'] ?? ''),
                        ])->filter(fn ($p) => $p['left'] !== '' || $p['right'] !== '')->values()->all();
                    } elseif ($s->butuhOpsi()) {
                        $item['opsi'] = [];
                        foreach ($s->opsi as $o) {
                            if ($o->teks_opsi !== null) {
                                $item['opsi'][$o->uuid] = RichText::clean($o->teks_opsi);
                            }
                        }
                    }
                    $hasil[$s->uuid] = $item;
                }

                return $hasil;
            }
        );
    }

    /**
     * Peta kecil uuid soal => tipe, utk endpoint simpan jawaban (dipanggil puluhan ribu kali
     * selama ujian). Dulu tiap simpan membongkar SELURUH cache soal+opsi (bisa ratusan KB
     * kalau ada gambar/rumus) cuma utk tahu tipe satu soal.
     */
    public function getCachedTipeSoal(): array
    {
        return Cache::remember(
            'ujian_' . $this->uuid . '_tipe_soal_v1',
            now()->addHours(6),
            fn () => $this->soal()->pluck('tipe', 'uuid')->all()
        );
    }

    /**
     * TTL sengaja pendek (5 menit, dulu 6 jam): semua jalur tulis resmi sudah membuang cache
     * ini (event UjianKelas, UjianJadwalSync, generate ulang token) — TTL ini cuma jaring
     * pengaman kalau suatu saat ada jalur tulis baru yg lupa membuangnya.
     */
    public function getCachedKelas()
    {
        return Cache::remember(
            'ujian_' . $this->uuid . '_kelas_all',
            now()->addMinutes(5),
            fn () => $this->kelas()->get()
        );
    }

    public function clearKelasCache()
    {
        Cache::forget('ujian_' . $this->uuid . '_kelas_all');
    }

    /**
     * Baca baris ujian_kelas TERBARU dari DB utk satu kelas — dipakai siswa saat versi cache
     * menolak (jadwal/token mungkin baru diubah). Cache bersama HANYA dibuang kalau memang
     * terbukti basi; dulu cache dibuang di SETIAP penolakan, jadi selama menunggu jam mulai
     * tiap siswa yg membuka gerbang/menekan "Mulai" ikut menghapus cache milik semua siswa.
     */
    public function kelasSegarUntuk(string $idKelas, ?UjianKelas $dariCache): ?UjianKelas
    {
        $segar = $this->kelas()->where('id_kelas', $idKelas)->first();

        $sidikJari = fn (?UjianKelas $uk) => $uk ? [
            $uk->token_masuk,
            $uk->status,
            $uk->dibuka_mulai?->toDateTimeString(),
            $uk->dibuka_sampai?->toDateTimeString(),
            $uk->updated_at?->toDateTimeString(),
        ] : null;

        if ($sidikJari($segar) !== $sidikJari($dariCache)) {
            $this->clearKelasCache();
        }

        return $segar;
    }

    public function kelas()
    {
        return $this->hasMany(UjianKelas::class, 'id_ujian', 'uuid');
    }

    public function susulans()
    {
        return $this->hasMany(UjianSusulan::class, 'id_ujian', 'uuid');
    }

    /** Paket (folder periode ujian, mis. "PAS Semester 1") — opsional, ujian standalone tetap sah tanpa ini. */
    public function paket()
    {
        return $this->belongsTo(UjianPaket::class, 'id_ujian_paket', 'uuid');
    }

    /** Pre-test / Uji Coba Publik dengan Link & QR Code */
    public function pretest()
    {
        return $this->hasOne(UjianPretest::class, 'id_ujian', 'uuid');
    }

    /** Ujian standalone (id_ujian_paket null, "Ulangan Harian" bebas guru) selalu mode 1 — tak pernah wajib scan. */
    public function wajibScanQr(): bool
    {
        return (bool) ($this->paket?->wajib_scan_qr ?? false);
    }

    /** Baris jadwal (hari×jam) milik ujian ini — lihat UjianJadwalSync utk cara dipakai. */
    public function jadwalUjian()
    {
        return $this->hasMany(UjianJadwal::class, 'id_ujian', 'uuid');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /** target_nilai='sumatif' butuh id_materi (per-Ngajar) — jadi cuma boleh 1 kelas. */
    public function butuhSatuKelas(): bool
    {
        return $this->target_nilai === 'sumatif';
    }

    public function jenisLabel(): string
    {
        return match ($this->jenis) {
            'pts'   => 'PTS',
            'pas'   => 'PAS',
            'uas'   => 'UAS',
            default => 'Ulangan Harian',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'published' => 'Terbit',
            'closed'    => 'Ditutup',
            default     => 'Draf',
        };
    }
}
