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
            return $items->pluck('hari')->toArray();
        });

        // Format untuk Vue/Alpine: array of object { id, nama, hari: [1, 3, 5] }
        $rows = $guruList->map(function ($g) use ($jadwal) {
            return [
                'id' => $g->uuid,
                'nama' => $g->nama,
                'hari' => $jadwal->get($g->uuid) ?? [],
            ];
        });

        return view('piket.jadwal', compact('rows'));
    }

    public function simpanJadwal(Request $request)
    {
        $this->authorize('manage', JadwalPiket::class);

        $data = $request->validate([
            'jadwal' => ['required', 'array'],
            'jadwal.*.id' => ['required', 'string', 'exists:gurus,uuid'],
            'jadwal.*.hari' => ['present', 'array'],
            'jadwal.*.hari.*' => ['integer', 'min:1', 'max:7'],
        ]);

        DB::transaction(function () use ($data) {
            JadwalPiket::query()->truncate();

            $inserts = [];
            foreach ($data['jadwal'] as $row) {
                foreach ($row['hari'] as $hari) {
                    $inserts[] = [
                        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                        'id_guru' => $row['id'],
                        'hari' => $hari,
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
