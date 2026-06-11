<?php

namespace App\Imports;

use App\Models\Nis;
use App\Models\Orangtua;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class SiswaImport implements ToCollection, WithStartRow
{
    public int $imported = 0;
    public int $skipped = 0;

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

            // NIS: pakai input jika ada & unik, jika tidak generate otomatis
            $inputNis = trim((string)($row[1] ?? ''));
            if ($inputNis !== '' && !Siswa::where('nis', $inputNis)->exists()) {
                $nis = $inputNis;
            } else {
                $nisRecord = Nis::firstOrCreate([], ['kode' => 1]);
                do {
                    $nis = str_pad($nisRecord->kode, 5, '0', STR_PAD_LEFT);
                    $nisRecord->increment('kode');
                } while (Siswa::where('nis', $nis)->exists());
            }

            // Akun siswa
            $userSiswa = User::create([
                'username'   => 'siswa.' . $nis,
                'identifier' => $nis,
                'password'   => Str::random(8),
                'access'     => 'siswa',
                'must_change_password' => true,
            ]);

            $siswa = Siswa::create([
                'id_login'      => $userSiswa->uuid,
                'nis'           => $nis,
                'nama'          => $nama,
                'nisn'          => $this->str($row[2] ?? null),
                'jk'            => strtoupper(trim((string)($row[3] ?? 'L'))) === 'P' ? 'P' : 'L',
                'tempat_lahir'  => $this->str($row[4] ?? null),
                'tanggal_lahir' => $this->date($row[5] ?? null),
                'agama'         => $this->str($row[6] ?? null),
                'no_handphone'  => $this->str($row[7] ?? null),
                'alamat'        => $this->str($row[8] ?? null),
                'nama_ayah'     => $this->str($row[9] ?? null),
                'pekerjaan_ayah'=> $this->str($row[10] ?? null),
                'no_telp_ayah'  => $this->str($row[11] ?? null),
                'nama_ibu'      => $this->str($row[12] ?? null),
                'pekerjaan_ibu' => $this->str($row[13] ?? null),
                'no_telp_ibu'   => $this->str($row[14] ?? null),
                'sekolah_asal'  => $this->str($row[15] ?? null),
                'spp'           => is_numeric($row[16] ?? null) ? (int)$row[16] : null,
            ]);

            // Akun orang tua
            $userOrtu = User::create([
                'username'   => 'ortu.' . $nis,
                'identifier' => $nis . '-ortu',
                'password'   => Str::random(8),
                'access'     => 'ortu',
                'must_change_password' => true,
            ]);
            Orangtua::create([
                'id_siswa' => $siswa->uuid,
                'id_login' => $userOrtu->uuid,
            ]);

            $this->imported++;
        }
    }

    private function str($val): ?string
    {
        $val = trim((string)($val ?? ''));
        return $val === '' ? null : $val;
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
