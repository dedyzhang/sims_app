<?php

namespace App\Sarpras\Imports;

use App\Sarpras\Models\Denah;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import data ruangan ke sebuah denah dari Excel/CSV.
 *
 * Header kolom yang dikenali (sesuai template):
 *   kode, nama, kapasitas, warna, deskripsi
 *
 * - kode wajib; baris tanpa kode dilewati.
 * - kode menjadi kunci UPSERT (dalam denah ini): ada -> diperbarui, belum -> dibuat.
 * - warna harus hex #rrggbb; selain itu diabaikan (pakai default).
 * - Ruangan BARU ditata otomatis dalam grid agar langsung tampak di denah;
 *   posisi bisa dirapikan via editor "Atur Blok Ruangan".
 */
class RuanganImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<int,string> Catatan per baris yang dilewati/diberi peringatan. */
    public array $dilewati = [];

    public int $dibuat = 0;

    public int $diperbarui = 0;

    public function __construct(private Denah $denah) {}

    public function collection(Collection $rows): void
    {
        // Offset grid: ruangan baru ditata setelah yang sudah ada.
        $urutan = $this->denah->ruangan()->count();

        foreach ($rows as $i => $row) {
            // +2: baris 1 = header, index mulai 0.
            $baris = $i + 2;

            $kode = trim((string) ($row['kode'] ?? ''));
            if ($kode === '') {
                $this->dilewati[] = "Baris {$baris}: kode ruangan wajib diisi — dilewati.";

                continue;
            }

            // Warna: hanya terima hex #rrggbb, selain itu null (pakai default).
            $warna = trim((string) ($row['warna'] ?? ''));
            if ($warna !== '' && ! preg_match('/^#[0-9a-fA-F]{6}$/', $warna)) {
                $this->dilewati[] = "Baris {$baris} ({$kode}): warna \"{$warna}\" bukan format hex — diabaikan.";
                $warna = '';
            }

            // Partial-update: hanya kolom yang diisi yang masuk payload, supaya
            // re-import tidak menimpa nama/kapasitas/deskripsi lama dengan kosong.
            $payload = [];
            if (($n = trim((string) ($row['nama'] ?? ''))) !== '') {
                $payload['nama'] = mb_substr($n, 0, 150);
            }
            if (($kap = $this->kapasitas($row['kapasitas'] ?? null)) !== null) {
                $payload['kapasitas'] = $kap;
            }
            if (($d = trim((string) ($row['deskripsi'] ?? ''))) !== '') {
                $payload['deskripsi'] = mb_substr($d, 0, 1000);
            }
            if ($warna !== '') {
                $payload['warna'] = $warna;
            }

            $ruangan = $this->denah->ruangan()->where('kode', $kode)->first();

            if ($ruangan) {
                // Update tanpa mengubah posisi blok yang sudah diatur.
                $ruangan->update($payload);
                $this->diperbarui++;
            } else {
                // Tata otomatis: grid 6 kolom, koordinat persen (center blok).
                [$x, $y] = $this->posisiGrid($urutan);
                $this->denah->ruangan()->create($payload + [
                    'kode' => mb_substr($kode, 0, 50),
                    'pos_x' => $x,
                    'pos_y' => $y,
                    'lebar' => 12,
                    'tinggi' => 8,
                ]);
                $this->dibuat++;
                $urutan++;
            }
        }
    }

    public function jumlahDilewati(): int
    {
        return count($this->dilewati);
    }

    private function kapasitas(mixed $v): ?int
    {
        if ($v === null || trim((string) $v) === '') {
            return null;
        }

        return max(0, (int) preg_replace('/\D/', '', (string) $v));
    }

    /**
     * Koordinat grid (persen) untuk blok ke-$n: 6 kolom, jarak tetap.
     *
     * @return array{0:float,1:float}
     */
    private function posisiGrid(int $n): array
    {
        $kolom = $n % 6;
        $baris = intdiv($n, 6);

        $x = 9 + $kolom * 15;   // 9,24,39,54,69,84
        $y = 10 + $baris * 13;  // 10,23,36,...

        return [(float) $x, (float) min($y, 95)];
    }
}
