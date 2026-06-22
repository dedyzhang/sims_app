<?php

namespace App\Http\Controllers;

use App\Models\Agenda;
use App\Models\AgendaAbsensi;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Semester;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgendaController extends Controller
{
    /** Guru wajib punya profil; admin tanpa profil hanya boleh rekap. */
    private function guru(): ?Guru
    {
        return Auth::user()->guru;
    }

    private function semester(): int
    {
        return (int) (Semester::aktif()?->semester ?? 1);
    }

    /** Akses rekap: admin, kepala sekolah, kurikulum. */
    private function bisaRekap(): bool
    {
        return Auth::user()->isAdmin()
            || in_array(Auth::user()->access, ['kepala', 'kurikulum'], true);
    }

    /**
     * Slot jadwal yang diajar guru pada satu tanggal (1 baris per kelas+mapel),
     * lengkap dengan status apakah agendanya sudah diisi.
     */
    private function slotHari(Guru $guru, string $tanggal): array
    {
        $hariKe = (int) date('N', strtotime($tanggal));   // 1=Senin..7=Minggu
        if ($hariKe > 6) {
            return [];
        }

        $jadwals = Jadwal::with(['kelas', 'pelajaran', 'jam'])
            ->where('id_guru', $guru->uuid)
            ->where('hari', $hariKe)
            ->whereNotNull('id_pelajaran')
            ->get()
            ->sortBy('jam_mulai');

        // Agenda yang sudah ada pada tanggal ini (key: id_kelas|id_pelajaran)
        $sudah = Agenda::where('id_guru', $guru->uuid)
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->keyBy(fn ($a) => $a->id_kelas . '|' . $a->id_pelajaran);

        // Gabungkan jam berurutan dari kelas+mapel yang sama jadi satu slot agenda
        $grup = [];
        foreach ($jadwals as $j) {
            $key = $j->id_kelas . '|' . $j->id_pelajaran;
            if (!isset($grup[$key])) {
                $grup[$key] = [
                    'id_jadwal'    => $j->uuid,           // perwakilan
                    'id_kelas'     => $j->id_kelas,
                    'id_pelajaran' => $j->id_pelajaran,
                    'kelas'        => $j->kelas ? $j->kelas->tingkat . $j->kelas->kelas : '-',
                    'pelajaran'    => $j->pelajaran?->nama ?? '-',
                    'kode'         => $j->pelajaran?->kode,
                    'jam_mulai'    => substr((string) $j->jam_mulai, 0, 5),
                    'jam_selesai'  => substr((string) $j->jam_selesai, 0, 5),
                    'agenda'       => $sudah->get($key),
                ];
            } else {
                // perluas rentang jam
                $grup[$key]['jam_selesai'] = substr((string) $j->jam_selesai, 0, 5);
            }
        }

        return array_values($grup);
    }

    /** Halaman utama: satu daftar jam mengajar (N hari terakhir) + status pengisian agenda. */
    public function index(Request $request)
    {
        $guru = $this->guru();

        $hari = (int) ($request->hari ?: 14);
        if (!in_array($hari, [7, 14, 30], true)) $hari = 14;

        // Tanggal spesifik (opsional). Bila diisi → tampilkan hanya hari itu.
        $tanggal = $request->tanggal && strtotime($request->tanggal) ? $request->tanggal : null;

        if ($guru && $tanggal) {
            $daftar = $this->daftarTanggal($guru, $tanggal);
        } else {
            $daftar = $guru ? $this->riwayatJadwal($guru, $hari) : [];
        }
        $belum = collect($daftar)->filter(fn ($d) => !$d['agenda'])->count();
        $mengajar = $guru ? Jadwal::where('id_guru', $guru->uuid)->whereNotNull('id_pelajaran')->exists() : false;

        return view('agenda.index', compact('guru', 'daftar', 'belum', 'mengajar', 'hari', 'tanggal'));
    }

    /** Slot terjadwal pada satu tanggal tertentu, beserta agendanya (jika ada). */
    private function daftarTanggal(Guru $guru, string $tanggal): array
    {
        $tgl = \Carbon\Carbon::parse($tanggal);
        $list = [];
        foreach ($this->slotHari($guru, $tanggal) as $s) {
            $list[] = [
                'tanggal'       => $tanggal,
                'tanggal_label' => $tgl->locale('id')->isoFormat('ddd, D MMM'),
                'id_jadwal'     => $s['id_jadwal'],
                'kelas'         => $s['kelas'],
                'pelajaran'     => $s['pelajaran'],
                'jam_mulai'     => $s['jam_mulai'],
                'jam_selesai'   => $s['jam_selesai'],
                'hari_ini'      => $tanggal === now()->toDateString(),
                'agenda'        => $s['agenda'],
            ];
        }
        usort($list, fn ($a, $b) => strcmp($a['jam_mulai'], $b['jam_mulai']));

        return $list;
    }

    /** Semua slot terjadwal dalam $hari hari terakhir (s/d hari ini), beserta agendanya (jika ada). */
    private function riwayatJadwal(Guru $guru, int $hari): array
    {
        $list = [];
        $end = now()->startOfDay();
        $start = (clone $end)->subDays($hari - 1);

        for ($d = clone $start; $d <= $end; $d->addDay()) {
            $tgl = $d->toDateString();
            foreach ($this->slotHari($guru, $tgl) as $s) {
                $list[] = [
                    'tanggal'       => $tgl,
                    'tanggal_label' => $d->locale('id')->isoFormat('ddd, D MMM'),
                    'id_jadwal'     => $s['id_jadwal'],
                    'kelas'         => $s['kelas'],
                    'pelajaran'     => $s['pelajaran'],
                    'jam_mulai'     => $s['jam_mulai'],
                    'jam_selesai'   => $s['jam_selesai'],
                    'hari_ini'      => $tgl === now()->toDateString(),
                    'agenda'        => $s['agenda'],   // model Agenda atau null
                ];
            }
        }
        // terbaru dulu
        usort($list, fn ($a, $b) => strcmp($b['tanggal'] . $b['jam_mulai'], $a['tanggal'] . $a['jam_mulai']));

        return $list;
    }

    /** AJAX: daftar slot jadwal untuk tanggal terpilih (form pengisian). */
    public function slots(Request $request)
    {
        $guru = $this->guru();
        if (!$guru) {
            return response()->json(['success' => false, 'message' => 'Akun ini tidak memiliki profil guru.']);
        }
        $tanggal = $request->tanggal;
        if (!$tanggal || strtotime($tanggal) === false) {
            return response()->json(['success' => false, 'message' => 'Tanggal tidak valid.']);
        }

        $slots = $this->slotHari($guru, $tanggal);
        if (empty($slots)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada jadwal mengajar pada tanggal ini.']);
        }
        // hanya kirim yang belum diisi (untuk dipilih)
        $payload = collect($slots)->map(fn ($s) => [
            'id_jadwal'  => $s['id_jadwal'],
            'label'      => $s['kelas'] . ' — ' . $s['pelajaran'] . ' (' . $s['jam_mulai'] . '–' . $s['jam_selesai'] . ')',
            'terisi'     => (bool) $s['agenda'],
        ])->values();

        return response()->json(['success' => true, 'slots' => $payload]);
    }

    /** AJAX: daftar siswa pada kelas dari slot jadwal terpilih. */
    public function siswa(Request $request)
    {
        $jadwal = Jadwal::find($request->id_jadwal);
        if (!$jadwal) {
            return response()->json(['success' => false, 'message' => 'Jadwal tidak ditemukan.']);
        }
        $siswa = Siswa::where('id_kelas', $jadwal->id_kelas)
            ->orderBy('nama')
            ->get(['uuid', 'nama', 'nis']);

        return response()->json(['success' => true, 'siswa' => $siswa]);
    }

    /** Form tambah agenda (opsional preset ?jadwal= & ?tanggal=). */
    public function create(Request $request)
    {
        $guru = $this->guru();
        if (!$guru) {
            return redirect()->route('agenda.index')
                ->with('error', 'Akun ini tidak memiliki profil guru sehingga tidak bisa mengisi agenda.');
        }
        $presetTanggal = $request->tanggal ?: now()->toDateString();
        $presetJadwal = $request->jadwal;

        return view('agenda.create', compact('presetTanggal', 'presetJadwal'));
    }

    /** Simpan agenda baru beserta catatan ketidakhadiran siswa. */
    public function store(Request $request)
    {
        $guru = $this->guru();
        if (!$guru) {
            return response()->json(['success' => false, 'message' => 'Akun ini tidak memiliki profil guru.']);
        }

        $data = $request->validate([
            'tanggal'    => 'required|date',
            'jadwal'     => 'required|exists:jadwals,uuid',
            'pembahasan' => 'required|string',
            'metode'     => 'required|string',
            'proses'     => 'required|in:belum,selesai',
            'kegiatan'   => 'required|string',
            'kendala'    => 'required|string',
        ]);

        $jadwal = Jadwal::findOrFail($data['jadwal']);

        // Pastikan slot ini memang milik guru bersangkutan.
        if ($jadwal->id_guru !== $guru->uuid) {
            return response()->json(['success' => false, 'message' => 'Jadwal ini bukan jadwal mengajar Anda.']);
        }

        // Cegah duplikat: 1 agenda per tanggal + kelas + mapel.
        $duplikat = Agenda::where('id_guru', $guru->uuid)
            ->whereDate('tanggal', $data['tanggal'])
            ->where('id_kelas', $jadwal->id_kelas)
            ->where('id_pelajaran', $jadwal->id_pelajaran)
            ->exists();
        if ($duplikat) {
            return response()->json(['success' => false, 'message' => 'Agenda untuk kelas & mata pelajaran ini pada tanggal tersebut sudah diisi.']);
        }

        $semester = $this->semester();

        $agenda = Agenda::create([
            'tanggal'      => $data['tanggal'],
            'id_jadwal'    => $jadwal->uuid,
            'id_guru'      => $guru->uuid,
            'id_kelas'     => $jadwal->id_kelas,
            'id_pelajaran' => $jadwal->id_pelajaran,
            'pembahasan'   => $data['pembahasan'],
            'metode'       => $data['metode'],
            'proses'       => $data['proses'],
            'kegiatan'     => $data['kegiatan'],
            'kendala'      => $data['kendala'],
            'validasi'     => 'belum',
            'semester'     => $semester,
        ]);

        // Absensi (S/I/A)
        foreach ($this->decodeArray($request->absensi) as $el) {
            if (empty($el->siswa) || empty($el->absensi)) continue;
            AgendaAbsensi::create([
                'id_agenda'  => $agenda->uuid,
                'id_siswa'   => $el->siswa,
                'absensi'    => $el->absensi,
                'keterangan' => $el->keterangan ?? null,
            ]);
        }

        return response()->json(['success' => true]);
    }

    private function decodeArray($json): array
    {
        if (empty($json)) return [];
        $arr = is_string($json) ? json_decode($json) : $json;
        return is_array($arr) ? $arr : [];
    }

    /** Edit agenda milik guru. */
    public function edit(Agenda $agenda)
    {
        $this->pastikanMilikSendiri($agenda);
        $agenda->load(['absensi.siswa', 'jadwal.kelas', 'jadwal.pelajaran', 'kelas', 'pelajaran']);
        $siswaKelas = Siswa::where('id_kelas', $agenda->id_kelas)->orderBy('nama')->get(['uuid', 'nama', 'nis']);

        return view('agenda.edit', compact('agenda', 'siswaKelas'));
    }

    /** Update isi agenda (bagian B) + sinkron ketidakhadiran. */
    public function update(Request $request, Agenda $agenda)
    {
        $this->pastikanMilikSendiri($agenda);

        $data = $request->validate([
            'pembahasan' => 'required|string',
            'metode'     => 'required|string',
            'proses'     => 'required|in:belum,selesai',
            'kegiatan'   => 'required|string',
            'kendala'    => 'required|string',
        ]);
        $agenda->update($data);

        // Sinkron ulang absensi (kirim ulang seluruh daftar dari form)
        if ($request->has('absensi')) {
            $agenda->absensi()->delete();
            foreach ($this->decodeArray($request->absensi) as $el) {
                if (empty($el->siswa) || empty($el->absensi)) continue;
                AgendaAbsensi::create([
                    'id_agenda'  => $agenda->uuid,
                    'id_siswa'   => $el->siswa,
                    'absensi'    => $el->absensi,
                    'keterangan' => $el->keterangan ?? null,
                ]);
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('agenda.index', ['tanggal' => $agenda->tanggal->toDateString()])
            ->with('success', 'Agenda diperbarui.');
    }

    public function destroy(Agenda $agenda)
    {
        $this->pastikanMilikSendiri($agenda);
        $tanggal = $agenda->tanggal->toDateString();
        $agenda->absensi()->delete();
        $agenda->delete();

        return redirect()->route('agenda.index', ['tanggal' => $tanggal])->with('success', 'Agenda dihapus.');
    }

    private function pastikanMilikSendiri(Agenda $agenda): void
    {
        $guru = $this->guru();
        abort_unless($guru && $agenda->id_guru === $guru->uuid, 403, 'Anda hanya dapat mengubah agenda Anda sendiri.');
    }

    // ─────────────── Rekap (Admin / Kepala Sekolah / Kurikulum) ───────────────

    public function rekap(Request $request)
    {
        abort_unless($this->bisaRekap(), 403);

        $guruList = Guru::orderBy('nama')->get(['uuid', 'nama']);
        $selectedGuru = $request->guru ?: optional($guruList->first())->uuid;

        $dari   = $request->dari   ?: now()->startOfMonth()->toDateString();
        $sampai = $request->sampai ?: now()->toDateString();
        if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];

        $agendas = collect();
        if ($selectedGuru) {
            $agendas = Agenda::with(['absensi.siswa', 'kelas', 'pelajaran', 'jadwal.jam'])
                ->where('id_guru', $selectedGuru)
                ->whereDate('tanggal', '>=', $dari)
                ->whereDate('tanggal', '<=', $sampai)
                ->orderBy('tanggal')
                ->get()
                ->sortBy(fn ($a) => $a->tanggal->toDateString() . ' ' . ($a->jadwal?->jam_mulai ?? ''))
                ->values();
        }

        return view('agenda.rekap', compact('guruList', 'selectedGuru', 'dari', 'sampai', 'agendas'));
    }

    /** Validasi/catatan kepala sekolah pada satu agenda. */
    public function validasi(Request $request, Agenda $agenda)
    {
        abort_unless($this->bisaRekap(), 403);
        $data = $request->validate([
            'validasi'       => 'required|in:belum,valid',
            'catatan_kepsek' => 'nullable|string',
        ]);
        $agenda->update($data);

        return back()->with('success', 'Validasi agenda disimpan.');
    }
}
