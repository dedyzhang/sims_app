<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\JadwalPiket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PiketController extends Controller
{
    public function index()
    {
        $this->authorize('manage', JadwalPiket::class);

        $guruList = Guru::orderBy('nama')->get(['uuid', 'nama']);
        $jadwal = JadwalPiket::all()->groupBy('id_guru')->map(function ($items) {
            return $items->pluck('hari')->map(fn ($hari) => (int) $hari)->values()->all();
        });
        $ketua = JadwalPiket::where('is_ketua', true)->get(['hari', 'id_guru'])
            ->mapWithKeys(fn ($row) => [(int) $row->hari => $row->id_guru]);

        // Format untuk Vue/Alpine: array of object { id, nama, hari: [1, 3, 5] }
        $rows = $guruList->map(function ($g) use ($jadwal) {
            return [
                'id' => $g->uuid,
                'nama' => $g->nama,
                'hari' => $jadwal->get($g->uuid) ?? [],
            ];
        });

        return view('piket.jadwal', compact('rows', 'ketua'));
    }

    public function simpanJadwal(Request $request)
    {
        $this->authorize('manage', JadwalPiket::class);

        $data = $request->validate([
            'jadwal' => ['required', 'array'],
            'jadwal.*.id' => ['required', 'string', 'exists:gurus,uuid'],
            'jadwal.*.hari' => ['present', 'array'],
            'jadwal.*.hari.*' => ['integer', 'min:1', 'max:5'],
            'ketua' => ['required', 'array'],
            'ketua.1' => ['required', 'string', 'exists:gurus,uuid'],
            'ketua.2' => ['required', 'string', 'exists:gurus,uuid'],
            'ketua.3' => ['required', 'string', 'exists:gurus,uuid'],
            'ketua.4' => ['required', 'string', 'exists:gurus,uuid'],
            'ketua.5' => ['required', 'string', 'exists:gurus,uuid'],
        ]);

        $guruPiketPerHari = collect(range(1, 5))->mapWithKeys(function (int $hari) use ($data) {
            return [$hari => collect($data['jadwal'])
                ->filter(fn (array $row) => in_array($hari, array_map('intval', $row['hari']), true))
                ->pluck('id')];
        });

        foreach (range(1, 5) as $hari) {
            abort_unless(
                $guruPiketPerHari->get($hari)->contains($data['ketua'][$hari] ?? null),
                422,
                "Ketua piket hari ke-{$hari} harus terdaftar sebagai guru piket pada hari tersebut."
            );
        }

        DB::transaction(function () use ($data) {
            // Keep the replacement atomic on MySQL; TRUNCATE can implicitly commit.
            JadwalPiket::query()->delete();

            $inserts = [];
            foreach ($data['jadwal'] as $row) {
                foreach ($row['hari'] as $hari) {
                    $inserts[] = [
                        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                        'id_guru' => $row['id'],
                        'hari' => $hari,
                        'is_ketua' => (string) ($data['ketua'][$hari] ?? '') === $row['id'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            
            // Insert in chunks to avoid query length limits
            foreach (array_chunk($inserts, 500) as $chunk) {
                JadwalPiket::insert($chunk);
            }
        });

        return response()->json(['message' => 'Jadwal piket berhasil disimpan.']);
    }
}
