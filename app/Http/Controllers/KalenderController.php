<?php

namespace App\Http\Controllers;

use App\Models\HariEfektif;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class KalenderController extends Controller
{
    /** Hanya admin & kurikulum yang boleh mengatur kalender. */
    private function guard(): void
    {
        $u = auth()->user();
        abort_unless($u->canAccess('manage_absensi'), 403);
    }

    public function index(Request $request)
    {
        $this->guard();

        $bulan = $request->bulan && preg_match('/^\d{4}-\d{2}$/', $request->bulan)
            ? $request->bulan : now()->format('Y-m');

        $awal = Carbon::parse($bulan . '-01')->startOfMonth();
        $gridAwal = $awal->copy()->startOfWeek(Carbon::MONDAY);
        $akhir = $awal->copy()->endOfMonth();
        $gridAkhir = $akhir->copy()->endOfWeek(Carbon::SUNDAY);

        $rows = HariEfektif::whereDate('tanggal', '>=', $gridAwal->toDateString())
            ->whereDate('tanggal', '<=', $gridAkhir->toDateString())
            ->get()->keyBy(fn ($r) => $r->tanggal->toDateString());

        $hari = [];
        $state = [];   // ymd => [absen_siswa, agenda_guru]  (untuk Alpine)
        for ($d = $gridAwal->copy(); $d <= $gridAkhir; $d->addDay()) {
            $ymd = $d->toDateString();
            $row = $rows->get($ymd);
            $hari[] = [
                'ymd'      => $ymd,
                'day'      => $d->day,
                'inMonth'  => $d->month === $awal->month,
                'weekend'  => $d->dayOfWeekIso >= 6,
                'hari_ini' => $ymd === now()->toDateString(),
            ];
            if ($d->month === $awal->month) {
                $state[$ymd] = [
                    'absen_siswa' => (bool) ($row?->absen_siswa),
                    'agenda_guru' => (bool) ($row?->agenda_guru),
                ];
            }
        }

        $minggu = array_chunk($hari, 7);

        $absenAktif  = Setting::get('kalender_absen_aktif', '0') === '1';
        $agendaAktif = Setting::get('kalender_agenda_aktif', '0') === '1';
        $label = $awal->locale('id')->isoFormat('MMMM Y');
        $prev = $awal->copy()->subMonth()->format('Y-m');
        $next = $awal->copy()->addMonth()->format('Y-m');

        return view('kalender.index', compact(
            'bulan', 'label', 'prev', 'next', 'minggu', 'state', 'absenAktif', 'agendaAktif'
        ));
    }

    /** AJAX: set satu field (absen_siswa | agenda_guru) untuk satu tanggal. */
    public function toggle(Request $request)
    {
        $this->guard();
        $data = $request->validate([
            'tanggal' => 'required|date',
            'field'   => 'required|in:absen_siswa,agenda_guru',
            'value'   => 'required|boolean',
        ]);

        $row = HariEfektif::firstOrNew(['tanggal' => $data['tanggal']]);
        $row->{$data['field']} = $data['value'];
        if (!$row->semester) {
            $row->semester = (int) (\App\Models\Semester::aktif()?->semester ?? 1);
        }
        $row->save();

        return response()->json(['ok' => true]);
    }

    /** Set satu field untuk seluruh hari (atau hari kerja) dalam satu bulan. */
    public function bulk(Request $request)
    {
        $this->guard();
        $data = $request->validate([
            'bulan'       => 'required|date_format:Y-m',
            'field'       => 'required|in:absen_siswa,agenda_guru',
            'value'       => 'required|boolean',
            'akhir_pekan' => 'nullable|boolean',
        ]);

        $awal = Carbon::parse($data['bulan'] . '-01')->startOfMonth();
        $akhir = $awal->copy()->endOfMonth();
        $sem = (int) (\App\Models\Semester::aktif()?->semester ?? 1);
        $n = 0;

        for ($d = $awal->copy(); $d <= $akhir; $d->addDay()) {
            if (empty($data['akhir_pekan']) && $d->dayOfWeekIso >= 6) {
                continue;   // lewati Sabtu/Minggu kecuali diminta
            }
            $row = HariEfektif::firstOrNew(['tanggal' => $d->toDateString()]);
            $row->{$data['field']} = $data['value'];
            if (!$row->semester) $row->semester = $sem;
            $row->save();
            $n++;
        }

        $f = $data['field'] === 'absen_siswa' ? 'Absen siswa' : 'Agenda guru';
        $st = $data['value'] ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "{$f} {$st} untuk {$n} hari pada " . $awal->locale('id')->isoFormat('MMMM Y') . '.');
    }

    /** Aktif/nonaktifkan penegakan kalender (master toggle). */
    public function mode(Request $request)
    {
        $this->guard();
        Setting::set('kalender_absen_aktif', $request->boolean('kalender_absen_aktif') ? '1' : '0');
        Setting::set('kalender_agenda_aktif', $request->boolean('kalender_agenda_aktif') ? '1' : '0');

        return back()->with('success', 'Pengaturan penegakan kalender disimpan.');
    }
}
