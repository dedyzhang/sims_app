<?php

namespace App\Http\Controllers;

use App\Models\BankSoal;
use App\Models\BankSoalOpsi;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Support\SoalValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankSoalController extends Controller
{
    /** Daftar mapel yg boleh dikelola bank soalnya oleh user ini — admin/manage_ujian lihat semua, guru cuma mapel yg diajarnya (via Ngajar). */
    public function index(Request $request)
    {
        $this->authorize('create', BankSoal::class);
        $user = $request->user();

        if ($user->isAdmin() || $user->canAccess('manage_ujian')) {
            $pelajaran = Pelajaran::orderBy('nama')->get();
        } else {
            $idPelajaran = Ngajar::where('id_guru', $user->guru?->uuid ?? '-')->distinct()->pluck('id_pelajaran');
            $pelajaran = Pelajaran::whereIn('uuid', $idPelajaran)->orderBy('nama')->get();
        }

        $jumlahPerPelajaran = BankSoal::whereIn('id_pelajaran', $pelajaran->pluck('uuid'))
            ->selectRaw('id_pelajaran, count(*) as jumlah')
            ->groupBy('id_pelajaran')
            ->pluck('jumlah', 'id_pelajaran');

        return view('bank-soal.index', compact('pelajaran', 'jumlahPerPelajaran'));
    }

    public function show(Request $request, Pelajaran $pelajaran)
    {
        $this->authorize('viewPelajaran', [BankSoal::class, $pelajaran->uuid]);

        $soal = BankSoal::with('opsi')->where('id_pelajaran', $pelajaran->uuid)->orderBy('urutan')->get();

        return view('bank-soal.show', compact('pelajaran', 'soal'));
    }

    public function store(Request $request, Pelajaran $pelajaran)
    {
        $this->authorize('viewPelajaran', [BankSoal::class, $pelajaran->uuid]);

        $data = SoalValidator::validate($request);

        DB::transaction(function () use ($request, $pelajaran, $data) {
            $urutan = (int) BankSoal::where('id_pelajaran', $pelajaran->uuid)->max('urutan') + 1;

            $soal = BankSoal::create([
                'id_pelajaran' => $pelajaran->uuid,
                'created_by'   => $request->user()->uuid,
                'tipe'         => $data['tipe'],
                'teks_soal'    => $data['teks_soal'],
                'poin'         => $data['poin'],
                'urutan'       => $urutan,
                'meta'         => $data['meta'] ?? null,
                'penjelasan'   => $data['penjelasan'] ?? null,
                'skor_mode'    => $data['skor_mode'],
            ]);

            $this->simpanOpsi($soal, $data);
        });

        return back()->with('success', 'Soal ditambahkan ke Bank Soal.');
    }

    public function update(Request $request, Pelajaran $pelajaran, BankSoal $soal)
    {
        $this->authorize('manage', $soal);
        abort_unless($soal->id_pelajaran === $pelajaran->uuid, 404);

        $data = SoalValidator::validate($request);

        DB::transaction(function () use ($soal, $data) {
            $soal->update([
                'tipe'       => $data['tipe'],
                'teks_soal'  => $data['teks_soal'],
                'poin'       => $data['poin'],
                'meta'       => $data['meta'] ?? null,
                'penjelasan' => $data['penjelasan'] ?? null,
                'skor_mode'  => $data['skor_mode'],
            ]);

            $soal->opsi()->delete();
            $this->simpanOpsi($soal, $data);
        });

        return back()->with('success', 'Soal diperbarui.');
    }

    public function destroy(Request $request, Pelajaran $pelajaran, BankSoal $soal)
    {
        $this->authorize('manage', $soal);
        abort_unless($soal->id_pelajaran === $pelajaran->uuid, 404);

        $soal->delete();

        return back()->with('success', 'Soal dihapus dari Bank Soal.');
    }

    public function data(Request $request, Pelajaran $pelajaran, BankSoal $soal)
    {
        abort_unless($soal->id_pelajaran === $pelajaran->uuid, 404);

        $soal->load('opsi');

        return response()->json([
            'teks_soal' => $soal->teks_soal,
            'penjelasan' => $soal->penjelasan ?? '',
            'kunci_esai' => $soal->meta['kunci_jawaban'] ?? '',
            'opsi' => $soal->opsi->map(fn($o) => ['teks' => $o->teks_opsi, 'benar' => $o->is_benar]),
            'pasangan' => ($soal->meta['pairs'] ?? []) ? collect($soal->meta['pairs'])->map(fn($p) => ['kiri'=>$p['left'],'kanan'=>$p['right']])->all() : []
        ]);
    }

    /**
     * Dipanggil dari modal "Sisipkan dari Bank Soal" di halaman Susun Soal ujian —
     * balikan JSON ringkas (bukan Blade) supaya modal bisa render daftar tanpa reload.
     */
    public function pilihJson(Request $request, Pelajaran $pelajaran)
    {
        $this->authorize('viewPelajaran', [BankSoal::class, $pelajaran->uuid]);

        $soal = BankSoal::where('id_pelajaran', $pelajaran->uuid)->orderBy('urutan')->get();

        return response()->json($soal->map(fn (BankSoal $s) => [
            'uuid'   => $s->uuid,
            'tipe'   => $s->tipe,
            'label'  => $s->typeLabel(),
            'poin'   => $s->poin,
            'cuplik' => \Illuminate\Support\Str::limit(strip_tags($s->teks_soal), 100),
        ]));
    }

    private function simpanOpsi(BankSoal $soal, array $data): void
    {
        if (!$soal->butuhOpsi()) {
            return;
        }
        foreach ($data['opsi'] as $i => $o) {
            BankSoalOpsi::create([
                'id_soal'   => $soal->uuid,
                'teks_opsi' => $o['teks'],
                'is_benar'  => !empty($o['benar']),
                'urutan'    => $i + 1,
            ]);
        }
    }
}
