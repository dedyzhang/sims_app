<?php

namespace App\Http\Controllers;

use App\Models\Ekskul;
use App\Models\EkskulSiswa;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Materi;
use App\Models\Ngajar;
use App\Models\NilaiFormatif;
use App\Models\NilaiPas;
use App\Models\NilaiRapor;
use App\Models\NilaiSumatif;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Support\Penilaian;
use Illuminate\Http\Request;

class EkskulController extends Controller
{
    private function semester(): ?Semester
    {
        return Semester::aktif() ?? Semester::first();
    }

    /** Pembina ekskul atau admin. */
    private function aksesEkskul(Ekskul $ekskul): void
    {
        $u = auth()->user();
        if ($u->isAdmin()) return;
        abort_unless($u->guru && $ekskul->id_guru === $u->guru->uuid, 403, 'Anda bukan pembina ekskul ini.');
    }

    private function adminOnly(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    /** ====== Daftar ekskul ====== */
    public function index()
    {
        $u = auth()->user();
        $q = Ekskul::with(['guru', 'pelajaran'])->orderBy('urutan')->orderBy('nama');
        if (!$u->isAdmin()) {
            $q->where('id_guru', $u->guru?->uuid ?? '-');
        }
        return view('ekskul.index', [
            'ekskuls'    => $q->get(),
            'isAdmin'    => $u->isAdmin(),
            'gurus'      => $u->isAdmin() ? Guru::orderBy('nama')->get() : collect(),
            'pelajarans' => $u->isAdmin() ? Pelajaran::orderBy('urutan')->orderBy('nama')->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $this->adminOnly();
        $data = $request->validate([
            'nama'         => 'required|string|max:100',
            'id_guru'      => 'nullable|exists:gurus,uuid',
            'id_pelajaran' => 'nullable|exists:pelajarans,uuid',
        ]);
        Ekskul::create([
            'nama'         => $data['nama'],
            'id_guru'      => $data['id_guru'] ?: null,
            'id_pelajaran' => $data['id_pelajaran'] ?: null,
            'urutan'       => (int) Ekskul::max('urutan') + 1,
            'aktif'        => true,
        ]);
        return back()->with('success', 'Ekskul ditambahkan.');
    }

    public function update(Request $request, string $uuid)
    {
        $this->adminOnly();
        $ekskul = Ekskul::findOrFail($uuid);
        $data = $request->validate([
            'nama'         => 'required|string|max:100',
            'id_guru'      => 'nullable|exists:gurus,uuid',
            'id_pelajaran' => 'nullable|exists:pelajarans,uuid',
        ]);
        $ekskul->update([
            'nama'         => $data['nama'],
            'id_guru'      => $data['id_guru'] ?: null,
            'id_pelajaran' => $data['id_pelajaran'] ?: null,
        ]);
        return back()->with('success', 'Ekskul diperbarui.');
    }

    public function destroy(string $uuid)
    {
        $this->adminOnly();
        $ekskul = Ekskul::findOrFail($uuid);
        EkskulSiswa::where('id_ekskul', $ekskul->uuid)->delete();
        $ekskul->delete();
        return back()->with('success', 'Ekskul dihapus.');
    }

    /** ====== Input nilai (deskripsi) ekskul per kelas ====== */
    public function nilai(Request $request, string $uuid)
    {
        $ekskul = Ekskul::with(['guru', 'pelajaran'])->findOrFail($uuid);
        $this->aksesEkskul($ekskul);
        $sem = $this->semester();

        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $selKelas = $request->kelas ?: optional(
            $kelasList->first(fn ($k) => Siswa::where('id_kelas', $k->uuid)->exists()) ?? $kelasList->first()
        )->uuid;

        $siswas = $selKelas ? Siswa::where('id_kelas', $selKelas)->orderBy('nama')->get() : collect();

        $readonly = $ekskul->dariMapel();
        $saved = [];
        $auto = [];
        $konfirmasi = null;   // null = N/A (manual)

        if ($readonly) {
            // dari mapel → deskripsi OTOMATIS dari rapor mapel (tidak diisi guru),
            // terintegrasi dengan rapor yang sudah dikonfirmasi.
            if ($selKelas && $siswas->isNotEmpty()) {
                $ngajar = Ngajar::with(['pelajaran', 'kelas'])
                    ->where('id_pelajaran', $ekskul->id_pelajaran)->where('id_kelas', $selKelas)->first();
                if ($ngajar) {
                    $konfirmasi = \App\Models\RaporKonfirmasi::where('id_ngajar', $ngajar->uuid)
                        ->where('id_semester', $sem?->id)->exists();
                    foreach ($this->olahRapor($ngajar, $siswas, $sem) as $sid => $o) {
                        $auto[$sid] = $this->formatEkskul($o);
                    }
                } else {
                    $konfirmasi = false;
                }
            }
        } else {
            // manual → diisi guru/admin, disimpan di ekskul_siswa
            $saved = EkskulSiswa::where('id_ekskul', $uuid)->where('id_semester', $sem?->id)
                ->pluck('deskripsi', 'id_siswa')->toArray();
        }

        return view('ekskul.nilai', compact('ekskul', 'kelasList', 'selKelas', 'siswas', 'saved', 'auto', 'sem', 'readonly', 'konfirmasi'));
    }

    public function nilaiCell(Request $request, string $uuid)
    {
        $ekskul = Ekskul::findOrFail($uuid);
        $this->aksesEkskul($ekskul);
        abort_if($ekskul->dariMapel(), 422, 'Ekskul dari mapel terisi otomatis dari rapor — tidak bisa diedit manual.');
        $data = $request->validate([
            'id_siswa'  => 'required|exists:siswa,uuid',
            'deskripsi' => 'nullable|string|max:1000',
        ]);
        $sem = $this->semester();
        $key = ['id_ekskul' => $ekskul->uuid, 'id_siswa' => $data['id_siswa'], 'id_semester' => $sem?->id];
        $teks = trim((string) ($data['deskripsi'] ?? ''));
        if ($teks === '') {
            EkskulSiswa::where($key)->delete();
        } else {
            EkskulSiswa::updateOrCreate($key, ['deskripsi' => $teks]);
        }
        return response()->json(['ok' => true]);
    }

    /** Olah nilai rapor satu ngajar → [siswa => {nilai,predikat,pos,neg}]. */
    private function olahRapor(Ngajar $ngajar, $siswas, ?Semester $sem): array
    {
        $kktp = $ngajar->kktp;
        $rumus = Setting::get('rumus_rapor', 'bagi4');
        $materi = Materi::with('tujuan')->where('id_ngajar', $ngajar->uuid)
            ->where('id_semester', $sem?->id)->where('aktif', true)->get();
        $tupeAll = $materi->flatMap(fn ($m) => $m->tujuan);
        $tupeText = $tupeAll->pluck('tupe', 'uuid');

        $fmt = [];
        foreach (NilaiFormatif::whereIn('id_tupe', $tupeAll->pluck('uuid'))->get() as $r) { $fmt[$r->id_siswa][$r->id_tupe] = (float) $r->nilai; }
        $sum = [];
        foreach (NilaiSumatif::whereIn('id_materi', $materi->pluck('uuid'))->get() as $r) { $sum[$r->id_siswa][] = (float) $r->nilai; }
        $pas = NilaiPas::where('id_ngajar', $ngajar->uuid)->where('id_semester', $sem?->id)->pluck('nilai', 'id_siswa')->toArray();
        $rapor = NilaiRapor::where('id_ngajar', $ngajar->uuid)->where('id_semester', $sem?->id)->get()->keyBy('id_siswa');

        $out = [];
        foreach ($siswas as $s) {
            $h = Penilaian::hitung(array_values($fmt[$s->uuid] ?? []), $sum[$s->uuid] ?? [], isset($pas[$s->uuid]) ? (float) $pas[$s->uuid] : null, $rumus);
            $rf = $rapor->get($s->uuid);
            $nilai = ($rf && $rf->nilai !== null) ? (int) $rf->nilai : $h['rapor'];
            $pred = Penilaian::predikat($nilai, $kktp);

            $dPos = $dNeg = '';
            $skorTupe = $fmt[$s->uuid] ?? [];
            if (!empty($skorTupe)) {
                arsort($skorTupe); $maxTupe = array_key_first($skorTupe);
                asort($skorTupe);  $minTupe = array_key_first($skorTupe);
                $predMax = Penilaian::predikat((int) round($skorTupe[$maxTupe]), $kktp);
                $dPos = Penilaian::kalimatPositif($predMax, (string) ($tupeText[$maxTupe] ?? ''));
                $dNeg = Penilaian::kalimatNegatif((string) ($tupeText[$minTupe] ?? ''));
            }
            $out[$s->uuid] = [
                'predikat' => $pred,
                'pos'      => $rf?->deskripsi_positif ?? $dPos,
                'neg'      => $rf?->deskripsi_negatif ?? $dNeg,
            ];
        }
        return $out;
    }

    /** Format ekskul: "Predikat, capaian positif namun capaian negatif". */
    private function formatEkskul(array $o): string
    {
        $kata = Penilaian::predikatKata($o['predikat']);
        $pos = trim((string) $o['pos']);
        $neg = trim((string) $o['neg']);
        if ($pos === '' && $neg === '') return $kata . '.';
        $s = $kata;
        if ($pos !== '') $s .= ', ' . rtrim(lcfirst($pos), '.');
        if ($neg !== '') $s .= ' namun ' . rtrim(lcfirst($neg), '.');
        return $s . '.';
    }
}
