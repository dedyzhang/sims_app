<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Ekskul;
use App\Models\EkskulSiswa;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\NilaiPenjabaran;
use App\Models\PenjabaranKomponen;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Support\Penilaian;
use App\Support\RaporHitung;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CetakRaporController extends Controller
{
    /** Tingkat → Fase Kurikulum Merdeka. */
    private const FASE = [1 => 'A', 2 => 'A', 3 => 'B', 4 => 'B', 5 => 'C', 6 => 'C', 7 => 'D', 8 => 'D', 9 => 'D', 10 => 'E', 11 => 'F', 12 => 'F'];

    /** @return array{0:bool,1:?string} [bolehSemua, idKelasWalikelas] */
    private function akses(): array
    {
        $user = auth()->user();
        $bolehSemua = $user->canAccess('manage_rapor');
        $wkKelas = null;
        if (!$bolehSemua) {
            $wk = $user->guru?->walikelas;
            abort_unless($wk, 403, 'Hanya pengelola dan wali kelas yang dapat mencetak rapor.');
            $wkKelas = $wk->id_kelas;
        }
        return [$bolehSemua, $wkKelas];
    }

    public function index(Request $request)
    {
        [$bolehSemua, $wkKelas] = $this->akses();

        $kelasList = $bolehSemua
            ? Kelas::orderBy('tingkat')->orderBy('kelas')->get()
            : Kelas::where('uuid', $wkKelas)->get();

        $selKelas = $wkKelas ?: ($request->kelas ?: optional($kelasList->first())->uuid);
        $siswas = $selKelas ? Siswa::where('id_kelas', $selKelas)->orderBy('nama')->get() : collect();
        $sem = Semester::aktif() ?? Semester::first();

        return view('cetak.index', compact('kelasList', 'selKelas', 'siswas', 'sem', 'bolehSemua'));
    }

    public function cetak(Request $request)
    {
        [$bolehSemua, $wkKelas] = $this->akses();

        $selKelas = $bolehSemua ? $request->kelas : $wkKelas;
        abort_unless($selKelas, 404, 'Kelas tidak ditentukan.');
        if (!$bolehSemua) abort_unless($selKelas === $wkKelas, 403);

        $kelas = Kelas::with('walikelas.guru')->findOrFail($selKelas);

        $siswas = Siswa::where('id_kelas', $selKelas)
            ->when($request->siswa, fn ($q) => $q->where('uuid', $request->siswa))
            ->orderBy('nama')->get();
        abort_if($siswas->isEmpty(), 404, 'Tidak ada siswa pada kelas ini.');

        $sem = Semester::aktif() ?? Semester::first();
        $rumus = Setting::get('rumus_rapor', 'bagi4');

        // Ekskul aktif. Pelajaran yang dijadikan ekskul dikeluarkan dari tabel mapel.
        $ekskuls = Ekskul::orderBy('urutan')->orderBy('nama')->get()->filter(fn ($e) => $e->aktif)->values();
        $pelajaranEkskul = $ekskuls->whereNotNull('id_pelajaran')->pluck('id_pelajaran')->all();

        // Penugasan mengajar (mapel) kelas ini, urut.
        $ngajars = Ngajar::with('pelajaran')->where('id_kelas', $selKelas)->whereNotNull('id_pelajaran')->get()
            ->sortBy(fn ($n) => [$n->pelajaran?->urutan ?? 99, $n->pelajaran?->nama])->values();
        $ngajarMapel = $ngajars->reject(fn ($n) => in_array($n->id_pelajaran, $pelajaranEkskul, true))->values();

        // Olah rapor (nilai + predikat + deskripsi) per ngajar untuk seluruh siswa.
        $olah = [];
        foreach ($ngajars as $ng) {
            $olah[$ng->uuid] = RaporHitung::olah($ng, $siswas, $sem?->id, $rumus, $ng->kktp);
        }

        // Ekskul per siswa (manual = ketik; dari mapel = olahan rapor).
        $manualIds = $ekskuls->filter(fn ($e) => !$e->dariMapel())->pluck('uuid')->all();
        $manualMap = [];
        if ($manualIds) {
            foreach (EkskulSiswa::whereIn('id_ekskul', $manualIds)->where('id_semester', $sem?->id)->get() as $r) {
                $manualMap[$r->id_ekskul][$r->id_siswa] = $r->deskripsi;
            }
        }
        $ekskulRows = [];
        foreach ($siswas as $s) {
            foreach ($ekskuls as $e) {
                if ($e->dariMapel()) {
                    $ng = $ngajars->firstWhere('id_pelajaran', $e->id_pelajaran);
                    $o = $ng ? ($olah[$ng->uuid][$s->uuid] ?? null) : null;
                    if (!$o) continue;
                    $desk = $this->formatEkskul($o);
                } else {
                    $desk = $manualMap[$e->uuid][$s->uuid] ?? '';
                    if (trim((string) $desk) === '') continue;
                }
                $ekskulRows[$s->uuid][] = ['nama' => $e->nama, 'desk' => $desk];
            }
        }

        // Penjabaran nilai per pelajaran (yang punya komponen).
        $penjabaran = [];
        foreach ($ngajars as $ng) {
            $komp = PenjabaranKomponen::where('id_pelajaran', $ng->id_pelajaran)->orderBy('urutan')->get();
            if ($komp->isEmpty()) continue;
            $vals = NilaiPenjabaran::where('id_ngajar', $ng->uuid)->where('id_semester', $sem?->id)->get();
            $map = [];
            foreach ($vals as $v) { $map[$v->id_siswa][$v->id_komponen] = $v->nilai; }
            $penjabaran[] = ['nama' => $ng->pelajaran?->nama, 'komponen' => $komp, 'nilai' => $map, 'kktp' => $ng->kktp];
        }

        // Ketidakhadiran per siswa.
        $absensi = [];
        $queryAbsen = Absensi::whereIn('id_siswa', $siswas->pluck('uuid'))->selectRaw('id_siswa, status, count(*) as c')->groupBy('id_siswa', 'status');
        if ($sem) $queryAbsen->where('id_semester', $sem->id);
        foreach ($queryAbsen->get() as $r) {
            $absensi[$r->id_siswa][$r->status] = $r->c;
        }

        $kepalaGuru = Guru::whereHas('user', fn ($q) => $q->where('access', 'kepala'))->first();

        $sekolah = [
            'nama'     => Setting::get('nama_sekolah', 'Sekolah'),
            'alamat'   => Setting::get('alamat_sekolah', ''),
            'kota'     => Setting::get('kota', ''),
            'provinsi' => Setting::get('provinsi', ''),
            'telp'     => Setting::get('telp_sekolah', ''),
            'npsn'     => Setting::get('npsn', ''),
        ];

        return view('cetak.rapor', [
            'kelas'       => $kelas,
            'siswas'      => $siswas,
            'sem'         => $sem,
            'fase'        => self::FASE[$kelas->tingkat] ?? '-',
            'ngajarMapel' => $ngajarMapel,
            'olah'        => $olah,
            'ekskulRows'  => $ekskulRows,
            'penjabaran'  => $penjabaran,
            'absensi'     => $absensi,
            'sekolah'     => $sekolah,
            'walikelas'   => $kelas->walikelas?->guru,
            'kepala'      => $kepalaGuru?->nama ?: Setting::get('kepala_sekolah', ''),
            'nikKepala'   => $kepalaGuru?->nik ?: Setting::get('nip_kepala', ''),
            'tanggal'     => Carbon::now()->locale('id')->translatedFormat('d F Y'),
            // Kop surat dapat diatur admin (upload logo, teks, backdrop)
            'kopLogoKiri'  => $this->kopImg('kop_logo_kiri', 'img/tutwuri.png'),
            'kopLogoKanan' => $this->kopImg('kop_logo_kanan', 'img/maitreyawira_square.png'),
            'kopBackdrop'  => $this->kopImg('kop_backdrop', 'img/logo.png'),
            'kopTeks'      => Setting::get('kop_teks'),
        ]);
    }

    /** URL gambar kop: pakai upload admin bila ada, selain itu default di public/img. */
    private function kopImg(string $key, string $default): ?string
    {
        $v = Setting::get($key);
        if ($v && file_exists(storage_path('app/public/' . $v))) return asset('storage/' . $v);
        if (file_exists(public_path($default))) return asset($default);
        return null;
    }

    /** Format deskripsi ekskul dari-mapel: "Predikat, pos namun neg." */
    private function formatEkskul(array $o): string
    {
        $kata = Penilaian::predikatKata($o['predikat']);
        $pos = trim((string) ($o['pos'] ?? ''));
        $neg = trim((string) ($o['neg'] ?? ''));
        if ($pos === '' && $neg === '') return $kata . '.';
        $s = $kata;
        if ($pos !== '') $s .= ', ' . rtrim(lcfirst($pos), '.');
        if ($neg !== '') $s .= ' namun ' . rtrim(lcfirst($neg), '.');
        return $s . '.';
    }
}
