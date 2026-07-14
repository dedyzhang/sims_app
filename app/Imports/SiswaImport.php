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

            // NIS wajib ada & unik; kalau NIS kosong atau sudah ada, lewati baris ini
            $inputNis = trim((string)($row[1] ?? ''));
            if ($inputNis === '' || Siswa::where('nis', $inputNis)->exists()) {
                $this->skipped++;
                continue;
            }
            $nis = $inputNis;

            // Akun siswa
            $passwordSiswa = Str::random(8);
            $usernameSiswa = $nis;
            $userSiswa = User::create([
                'username'   => $usernameSiswa,
                'identifier' => $nis,
                'password'   => $passwordSiswa,
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
                'agama'         => $this->agama($row[6] ?? null),
                'no_handphone'  => $this->str($row[7] ?? null),
                'alamat'        => $this->str($row[8] ?? null),
                'nama_ayah'     => $this->str($row[9] ?? null),
                'pekerjaan_ayah'=> $this->str($row[10] ?? null),
                'no_telp_ayah'  => $this->str($row[11] ?? null),
                'nama_ibu'      => $this->str($row[12] ?? null),
                'pekerjaan_ibu' => $this->str($row[13] ?? null),
                'no_telp_ibu'   => $this->str($row[14] ?? null),
                'nama_wali'     => $this->str($row[15] ?? null),
                'pekerjaan_wali'=> $this->str($row[16] ?? null),
                'no_telp_wali'  => $this->str($row[17] ?? null),
                'sekolah_asal'  => $this->str($row[18] ?? null),
                'spp'           => is_numeric($row[19] ?? null) ? (int)$row[19] : null,
            ]);

            // Akun orang tua
            $passwordOrtu = Str::random(8);
            $usernameOrtu = 'P.' . $nis;
            $userOrtu = User::create([
                'username'   => $usernameOrtu,
                'identifier' => $nis . '-ortu',
                'password'   => $passwordOrtu,
                'access'     => 'orangtua',
                'must_change_password' => true,
            ]);
            Orangtua::create([
                'id_siswa' => $siswa->uuid,
                'id_login' => $userOrtu->uuid,
            ]);

            $this->kredensial[] = [
                'nama' => $nama,
                'nis' => $nis,
                'username_siswa' => $usernameSiswa,
                'password_siswa' => $passwordSiswa,
                'username_ortu' => $usernameOrtu,
                'password_ortu' => $passwordOrtu,
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
