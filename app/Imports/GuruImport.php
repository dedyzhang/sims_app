<?php

namespace App\Imports;

use App\Models\Guru;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class GuruImport implements ToCollection, WithStartRow
{
    public int $imported = 0;
    public int $skipped = 0;
    /** Baris terisi Agama tapi nilainya tidak cocok daftar dropdown — diisi null, bukan diblokir. */
    public int $agamaTidakValid = 0;

    /** Kredensial akun yang baru dibuat batch ini — nilai plaintext, HANYA ada di sini sebelum di-hash. */
    public array $kredensial = [];

    /** Mulai dari baris 2 (lewati header) */
    public function startRow(): int
    {
        return 2;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $nama = trim((string)($row[0] ?? ''));

            // Lewati baris kosong & baris contoh
            if ($nama === '' || Str::startsWith(Str::upper($nama), 'CONTOH')) {
                $this->skipped++;
                continue;
            }

            $nik = trim((string)($row[1] ?? ''));
            $nip = trim((string)($row[2] ?? ''));
            
            // Lewati jika NIK atau NIP sudah terdaftar
            if ($nik !== '' && Guru::where('nik', $nik)->exists()) {
                $this->skipped++;
                continue;
            }
            if ($nip !== '' && Guru::where('nip', $nip)->exists()) {
                $this->skipped++;
                continue;
            }

            $identifier = $nik ?: ($nip ?: null);
            $usernameGuru = $identifier ?? (Str::slug($nama, '.') . '.' . Str::random(4));
            
            // Akun guru
            $passwordGuru = Str::random(8);
            
            $userGuru = User::create([
                'username'   => $usernameGuru,
                'identifier' => $identifier,
                'password'   => $passwordGuru,
                'access'     => 'guru',
                'must_change_password' => true,
            ]);

            Guru::create([
                'id_login'      => $userGuru->uuid,
                'nama'          => $nama,
                'nik'           => $nik === '' ? null : $nik,
                'nip'           => $nip === '' ? null : $nip,
                'jk'            => strtoupper(trim((string)($row[3] ?? 'L'))) === 'P' ? 'P' : 'L',
                'tempat_lahir'  => $this->str($row[4] ?? null),
                'tanggal_lahir' => $this->date($row[5] ?? null),
                'agama'         => $this->agama($row[6] ?? null),
                'alamat'        => $this->str($row[7] ?? null),
                'tingkat_studi' => $this->str($row[8] ?? null),
                'program_studi' => $this->str($row[9] ?? null),
                'universitas'   => $this->str($row[10] ?? null),
                'tahun_tamat'   => $this->str($row[11] ?? null),
                'tmt_ngajar'    => $this->date($row[12] ?? null),
                'tmt_smp'       => $this->date($row[13] ?? null),
                'no_telp'       => $this->str($row[14] ?? null),
            ]);

            $this->kredensial[] = [
                'nama' => $nama,
                'nik' => $nik,
                'nip' => $nip,
                'username_guru' => $usernameGuru,
                'password_guru' => $passwordGuru,
            ];

            $this->imported++;
        }
    }

    private function str($val): ?string
    {
        $val = trim((string)($val ?? ''));
        return $val === '' ? null : $val;
    }

    /** Cocokkan ke daftar agama baku (sama dgn dropdown Excel). Diisi tapi tak cocok → null + hitung. */
    private function agama($val): ?string
    {
        $mentah = trim((string)($val ?? ''));
        $baku = \App\Support\Agama::normalize($mentah);
        if ($mentah !== '' && $baku === null) {
            $this->agamaTidakValid++;
        }
        return $baku;
    }

    private function date($val): ?string
    {
        if (empty($val)) return null;
        try {
            if (is_numeric($val)) {
                return ExcelDate::excelToDateTimeObject($val)->format('Y-m-d');
            }
            return \Carbon\Carbon::parse($val)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
