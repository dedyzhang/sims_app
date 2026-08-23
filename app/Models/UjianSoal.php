<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UjianSoal extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ujian_soal';
    protected $primaryKey = 'uuid';

    protected $fillable = ['id_ujian', 'tipe', 'teks_soal', 'poin', 'urutan', 'meta', 'penjelasan', 'skor_mode'];

    protected function casts(): array
    {
        return [
            'poin'    => 'integer',
            'urutan'  => 'integer',
            'meta'    => 'array',
        ];
    }

    public function ujian()
    {
        return $this->belongsTo(Ujian::class, 'id_ujian', 'uuid');
    }

    public function opsi()
    {
        return $this->hasMany(UjianSoalOpsi::class, 'id_soal', 'uuid')->orderBy('urutan');
    }

    public function butuhOpsi(): bool
    {
        return in_array($this->tipe, ['mcq', 'mcq_complex', 'true_false'], true);
    }

    public function typeLabel(): string
    {
        return match ($this->tipe) {
            'mcq'         => 'Pilihan Ganda',
            'mcq_complex' => 'Pilihan Ganda Kompleks',
            'true_false'  => 'Benar/Salah',
            'match'       => 'Mencocokkan',
            'essay'       => 'Esai',
            default       => $this->tipe,
        };
    }

    /**
     * Total poin MAKSIMAL yg bisa didapat siswa dari soal ini. Utk mcq_complex & match
     * dgn skor_mode='proporsional', "Poin" berarti poin PER opsi/pasangan benar — efektifnya
     * poin × jumlah opsi/pasangan benar (poin 1 dgn 3 jawaban benar = maks 3 poin). Utk
     * skor_mode='all_or_nothing', konsep "per item" tak berlaku (semua-atau-tidak-sama-
     * sekali) — poin yg didapat PERSIS poin yg diinput, TANPA dikali jumlah item, jadi
     * maksimalnya ya poin itu sendiri. Tipe lain: poin apa adanya.
     */
    public function poinEfektif(): int
    {
        // Cek "!== proporsional" (bukan "=== all_or_nothing") krn model yg baru saja
        // dibuat via create() tanpa skor_mode eksplisit punya atribut in-memory NULL
        // (default kolom DB 'all_or_nothing' tak ikut ke-backfill tanpa fresh()/refresh())
        // — proporsional adalah SATU-SATUNYA nilai yg butuh pengali, jadi selain itu
        // (termasuk null) harus tetap dianggap all_or_nothing.
        if ($this->skor_mode !== 'proporsional') {
            return $this->poin;
        }
        return match ($this->tipe) {
            'mcq_complex' => $this->poin * $this->opsi->where('is_benar', true)->count(),
            'match'       => $this->poin * count($this->meta['pairs'] ?? []),
            default       => $this->poin,
        };
    }

    /**
     * Daftar item "benar" independen (satu per sub-kolom) khusus mcq_complex & match —
     * dipakai UjianAnalisisExport utk breakdown per-opsi/pasangan (bukan 1 kolom per soal
     * lagi, tapi 1 kolom per opsi/pasangan benar). Huruf label mcq_complex mengikuti posisi
     * KANONIK opsi (relasi opsi() sudah orderBy urutan) — SAMA dgn hurufOpsi() di export,
     * supaya "A"/"B" di sini konsisten dgn huruf yg dipakai di bagian mcq/true_false biasa.
     * Pasangan match diberi label huruf jg (bukan nomor) demi konsistensi visual, sesuai
     * urutan di meta['pairs']. Tipe lain: collection kosong (tak relevan).
     */
    public function itemBenarList(): \Illuminate\Support\Collection
    {
        if ($this->tipe === 'mcq_complex') {
            return $this->opsi->values()
                ->map(fn ($o, $i) => ['label' => chr(65 + $i), 'opsi_uuid' => $o->uuid, 'is_benar' => $o->is_benar])
                ->filter(fn ($item) => $item['is_benar'])
                ->values();
        }
        if ($this->tipe === 'match') {
            return collect($this->meta['pairs'] ?? [])->values()
                ->map(fn ($p, $i) => ['label' => chr(65 + $i), 'left' => $p['left'], 'right' => $p['right']]);
        }
        return collect();
    }

    /** Apakah siswa mendapat SATU item spesifik ini benar (dari itemBenarList()) di suatu jawaban. */
    public function itemDipilihBenar(UjianJawaban $jawaban, array $item): bool
    {
        if ($this->tipe === 'mcq_complex') {
            return in_array($item['opsi_uuid'], $jawaban->opsi_dipilih_multi ?? [], true);
        }
        if ($this->tipe === 'match') {
            return ($jawaban->jawaban_pasangan[$item['left']] ?? null) === $item['right'];
        }
        return false;
    }
}
