<?php

namespace App\Sarpras\Imports;

use App\Sarpras\Models\Aset;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\KategoriAset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import katalog aset dari Excel/CSV.
 *
 * Header kolom yang dikenali (sesuai template):
 *   kode, nama, kategori, ruangan, kondisi, status, tgl_perolehan, nilai_perolehan
 *
 * - kode + nama wajib; baris tanpa keduanya dilewati.
 * - kode menjadi kunci UPSERT (per sekolah): ada -> diperbarui, belum -> dibuat.
 * - kategori dicocokkan via nama ATAU kode; ruangan via kode. Tak ketemu -> kosong (+catatan).
 * - kondisi/status di luar daftar -> pakai default (baik / aktif).
 * - nilai_perolehan menerima format "1.500.000" maupun "1500000".
 */
class AsetImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<int,string> Catatan per baris yang dilewati/diberi peringatan. */
    public array $dilewati = [];

    public int $dibuat = 0;

    public int $diperbarui = 0;

    private const KONDISI = ['baik', 'rusak_ringan', 'rusak_berat', 'hilang'];

    private const STATUS = ['aktif', 'dipinjam', 'perbaikan', 'dihapus', 'dimutasi'];

    public function collection(Collection $rows): void
    {
        // Peta pencarian dibangun sekali (hindari query per baris).
        $kategoriByNama = KategoriAset::pluck('id', 'nama')->mapWithKeys(fn ($id, $n) => [mb_strtolower(trim($n)) => $id]);
        $kategoriByKode = KategoriAset::whereNotNull('kode')->pluck('id', 'kode')->mapWithKeys(fn ($id, $k) => [mb_strtolower(trim($k)) => $id]);
        $ruanganByKode = DenahRuangan::pluck('id', 'kode')->mapWithKeys(fn ($id, $k) => [mb_strtolower(trim($k)) => $id]);

        foreach ($rows as $i => $row) {
            // +2: baris 1 = header, dan index mulai dari 0.
            $baris = $i + 2;

            $kode = trim((string) ($row['kode'] ?? ''));
            $nama = trim((string) ($row['nama'] ?? ''));

            if ($kode === '' || $nama === '') {
                $this->dilewati[] = "Baris {$baris}: kode & nama wajib diisi — dilewati.";

                continue;
            }

            // Partial-update: HANYA kolom yang diisi yang masuk payload, supaya
            // re-import untuk memperbarui satu kolom tidak menimpa nilai lama
            // (mis. kategori/ruangan) dengan kosong/null.
            $payload = ['nama' => mb_substr($nama, 0, 200)];

            // Kategori: cocokkan via nama lalu kode. Hanya di-set bila ketemu.
            if (($raw = trim((string) ($row['kategori'] ?? ''))) !== '') {
                $v = mb_strtolower($raw);
                if ($kategoriId = $kategoriByNama[$v] ?? $kategoriByKode[$v] ?? null) {
                    $payload['kategori_id'] = $kategoriId;
                } else {
                    $this->dilewati[] = "Baris {$baris} ({$kode}): kategori \"{$raw}\" tidak ditemukan — diabaikan.";
                }
            }

            // Ruangan: cocokkan via kode. Hanya di-set bila ketemu.
            if (($raw = trim((string) ($row['ruangan'] ?? ''))) !== '') {
                if ($ruanganId = $ruanganByKode[mb_strtolower($raw)] ?? null) {
                    $payload['ruangan_id'] = $ruanganId;
                } else {
                    $this->dilewati[] = "Baris {$baris} ({$kode}): ruangan \"{$raw}\" tidak ditemukan — diabaikan.";
                }
            }

            if (($m = trim((string) ($row['merk'] ?? ''))) !== '') {
                $payload['merk'] = mb_substr($m, 0, 150);
            }

            if (($k = mb_strtolower(trim((string) ($row['kondisi'] ?? '')))) !== '') {
                if (in_array($k, self::KONDISI, true)) {
                    $payload['kondisi'] = $k;
                } else {
                    $this->dilewati[] = "Baris {$baris} ({$kode}): kondisi \"{$row['kondisi']}\" tidak dikenal — diabaikan.";
                }
            }

            if (($s = mb_strtolower(trim((string) ($row['status'] ?? '')))) !== '') {
                if (in_array($s, self::STATUS, true)) {
                    $payload['status'] = $s;
                } else {
                    $this->dilewati[] = "Baris {$baris} ({$kode}): status \"{$row['status']}\" tidak dikenal — diabaikan.";
                }
            }

            if (($tgl = $this->tanggal($row['tgl_perolehan'] ?? null)) !== null) {
                $payload['tgl_perolehan'] = $tgl;
            }

            if (trim((string) ($row['nilai_perolehan'] ?? '')) !== '') {
                $payload['nilai_perolehan'] = $this->rupiah($row['nilai_perolehan']);
            }

            if (($sd = trim((string) ($row['sumber_dana'] ?? ''))) !== '') {
                $payload['sumber_dana'] = mb_substr($sd, 0, 100);
            }

            // UPSERT berdasarkan kode (school_id diisi otomatis saat creating).
            $aset = Aset::where('kode', $kode)->first();
            if ($aset) {
                $aset->update($payload);
                $this->diperbarui++;
            } else {
                // Default hanya berlaku untuk pembuatan baru (tidak menimpa update).
                Aset::create($payload + [
                    'kode' => mb_substr($kode, 0, 100),
                    'kondisi' => 'baik',
                    'status' => 'aktif',
                    'nilai_perolehan' => 0,
                ]);
                $this->dibuat++;
            }
        }
    }

    public function jumlahDilewati(): int
    {
        return count($this->dilewati);
    }

    /** Konversi nilai uang ke integer rupiah ("1.500.000" / "1500000" / 1500000). */
    private function rupiah(mixed $v): int
    {
        if (is_numeric($v)) {
            return max(0, (int) $v);
        }

        return max(0, (int) preg_replace('/\D/', '', (string) $v));
    }

    /** Konversi tanggal dari serial Excel atau string ke 'Y-m-d', null bila kosong/invalid. */
    private function tanggal(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        try {
            if (is_numeric($v)) {
                return ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
            }

            return Carbon::parse((string) $v)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
