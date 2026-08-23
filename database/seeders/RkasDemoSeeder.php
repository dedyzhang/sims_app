<?php

namespace Database\Seeders;

use App\Models\RkasPlan;
use App\Models\RkasReferenceSet;
use App\Models\Setting;
use App\Models\User;
use App\Services\Keuangan\RkasValidationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data preview yang sengaja dipisahkan dari DatabaseSeeder.
 *
 * Jalankan manual:
 * php artisan db:seed --class=RkasDemoSeeder
 *
 * Semua label memakai CONTOH INTERNAL agar tidak disalahartikan sebagai
 * referensi resmi ARKAS atau data yang sudah disahkan Dinas/MARKAS.
 */
class RkasDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->whereIn('access', ['superadmin', 'admin'])->first();
        if (! $admin) {
            $this->command?->warn('Lewati demo RKAS: belum ada user admin.');
            return;
        }

        $schoolName = (string) Setting::get('nama_sekolah', 'SMP Edutive');
        $npsn = (string) Setting::get('npsn', '00000000');
        $scope = [
            'tahun_anggaran' => 2026,
            'versi' => 'CONTOH-INTERNAL-2026',
            'jenjang' => 'Dikdasmen',
            'sumber_dana' => 'BOSP Reguler',
        ];

        $referenceSet = RkasReferenceSet::updateOrCreate(
            $scope,
            [
                'label' => '[CONTOH INTERNAL] Referensi BOSP 2026 — bukan referensi resmi',
                'source_url' => null,
                'source_checksum' => hash('sha256', 'sims-rkas-demo-2026'),
                'rules' => [
                    'percentages' => [
                        ['label' => 'Buku / Pengembangan Perpustakaan', 'components' => ['Buku'], 'min_bps' => 1000],
                        ['label' => 'Honor contoh', 'components' => ['Honor'], 'max_bps' => 2000],
                        ['label' => 'Sarpras contoh', 'components' => ['Sarpras'], 'max_bps' => 2000],
                    ],
                ],
                'metadata' => [
                    'source_type' => 'demo',
                    'warning' => 'Data contoh internal untuk preview UI, bukan referensi resmi ARKAS.',
                    'generated_by' => 'RkasDemoSeeder',
                ],
                'imported_by' => $admin->uuid,
                'is_active' => true,
            ]
        );

        $references = [
            ['DEMO-05.02.02', 'Buku teks dan referensi', 'Buku'],
            ['DEMO-07.12.01', 'Honor kegiatan pendidikan', 'Honor'],
            ['DEMO-05.08.01', 'Pemeliharaan sarana sekolah', 'Sarpras'],
            ['DEMO-06.05.13', 'Pengelolaan sekolah', 'Pengelolaan'],
            ['DEMO-03.01.01', 'Kegiatan pembelajaran dan asesmen', 'Pembelajaran'],
            ['DEMO-04.02.01', 'Kegiatan kesiswaan dan karakter', 'Kesiswaan'],
            ['DEMO-08.01.01', 'Digitalisasi administrasi sekolah', 'Teknologi'],
        ];

        $referenceModels = [];
        foreach ($references as [$code, $description, $component]) {
            $referenceModels[$code] = $referenceSet->references()->updateOrCreate(
                ['kode_kegiatan' => $code],
                [
                    'uraian_kegiatan' => $description,
                    'komponen' => $component,
                    'snp' => 'Contoh internal',
                    'kode_rekening_belanja' => null,
                ]
            );
        }

        $plan = RkasPlan::query()
            ->where('nama_sekolah', '[CONTOH INTERNAL] '.$schoolName)
            ->where('tahun_anggaran', 2026)
            ->where('sumber_dana', 'BOSP Reguler')
            ->where('reference_set_uuid', $referenceSet->uuid)
            ->first();

        DB::transaction(function () use (&$plan, $admin, $schoolName, $npsn, $referenceSet, $referenceModels): void {
            $plan ??= new RkasPlan();
            $plan->fill([
                'npsn' => $npsn,
                'nama_sekolah' => '[CONTOH INTERNAL] '.$schoolName,
                'tahun_anggaran' => 2026,
                'jenjang' => 'Dikdasmen',
                'sumber_dana' => 'BOSP Reguler',
                'reference_set_uuid' => $referenceSet->uuid,
                'pagu' => 120000000,
                'status' => RkasPlan::STATUS_DRAFT,
                'validated_at' => null,
                'created_by' => $plan->created_by ?: $admin->uuid,
                'updated_by' => $admin->uuid,
            ]);
            $plan->save();

            $plan->items()->delete();
            $items = [
                ['DEMO-05.02.02', 'Pengadaan buku teks dan referensi perpustakaan', 1, 1, 'paket', 15000000],
                ['DEMO-07.12.01', 'Honor narasumber dan pendamping kegiatan pendidikan', 2, 1, 'paket', 20000000],
                ['DEMO-05.08.01', 'Pemeliharaan ringan ruang kelas dan fasilitas belajar', 3, 1, 'paket', 20000000],
                ['DEMO-06.05.13', 'Administrasi, rapat, dan pengelolaan satuan pendidikan', 4, 1, 'paket', 10000000],
                ['DEMO-03.01.01', 'Bahan ajar dan kegiatan asesmen pembelajaran', 5, 1, 'paket', 25000000],
                ['DEMO-04.02.01', 'Kegiatan karakter, literasi, dan kesiswaan', 7, 1, 'paket', 10000000],
                ['DEMO-08.01.01', 'Perangkat lunak dan dukungan digitalisasi administrasi', 8, 1, 'paket', 20000000],
            ];

            foreach ($items as [$code, $description, $month, $quantity, $unit, $unitPrice]) {
                $reference = $referenceModels[$code];
                $plan->items()->create([
                    'reference_uuid' => $reference->uuid,
                    'kode_kegiatan' => $reference->kode_kegiatan,
                    'komponen' => $reference->komponen,
                    'penjelasan_implementasi' => 'Data contoh untuk mempreview alur RKAS SIMS.',
                    'uraian_belanja' => $description,
                    'bulan_dianggarkan' => $month,
                    'jumlah' => $quantity,
                    'satuan' => $unit,
                    'harga_satuan' => $unitPrice,
                    'total' => $quantity * $unitPrice,
                    'kode_rekening_belanja' => null,
                ]);
            }
        });

        $plan->load(['referenceSet', 'items.reference']);
        $findings = app(RkasValidationService::class)->inspect($plan);
        $plan->validations()->delete();
        foreach ($findings as $finding) {
            $plan->validations()->create($finding);
        }
        $hasErrors = $findings->contains(fn (array $finding) => $finding['severity'] === 'error');
        $plan->update([
            'status' => $hasErrors ? RkasPlan::STATUS_DRAFT : RkasPlan::STATUS_VALIDATED,
            'validated_at' => $hasErrors ? null : now(),
            'updated_by' => $admin->uuid,
        ]);

        $this->command?->info('Demo RKAS berhasil dibuat/diperbarui: '.$plan->nama_sekolah);
        $this->command?->line('Total rencana: Rp '.number_format($plan->totalPlanned(), 0, ',', '.'));
        $this->command?->line('Temuan error: '.$findings->where('severity', 'error')->count());
    }
}
