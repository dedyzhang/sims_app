<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\NilaiPas;
use App\Models\NilaiPts;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Support\RaporHitung;
use Illuminate\Http\Request;

class RekapController extends Controller
{
    public const JENIS = ['rapor' => 'Nilai Rapor', 'pts' => 'Nilai PTS', 'pas' => 'Nilai PAS'];

    public function nilai(Request $request)
    {
        $user = auth()->user();
        $bolehSemua = $user->canAccess('manage_rapor');

        // Walikelas: hanya kelasnya
        $wkKelas = null;
        if (!$bolehSemua) {
            $wk = $user->guru?->walikelas;
            abort_unless($wk, 403, 'Hanya pengelola dan wali kelas yang dapat melihat rekap nilai.');
            $wkKelas = $wk->id_kelas;
        }

        $kelasList = $bolehSemua
            ? Kelas::orderBy('tingkat')->orderBy('kelas')->get()
            : Kelas::where('uuid', $wkKelas)->get();

        $selKelas = $wkKelas ?: ($request->kelas ?: optional($kelasList->first())->uuid);
        $jenis = in_array($request->jenis, ['pas', 'pts', 'rapor'], true) ? $request->jenis : 'rapor';

        $sem = Semester::aktif() ?? Semester::first();
        $rumus = Setting::get('rumus_rapor', 'bagi4');

        $siswas = $selKelas ? Siswa::where('id_kelas', $selKelas)->orderBy('nama')->get() : collect();
        $ngajars = $selKelas
            ? Ngajar::with(['pelajaran', 'guru'])->where('id_kelas', $selKelas)->whereNotNull('id_pelajaran')->get()
                ->sortBy(fn ($n) => [$n->pelajaran?->urutan, $n->pelajaran?->nama])->values()
            : collect();

        // Matriks nilai [id_siswa][id_ngajar] => nilai (+ deskripsi rapor utk popup)
        $nilai = [];
        $desk = [];   // [id_siswa][id_ngajar] => ['pos','neg']  (hanya jenis rapor)
        foreach ($ngajars as $ng) {
            if ($jenis === 'rapor') {
                foreach (RaporHitung::olah($ng, $siswas, $sem?->id, $rumus, $ng->kktp) as $sid => $o) {
                    $nilai[$sid][$ng->uuid] = $o['nilai'];
                    $desk[$sid][$ng->uuid] = ['pos' => $o['pos'], 'neg' => $o['neg']];
                }
            } else {
                $model = $jenis === 'pas' ? NilaiPas::class : NilaiPts::class;
                $map = $model::where('id_ngajar', $ng->uuid)->where('id_semester', $sem?->id)->pluck('nilai', 'id_siswa')->toArray();
                foreach ($siswas as $s) {
                    $v = $map[$s->uuid] ?? null;
                    if ($v !== null && $v !== '') $nilai[$s->uuid][$ng->uuid] = (int) $v;
                }
            }
        }

        return view('rekap.nilai', [
            'kelasList'  => $kelasList,
            'selKelas'   => $selKelas,
            'jenis'      => $jenis,
            'jenisList'  => self::JENIS,
            'siswas'     => $siswas,
            'ngajars'    => $ngajars,
            'nilai'      => $nilai,
            'desk'       => $desk,
            'sem'        => $sem,
            'bolehSemua' => $bolehSemua,
        ]);
    }
}
