<?php

namespace App\Sarpras\Imports;

use App\Sarpras\Models\KategoriAset;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import kategori aset dari Excel/CSV.
 *
 * Header kolom yang dikenali (sesuai template):
 *   kode, nama, induk, deskripsi
 *
 * - nama wajib; baris tanpa nama dilewati.
 * - Kunci UPSERT: pakai kode bila diisi, selain itu pakai nama.
 * - "induk" dicocokkan via nama ATAU kode kategori (boleh merujuk baris lain
 *   di file yang sama — diproses 2 tahap). Tak ketemu / dirinya sendiri -> kosong.
 */
class KategoriImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<int,string> Catatan per baris yang dilewati/diberi peringatan. */
    public array $dilewati = [];

    public int $dibuat = 0;

    public int $diperbarui = 0;

    public function collection(Collection $rows): void
    {
        // ---- TAHAP 1: upsert kode/nama/deskripsi (belum set induk) ----
        // @var array<int,array{0:KategoriAset,1:string,2:int}> [model, induk mentah, baris]
        $diproses = [];

        foreach ($rows as $i => $row) {
            // +2: baris 1 = header, index mulai 0.
            $baris = $i + 2;

            $nama = trim((string) ($row['nama'] ?? ''));
            if ($nama === '') {
                $this->dilewati[] = "Baris {$baris}: nama kategori wajib diisi — dilewati.";

                continue;
            }

            $kode = trim((string) ($row['kode'] ?? ''));
            $deskripsi = trim((string) ($row['deskripsi'] ?? ''));

            // Cari existing: by kode bila diisi, selain itu by nama (case-insensitive).
            $query = KategoriAset::query();
            if ($kode !== '') {
                $query->where('kode', $kode);
            } else {
                $query->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)]);
            }
            $kategori = $query->first();

            // Partial-update: kode/deskripsi hanya di-set bila diisi, supaya update
            // (mis. yang dicocokkan via nama) tidak menimpa kode lama dengan null.
            $payload = ['nama' => mb_substr($nama, 0, 150)];
            if ($kode !== '') {
                $payload['kode'] = mb_substr($kode, 0, 50);
            }
            if ($deskripsi !== '') {
                $payload['deskripsi'] = mb_substr($deskripsi, 0, 500);
            }

            if ($kategori) {
                $kategori->update($payload);
                $this->diperbarui++;
            } else {
                $kategori = KategoriAset::create($payload);
                $this->dibuat++;
            }

            $indukMentah = trim((string) ($row['induk'] ?? $row['parent'] ?? ''));
            $diproses[] = [$kategori, $indukMentah, $baris];
        }

        // ---- TAHAP 2: resolve induk (semua kategori sudah ada) ----
        $byNama = KategoriAset::pluck('id', 'nama')
            ->mapWithKeys(fn ($id, $n) => [mb_strtolower(trim((string) $n)) => $id]);
        $byKode = KategoriAset::whereNotNull('kode')->pluck('id', 'kode')
            ->mapWithKeys(fn ($id, $k) => [mb_strtolower(trim((string) $k)) => $id]);

        // Peta id => parent_id untuk deteksi siklus (mencerminkan state terkini).
        $parentMap = KategoriAset::pluck('parent_id', 'id')->all();

        foreach ($diproses as [$kategori, $indukMentah, $baris]) {
            if ($indukMentah === '') {
                continue;
            }

            $v = mb_strtolower($indukMentah);
            $indukId = $byNama[$v] ?? $byKode[$v] ?? null;

            if (! $indukId) {
                $this->dilewati[] = "Baris {$baris} ({$kategori->nama}): induk \"{$indukMentah}\" tidak ditemukan — dikosongkan.";

                continue;
            }
            if ($indukId === $kategori->id) {
                $this->dilewati[] = "Baris {$baris} ({$kategori->nama}): induk tidak boleh dirinya sendiri — dikosongkan.";

                continue;
            }
            // Cegah siklus: bila kategori ini sudah jadi leluhur calon induk,
            // menjadikannya induk akan membentuk lingkaran (mis. A→B & B→A).
            if ($this->menyebabkanSiklus($kategori->id, $indukId, $parentMap)) {
                $this->dilewati[] = "Baris {$baris} ({$kategori->nama}): induk \"{$indukMentah}\" menyebabkan siklus — dikosongkan.";

                continue;
            }

            if (($parentMap[$kategori->id] ?? null) !== $indukId) {
                $kategori->update(['parent_id' => $indukId]);
                $parentMap[$kategori->id] = $indukId;
            }
        }
    }

    public function jumlahDilewati(): int
    {
        return count($this->dilewati);
    }

    /**
     * Apakah menjadikan $indukId sebagai induk dari $id membentuk siklus?
     * Telusuri rantai leluhur $indukId; bila menemui $id, berarti siklus.
     */
    private function menyebabkanSiklus(string $id, ?string $indukId, array $parentMap): bool
    {
        $cursor = $indukId;
        $langkah = 0;

        while ($cursor !== null) {
            if ($cursor === $id) {
                return true;
            }
            if (++$langkah > 1000) {
                return true; // jaga-jaga bila data lama sudah korup.
            }
            $cursor = $parentMap[$cursor] ?? null;
        }

        return false;
    }
}
