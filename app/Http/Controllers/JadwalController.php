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
        $jamList   = JamPelajaran::where('hari', $hari)->orderBy('urutan')->orderBy('jam_mulai')->get();
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

        // Peta penugasan: kelas → pelajaran → guru (dari profil guru / Ngajar)
        $ngajars = Ngajar::with(['pelajaran', 'guru'])
            ->whereNotNull('id_guru')->whereNotNull('id_pelajaran')->get();
        $ngajarMap = [];
        foreach ($kelasList as $k) {
            foreach ($ngajars as $ng) {
                if ($ng->id_kelas === $k->uuid || empty($ng->id_kelas)) {
                    if (!isset($ngajarMap[$k->uuid][$ng->id_pelajaran])) {
                        $ngajarMap[$k->uuid][$ng->id_pelajaran] = [
                            'g'  => $ng->id_guru,
                            'gn' => $ng->guru?->nama ?? '',
                            'pn' => $ng->pelajaran?->nama ?? '',
                            'pk' => $ng->pelajaran?->kode ?? '',
                        ];
                    }
                }
            }
        }

        return view('jadwal.index', compact('hari', 'kelasList', 'jamList', 'pelajarans', 'gurus', 'cells', 'bentrok', 'ngajarMap'));
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

        // Kelas yg sedang istirahat/khusus pada jam ini tidak boleh diisi pelajaran — pengaman
        // di server krn grid front-end memang sudah tidak merender input utk sel semacam ini,
        // tapi endpoint ini tetap harus menolak kalau dipanggil langsung.
        if ($jam && $jam->isKhususUntukKelas($data['id_kelas'])) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas ini sedang ' . strtolower($jam->nama_khusus) . ' pada jam ini.',
            ], 422);
        }

        Jadwal::updateOrCreate(
            ['id_kelas' => $data['id_kelas'], 'hari' => $data['hari'], 'id_jam' => $data['id_jam']],
            [
                'jam_ke'       => $jam?->jam_ke,
                'jam_mulai'    => $jam?->jam_mulai,
                'jam_selesai'  => $jam?->jam_selesai,
                'id_pelajaran' => $data['id_pelajaran'] ?? null,
                'id_guru'      => $data['id_guru'] ?? null,
                'keterangan'   => $data['keterangan'] ?? null,
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
        $hariList  = array_keys(Jadwal::HARI);
        $jpMap     = Pelajaran::pluck('jp', 'uuid');

        // Semua jam PER HARI (urut) — termasuk jenis!=pelajaran, krn jam khusus yg di-scope ke
        // kelas TERTENTU (istirahat bergilir) tetap jadi slot pelajaran biasa bagi kelas lain
        // yg tidak termasuk cakupannya. Filter per-jenis "pelajaran" dilakukan PER KELAS di bawah.
        $allByDay = [];
        foreach ($hariList as $h) {
            $allByDay[$h] = JamPelajaran::where('hari', $h)->orderBy('urutan')->orderBy('jam_mulai')->get()->values();
        }
        $totalSlots = array_sum(array_map(fn($c) => $c->where('jenis', 'pelajaran')->count(), $allByDay));

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
        if ($totalSlots === 0) {
            return back()->with('error', 'Belum ada jam pelajaran. Klik "Atur Jam" untuk menambah jam dulu.');
        }

        DB::transaction(function () use ($mode, $kelasList, $allByDay, $hariList, $byKelas) {
            if ($mode === 'timpa') Jadwal::truncate();

            // busy berbasis uuid jam (uuid sudah unik per hari)
            $classBusy = []; // "kelas|jamUuid"
            $guruBusy  = []; // "jamUuid|guru"
            foreach (Jadwal::whereNotNull('id_jam')->get(['id_kelas', 'id_jam', 'id_guru']) as $j) {
                $classBusy[$j->id_kelas . '|' . $j->id_jam] = true;
                if ($j->id_guru) $guruBusy[$j->id_jam . '|' . $j->id_guru] = true;
            }

            $insert = [];
            $mk = function ($k, $hari, $slot, $subj) {
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

                // Slot yg boleh diisi pelajaran UTK KELAS INI: jenis pelajaran, atau jam khusus
                // yg cakupannya tidak menyertakan kelas ini (istirahat kelas lain, bukan kelas ini).
                $slotsByDay = [];
                foreach ($hariList as $h) {
                    $slotsByDay[$h] = $allByDay[$h]->filter(fn($jam) => !$jam->isKhususUntukKelas($k->uuid))->values();
                }

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
                                $slots = $slotsByDay[$hari];
                                $n = $slots->count();
                                for ($start = 0; $start + $B <= $n; $start++) {
                                    $fit = true;
                                    for ($o = 0; $o < $B; $o++) {
                                        $u = $slots[$start + $o]->uuid;
                                        if (isset($classBusy[$k->uuid . '|' . $u]) || isset($guruBusy[$u . '|' . $subj['g']])) { $fit = false; break; }
                                    }
                                    if ($fit) {
                                        for ($o = 0; $o < $B; $o++) {
                                            $slot = $slots[$start + $o];
                                            $insert[] = $mk($k->uuid, $hari, $slot, $subj);
                                            $classBusy[$k->uuid . '|' . $slot->uuid] = true;
                                            $guruBusy[$slot->uuid . '|' . $subj['g']] = true;
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
                                $done = false;
                                foreach ($dayOrder as $hari) {
                                    foreach ($slotsByDay[$hari] as $slot) {
                                        if (!isset($classBusy[$k->uuid . '|' . $slot->uuid]) && !isset($guruBusy[$slot->uuid . '|' . $subj['g']])) {
                                            $insert[] = $mk($k->uuid, $hari, $slot, $subj);
                                            $classBusy[$k->uuid . '|' . $slot->uuid] = true;
                                            $guruBusy[$slot->uuid . '|' . $subj['g']] = true;
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

        return back()->with('success', 'Jadwal di-generate: tiap mapel ditempatkan sebagai blok jam berurutan sesuai JP/minggu, mengikuti jam tiap hari & menghindari bentrok guru.');
    }

    // ===== Master Jam Pelajaran (PER HARI) =====
    public function jamStore(Request $request)
    {
        $data = $request->validate([
            'hari'          => 'required|integer|between:1,6',
            'jam_ke'        => 'nullable|integer|min:0',
            'jam_mulai'     => 'required|date_format:H:i',
            'jam_selesai'   => 'required|date_format:H:i|after:jam_mulai',
            'jenis'         => 'required|in:' . implode(',', array_keys(JamPelajaran::JENIS)),
            'label'         => 'nullable|string|max:30',
            'kelas_scope'   => 'nullable|array',
            'kelas_scope.*' => 'string|exists:kelas,uuid',
        ]);
        if ($data['jenis'] !== 'pelajaran') {
            $data['jam_ke'] = null; // jam khusus tak punya "jam ke-"
        } else {
            $data['kelas_scope'] = null; // cakupan kelas cuma relevan utk jam khusus, bukan pelajaran
        }
        if (empty($data['kelas_scope'])) {
            $data['kelas_scope'] = null; // kosong = berlaku semua kelas
        }
        // urutan otomatis: berdasarkan jam mulai dalam hari tsb
        $data['urutan'] = (JamPelajaran::where('hari', $data['hari'])->max('urutan') ?? 0) + 1;
        JamPelajaran::create($data);

        $this->resequence($data['hari']);
        return redirect()->route('jadwal.index', ['hari' => $data['hari']])->with('success', 'Jam ditambahkan ke ' . (Jadwal::HARI[$data['hari']] ?? '') . '.');
    }

    public function jamDestroy(string $uuid)
    {
        $jam = JamPelajaran::findOrFail($uuid);
        $hari = $jam->hari;
        // bersihkan jadwal yang menunjuk jam ini agar tak jadi orphan
        Jadwal::where('id_jam', $uuid)->delete();
        $jam->delete();
        return redirect()->route('jadwal.index', ['hari' => $hari])->with('success', 'Jam dihapus.');
    }

    /** Salin susunan jam satu hari ke hari lain (mereset jam & jadwal hari tujuan) */
    public function jamCopy(Request $request)
    {
        $data = $request->validate([
            'from_hari' => 'required|integer|between:1,6',
            'to'        => 'required|array|min:1',
            'to.*'      => 'integer|between:1,6',
        ]);
        $sumber = JamPelajaran::where('hari', $data['from_hari'])->orderBy('urutan')->get();
        if ($sumber->isEmpty()) {
            return back()->with('error', 'Hari sumber belum punya jam.');
        }

        $tujuan = array_values(array_unique(array_diff($data['to'], [$data['from_hari']])));
        DB::transaction(function () use ($sumber, $tujuan) {
            foreach ($tujuan as $hari) {
                // reset jam & jadwal hari tujuan
                $oldIds = JamPelajaran::where('hari', $hari)->pluck('uuid');
                Jadwal::whereIn('id_jam', $oldIds)->delete();
                JamPelajaran::where('hari', $hari)->delete();
                // salin
                foreach ($sumber as $s) {
                    JamPelajaran::create([
                        'hari' => $hari, 'jam_ke' => $s->jam_ke,
                        'jam_mulai' => $s->jam_mulai, 'jam_selesai' => $s->jam_selesai,
                        'jenis' => $s->jenis, 'label' => $s->label, 'urutan' => $s->urutan,
                        'kelas_scope' => $s->kelas_scope,
                    ]);
                }
            }
        });
        return redirect()->route('jadwal.index', ['hari' => $data['from_hari']])
            ->with('success', 'Susunan jam disalin ke ' . count($tujuan) . ' hari (jadwal hari tujuan direset).');
    }

    /** Rapikan urutan jam dalam satu hari mengikuti jam_mulai */
    private function resequence(int $hari): void
    {
        $jams = JamPelajaran::where('hari', $hari)->orderBy('jam_mulai')->orderBy('urutan')->get();
        foreach ($jams as $i => $j) {
            if ($j->urutan !== $i + 1) $j->update(['urutan' => $i + 1]);
        }
    }

    /** Tampilan jadwal per kelas (read-only mingguan) — sumbu waktu gabungan semua hari */
    public function kelasView(Request $request)
    {
        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $selectedKelas = $request->kelas ?: optional($kelasList->first())->uuid;

        // Baris = gabungan jam semua hari, unik per (mulai|selesai|jenis), urut jam mulai
        $rows = JamPelajaran::orderBy('jam_mulai')->orderBy('urutan')->get()
            ->unique(fn($j) => substr($j->jam_mulai, 0, 5) . '|' . substr($j->jam_selesai, 0, 5) . '|' . $j->jenis)
            ->sortBy('jam_mulai')->values();

        // cells: "H:i|hari" => jadwal (cocokkan berdasarkan jam mulai)
        $cells = [];
        if ($selectedKelas) {
            foreach (Jadwal::with(['pelajaran', 'guru'])->where('id_kelas', $selectedKelas)->get() as $j) {
                $key = \Carbon\Carbon::parse($j->jam_mulai)->format('H:i') . '|' . $j->hari;
                $cells[$key] = $j;
            }
        }
        return view('jadwal.kelas', compact('kelasList', 'selectedKelas', 'rows', 'cells'));
    }

    /** Tampilan jadwal per guru (read-only mingguan) — sumbu waktu gabungan semua hari */
    public function guruView(Request $request)
    {
        $guruList = Guru::orderBy('nama')->get();
        $selectedGuru = $request->guru ?: optional($guruList->first())->uuid;

        // Baris = gabungan jam semua hari, unik per (mulai|selesai|jenis), urut jam mulai
        $rows = JamPelajaran::orderBy('jam_mulai')->orderBy('urutan')->get()
            ->unique(fn($j) => substr($j->jam_mulai, 0, 5) . '|' . substr($j->jam_selesai, 0, 5) . '|' . $j->jenis)
            ->sortBy('jam_mulai')->values();

        // cells: "H:i|hari" => jadwal (cocokkan berdasarkan jam mulai)
        $cells = [];
        if ($selectedGuru) {
            foreach (Jadwal::with(['pelajaran', 'kelas'])->where('id_guru', $selectedGuru)->get() as $j) {
                $key = \Carbon\Carbon::parse($j->jam_mulai)->format('H:i') . '|' . $j->hari;
                $cells[$key] = $j;
            }
        }
        return view('jadwal.guru', compact('guruList', 'selectedGuru', 'rows', 'cells'));
    }
}
