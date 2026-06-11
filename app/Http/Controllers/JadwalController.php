<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JadwalController extends Controller
{
    /** Editor grid per hari (semua kelas sekaligus) */
    public function index(Request $request)
    {
        $hari = (int) ($request->hari ?: 1);
        if ($hari < 1 || $hari > 6) $hari = 1;

        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $jamList   = JamPelajaran::orderBy('urutan')->orderBy('jam_mulai')->get();
        $pelajarans = Pelajaran::orderBy('urutan')->orderBy('nama')->get();
        $gurus     = Guru::orderBy('nama')->get();

        // jadwal hari ini: map "id_jam|id_kelas" => jadwal
        $jadwals = Jadwal::with(['pelajaran', 'guru'])->where('hari', $hari)->get();
        $cells = [];
        foreach ($jadwals as $j) {
            $cells[$j->id_jam . '|' . $j->id_kelas] = $j;
        }

        // deteksi bentrok: guru yang sama pada jam yang sama di >1 kelas
        $conflicts = [];   // "id_jam|id_guru" => count
        foreach ($jadwals as $j) {
            if (!$j->id_guru || !$j->id_jam) continue;
            $key = $j->id_jam . '|' . $j->id_guru;
            $conflicts[$key] = ($conflicts[$key] ?? 0) + 1;
        }
        $bentrok = array_keys(array_filter($conflicts, fn($c) => $c > 1));

        return view('jadwal.index', compact('hari', 'kelasList', 'jamList', 'pelajarans', 'gurus', 'cells', 'bentrok'));
    }

    /** Simpan / update satu sel (AJAX) */
    public function saveCell(Request $request)
    {
        $data = $request->validate([
            'id_kelas'     => 'required|exists:kelas,uuid',
            'hari'         => 'required|integer|between:1,6',
            'id_jam'       => 'required|exists:jam_pelajaran,uuid',
            'id_pelajaran' => 'nullable|exists:pelajarans,uuid',
            'id_guru'      => 'nullable|exists:gurus,uuid',
            'keterangan'   => 'nullable|string|max:50',
        ]);

        $jam = JamPelajaran::find($data['id_jam']);

        Jadwal::updateOrCreate(
            ['id_kelas' => $data['id_kelas'], 'hari' => $data['hari'], 'id_jam' => $data['id_jam']],
            [
                'jam_ke'       => $jam?->jam_ke,
                'jam_mulai'    => $jam?->jam_mulai,
                'jam_selesai'  => $jam?->jam_selesai,
                'id_pelajaran' => $data['id_pelajaran'] ?: null,
                'id_guru'      => $data['id_guru'] ?: null,
                'keterangan'   => $data['keterangan'] ?: null,
            ]
        );

        return response()->json(['success' => true, 'bentrok' => $this->bentrokHari($data['hari'])]);
    }

    /** Kosongkan satu sel (AJAX) */
    public function clearCell(Request $request)
    {
        $data = $request->validate([
            'id_kelas' => 'required',
            'hari'     => 'required|integer|between:1,6',
            'id_jam'   => 'required',
        ]);
        Jadwal::where($data)->delete();
        return response()->json(['success' => true, 'bentrok' => $this->bentrokHari($data['hari'])]);
    }

    /** Hitung daftar bentrok untuk satu hari: ["id_jam|id_guru", ...] */
    private function bentrokHari(int $hari): array
    {
        $rows = Jadwal::where('hari', $hari)->whereNotNull('id_guru')->whereNotNull('id_jam')
            ->get(['id_jam', 'id_guru']);
        $count = [];
        foreach ($rows as $r) {
            $k = $r->id_jam . '|' . $r->id_guru;
            $count[$k] = ($count[$k] ?? 0) + 1;
        }
        return array_keys(array_filter($count, fn($c) => $c > 1));
    }

    /** Halaman set JP (jam/minggu) per pelajaran — 1 tabel */
    public function jpForm()
    {
        $pelajarans = Pelajaran::orderBy('urutan')->orderBy('nama')->get();
        return view('jadwal.jp', compact('pelajarans'));
    }

    public function jpSave(Request $request)
    {
        $request->validate(['jp' => 'required|array']);
        foreach ($request->jp as $uuid => $val) {
            Pelajaran::where('uuid', $uuid)->update(['jp' => max(0, min(40, (int) $val))]);
        }
        return back()->with('success', 'Jam pelajaran per minggu disimpan.');
    }

    /** Bagi N jam jadi blok berurutan rata (blok 2, dan satu blok 3 jika ganjil). */
    private function splitBlocks(int $n): array
    {
        if ($n <= 0) return [];
        if ($n <= 3) return [$n];
        $blocks = [];
        while ($n > 0) {
            if ($n % 2 === 0) { $blocks[] = 2; $n -= 2; }
            else { $blocks[] = 3; $n -= 3; }   // hanya satu blok ganjil di awal
        }
        return $blocks;
    }

    private function rotateArr(array $arr, int $off): array
    {
        $n = count($arr);
        if ($n === 0) return $arr;
        $off = (($off % $n) + $n) % $n;
        return array_merge(array_slice($arr, $off), array_slice($arr, 0, $off));
    }

    /**
     * Auto-generate: tiap mapel ditempatkan sebagai BLOK JAM BERURUTAN sesuai JP/minggu,
     * blok-blok sebuah mapel disebar ke hari berbeda, tanpa bentrok guru.
     */
    public function generate(Request $request)
    {
        $mode = $request->mode ?? 'isi_kosong'; // isi_kosong | timpa

        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $jamSlots  = JamPelajaran::where('jenis', 'pelajaran')->orderBy('urutan')->orderBy('jam_mulai')->get()->values();
        $hariList  = array_keys(Jadwal::HARI);
        $jpMap     = Pelajaran::pluck('jp', 'uuid');

        // Penugasan mengajar per kelas: [kelas => [['p','g','jp'], ...]]
        $ngajars = Ngajar::whereNotNull('id_guru')->whereNotNull('id_pelajaran')->get();
        $byKelas = [];
        foreach ($kelasList as $k) {
            $list = [];
            foreach ($ngajars as $ng) {
                if ($ng->id_kelas === $k->uuid || empty($ng->id_kelas)) {
                    $list[] = ['p' => $ng->id_pelajaran, 'g' => $ng->id_guru, 'jp' => (int) ($jpMap[$ng->id_pelajaran] ?? 2)];
                }
            }
            $byKelas[$k->uuid] = $list;
        }

        if (collect($byKelas)->flatten(1)->isEmpty()) {
            return back()->with('error', 'Belum ada penugasan mengajar. Atur "Pelajaran Diajar" tiap guru dulu, lalu generate.');
        }
        if ($jamSlots->isEmpty()) {
            return back()->with('error', 'Belum ada jam pelajaran. Klik "Atur Jam" untuk menambah jam dulu.');
        }

        DB::transaction(function () use ($mode, $kelasList, $jamSlots, $hariList, $byKelas) {
            if ($mode === 'timpa') Jadwal::truncate();

            $nSlots = $jamSlots->count();
            $slotIndex = [];
            foreach ($jamSlots as $i => $s) $slotIndex[$s->uuid] = $i;

            $classBusy = []; // "kelas|hari|idx"
            $guruBusy  = []; // "hari|idx|guru"
            foreach (Jadwal::whereNotNull('id_jam')->get(['id_kelas', 'hari', 'id_jam', 'id_guru']) as $j) {
                $idx = $slotIndex[$j->id_jam] ?? null;
                if ($idx === null) continue;
                $classBusy[$j->id_kelas . '|' . $j->hari . '|' . $idx] = true;
                if ($j->id_guru) $guruBusy[$j->hari . '|' . $idx . '|' . $j->id_guru] = true;
            }

            $insert = [];
            $mk = function ($k, $hari, $idx, $subj) use ($jamSlots) {
                $slot = $jamSlots[$idx];
                return [
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'id_kelas' => $k, 'hari' => $hari, 'id_jam' => $slot->uuid,
                    'jam_ke' => $slot->jam_ke, 'jam_mulai' => $slot->jam_mulai, 'jam_selesai' => $slot->jam_selesai,
                    'id_pelajaran' => $subj['p'], 'id_guru' => $subj['g'],
                    'created_at' => now(), 'updated_at' => now(),
                ];
            };

            $kelasIdx = 0;
            foreach ($kelasList as $k) {
                $subjects = $byKelas[$k->uuid] ?? [];
                if (empty($subjects)) { $kelasIdx++; continue; }
                usort($subjects, fn($a, $b) => $b['jp'] <=> $a['jp']); // mapel JP besar dulu
                $dayStart = $kelasIdx % count($hariList);

                foreach ($subjects as $si => $subj) {
                    if ($subj['jp'] <= 0) continue;
                    $blocks = $this->splitBlocks($subj['jp']);
                    $dayOrder = $this->rotateArr($hariList, $dayStart + $si);
                    $usedDays = [];

                    foreach ($blocks as $B) {
                        $placed = false;
                        // utamakan hari yang belum dipakai mapel ini (sebar), lalu boleh hari mana saja
                        foreach ([true, false] as $distinctDay) {
                            foreach ($dayOrder as $hari) {
                                if ($distinctDay && in_array($hari, $usedDays)) continue;
                                for ($start = 0; $start + $B <= $nSlots; $start++) {
                                    $fit = true;
                                    for ($o = 0; $o < $B; $o++) {
                                        $idx = $start + $o;
                                        if (isset($classBusy[$k->uuid . '|' . $hari . '|' . $idx]) || isset($guruBusy[$hari . '|' . $idx . '|' . $subj['g']])) { $fit = false; break; }
                                    }
                                    if ($fit) {
                                        for ($o = 0; $o < $B; $o++) {
                                            $idx = $start + $o;
                                            $insert[] = $mk($k->uuid, $hari, $idx, $subj);
                                            $classBusy[$k->uuid . '|' . $hari . '|' . $idx] = true;
                                            $guruBusy[$hari . '|' . $idx . '|' . $subj['g']] = true;
                                        }
                                        $usedDays[] = $hari; $placed = true; break;
                                    }
                                }
                                if ($placed) break;
                            }
                            if ($placed) break;
                        }
                        // fallback: blok tak muat utuh → isi per jam di slot kosong mana saja
                        if (!$placed) {
                            for ($r = 0; $r < $B; $r++) {
                                foreach ($dayOrder as $hari) {
                                    $done = false;
                                    for ($idx = 0; $idx < $nSlots; $idx++) {
                                        if (!isset($classBusy[$k->uuid . '|' . $hari . '|' . $idx]) && !isset($guruBusy[$hari . '|' . $idx . '|' . $subj['g']])) {
                                            $insert[] = $mk($k->uuid, $hari, $idx, $subj);
                                            $classBusy[$k->uuid . '|' . $hari . '|' . $idx] = true;
                                            $guruBusy[$hari . '|' . $idx . '|' . $subj['g']] = true;
                                            $done = true; break;
                                        }
                                    }
                                    if ($done) break;
                                }
                            }
                        }
                    }
                }
                $kelasIdx++;
            }

            foreach (array_chunk($insert, 200) as $chunk) Jadwal::insert($chunk);
        });

        return back()->with('success', 'Jadwal di-generate: tiap mapel ditempatkan sebagai blok jam berurutan sesuai JP/minggu, bentrok guru dihindari.');
    }

    // ===== Master Jam Pelajaran =====
    public function jamStore(Request $request)
    {
        $data = $request->validate([
            'jam_ke'      => 'nullable|integer|min:0',
            'jam_mulai'   => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'jenis'       => 'required|in:pelajaran,istirahat',
            'label'       => 'nullable|string|max:30',
        ]);
        $data['urutan'] = (JamPelajaran::max('urutan') ?? 0) + 1;
        JamPelajaran::create($data);
        return back()->with('success', 'Jam pelajaran ditambahkan.');
    }

    public function jamDestroy(string $uuid)
    {
        JamPelajaran::findOrFail($uuid)->delete();
        return back()->with('success', 'Jam pelajaran dihapus.');
    }

    /** Tampilan jadwal per kelas (read-only mingguan) */
    public function kelasView(Request $request)
    {
        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $selectedKelas = $request->kelas ?: optional($kelasList->first())->uuid;
        $jamList = JamPelajaran::orderBy('urutan')->orderBy('jam_mulai')->get();

        $cells = [];
        if ($selectedKelas) {
            foreach (Jadwal::with(['pelajaran', 'guru'])->where('id_kelas', $selectedKelas)->get() as $j) {
                $cells[$j->id_jam . '|' . $j->hari] = $j;
            }
        }
        return view('jadwal.kelas', compact('kelasList', 'selectedKelas', 'jamList', 'cells'));
    }
}
