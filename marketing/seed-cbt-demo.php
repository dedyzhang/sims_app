<?php

use App\Models\Kelas;
use App\Models\Pelajaran;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$ujian = DB::transaction(function (): Ujian {
    $admin = User::where('username', 'admin')->firstOrFail();
    $pelajaran = Pelajaran::firstOrCreate(['nama' => 'Matematika'], ['kkm' => 75]);
    $kelas = Kelas::first() ?? Kelas::create(['tingkat' => 7, 'kelas' => 'A']);

    $ujian = Ujian::withTrashed()->firstOrCreate(
        ['judul' => 'CBT PTS Matematika Semester Ganjil'],
        [
            'id_pelajaran' => $pelajaran->uuid,
            'created_by' => $admin->uuid,
            'instruksi' => 'Kerjakan setiap soal dengan teliti. Nilai objektif dihitung otomatis oleh sistem.',
            'jenis' => 'pts',
            'target_nilai' => 'pts',
            'durasi_menit' => 90,
            'acak_soal' => true,
            'acak_opsi' => true,
            'status' => 'published',
        ],
    );
    if ($ujian->trashed()) {
        $ujian->restore();
    }
    $ujian->update(['id_pelajaran' => $pelajaran->uuid]);

    UjianKelas::firstOrCreate(
        ['id_ujian' => $ujian->uuid, 'id_kelas' => $kelas->uuid],
        ['token_masuk' => 'MWCBT1', 'status' => 'open'],
    );

    if ($ujian->soal()->count() === 0) {
        $pertanyaan = [
            ['Berapakah hasil 12 × 8?', ['96', '86', '108', '88'], 0],
            ['Nilai dari 3² + 4² adalah …', ['25', '12', '49', '7'], 0],
            ['Pecahan yang senilai dengan 1/2 adalah …', ['2/4', '2/3', '3/5', '4/5'], 0],
        ];

        foreach ($pertanyaan as $index => [$teks, $opsi, $benar]) {
            $soal = UjianSoal::create([
                'id_ujian' => $ujian->uuid,
                'tipe' => 'mcq',
                'teks_soal' => $teks,
                'poin' => 10,
                'urutan' => $index + 1,
            ]);
            foreach ($opsi as $urutan => $teksOpsi) {
                UjianSoalOpsi::create([
                    'id_soal' => $soal->uuid,
                    'teks_opsi' => $teksOpsi,
                    'is_benar' => $urutan === $benar,
                    'urutan' => $urutan + 1,
                ]);
            }
        }
    }

    return $ujian->fresh();
});

echo $ujian->uuid.PHP_EOL;
