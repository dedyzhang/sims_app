<?php

namespace App\Http\Controllers;

use App\Models\BankSoal;
use App\Models\BankSoalOpsi;
use App\Models\Ujian;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Support\SoalValidator;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class UjianSoalController extends Controller
{
    /** Lebar maks gambar soal — cukup utk dibuka jelas di HP (bukan resolusi kamera/asli), supaya ringan lewat data seluler. */
    private const MAX_LEBAR_GAMBAR = 900;

    /**
     * Endpoint upload gambar utk TinyMCE (teks_soal & opsi jawaban) — dipanggil
     * langsung oleh editor lewat images_upload_handler, bukan terikat ke satu soal.
     * Format respons JSON {location: url} sesuai kontrak TinyMCE. Di-resize ke lebar
     * resolusi HP (bukan disimpan mentah) — GIF dikecualikan krn decode ulang lewat
     * GD menghilangkan animasinya.
     */
    public function uploadGambar(Request $request)
    {
        $this->authorize('create', Ujian::class);

        $request->validate(['file' => 'required|image|mimes:jpg,jpeg,png,webp,gif|max:4096']);

        $file = $request->file('file');
        $ext = Uploads::safeExtension($file, ['jpg', 'jpeg', 'png', 'webp', 'gif'], 'png');
        $nama = now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $ext;
        $subdir = 'ujian-soal/' . now()->format('Y/m');

        if ($ext === 'gif') {
            $path = $file->storeAs($subdir, $nama, 'public');
        } else {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($file->getRealPath());
            if ($image->width() > self::MAX_LEBAR_GAMBAR) {
                $image->scaleDown(width: self::MAX_LEBAR_GAMBAR);
            }
            $encoded = match ($ext) {
                'png'   => (string) $image->toPng(),
                'webp'  => (string) $image->toWebp(80),
                default => (string) $image->toJpeg(80),
            };
            $path = $subdir . '/' . $nama;
            Storage::disk('public')->put($path, $encoded);
        }

        return response()->json(['location' => Storage::disk('public')->url($path)]);
    }

    public function store(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        abort_if($ujian->isPublished() || $ujian->isClosed(), 422, 'Ujian yang sudah terbit/ditutup tidak bisa diubah soalnya — tutup dulu jadi draf kalau perlu revisi besar.');

        $data = SoalValidator::validate($request);

        DB::transaction(function () use ($ujian, $data) {
            $urutan = (int) $ujian->soal()->max('urutan') + 1;

            $soal = UjianSoal::create([
                'id_ujian'   => $ujian->uuid,
                'tipe'       => $data['tipe'],
                'teks_soal'  => $data['teks_soal'],
                'poin'       => $data['poin'],
                'urutan'     => $urutan,
                'meta'       => $data['meta'] ?? null,
                'penjelasan' => $data['penjelasan'] ?? null,
                'skor_mode'  => $data['skor_mode'],
            ]);

            $this->simpanOpsi($soal, $data);
        });

        return back()->with('success', 'Soal ditambahkan.');
    }

    public function update(Request $request, Ujian $ujian, UjianSoal $soal)
    {
        $this->authorize('manage', $ujian);
        abort_unless($soal->id_ujian === $ujian->uuid, 404);
        abort_if($ujian->isPublished() || $ujian->isClosed(), 422, 'Ujian yang sudah terbit/ditutup tidak bisa diubah soalnya.');

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

    public function destroy(Request $request, Ujian $ujian, UjianSoal $soal)
    {
        $this->authorize('manage', $ujian);
        abort_unless($soal->id_ujian === $ujian->uuid, 404);
        abort_if($ujian->isPublished() || $ujian->isClosed(), 422, 'Ujian yang sudah terbit/ditutup tidak bisa diubah soalnya.');

        $soal->delete();

        return back()->with('success', 'Soal dihapus.');
    }

    public function data(Request $request, Ujian $ujian, UjianSoal $soal)
    {
        $this->authorize('manage', $ujian);
        abort_unless($soal->id_ujian === $ujian->uuid, 404);

        $soal->load('opsi');

        return response()->json([
            'teks_soal' => $soal->teks_soal,
            'penjelasan' => $soal->penjelasan ?? '',
            'kunci_esai' => $soal->meta['kunci_jawaban'] ?? '',
            'opsi' => $soal->opsi->map(fn($o) => ['teks' => $o->teks_opsi, 'benar' => $o->is_benar]),
            'pasangan' => ($soal->meta['pairs'] ?? []) ? collect($soal->meta['pairs'])->map(fn($p) => ['kiri'=>$p['left'],'kanan'=>$p['right']])->all() : []
        ]);
    }

    public function reorder(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        abort_if($ujian->isPublished() || $ujian->isClosed(), 422, 'Ujian yang sudah terbit/ditutup tidak bisa diubah urutan soalnya.');

        $data = $request->validate([
            'urutan'   => 'required|array',
            'urutan.*' => 'uuid',
        ]);

        DB::transaction(function () use ($ujian, $data) {
            foreach ($data['urutan'] as $i => $soalUuid) {
                UjianSoal::where('id_ujian', $ujian->uuid)->where('uuid', $soalUuid)->update(['urutan' => $i + 1]);
            }
        });

        return back()->with('success', 'Urutan soal diperbarui.');
    }

    private function simpanOpsi(UjianSoal $soal, array $data): void
    {
        if (!$soal->butuhOpsi()) {
            return;
        }
        foreach ($data['opsi'] as $i => $o) {
            UjianSoalOpsi::create([
                'id_soal'   => $soal->uuid,
                'teks_opsi' => $o['teks'],
                'is_benar'  => !empty($o['benar']),
                'urutan'    => $i + 1,
            ]);
        }
    }

    /**
     * Salin soal terpilih dari Bank Soal (mapel yg sama dgn ujian) ke ujian_soal — SALINAN
     * independen, bukan referensi, supaya perubahan bank soal di masa depan TIDAK ikut
     * mengubah ujian yg sudah dibuat dari soal itu (dan sebaliknya).
     */
    public function sisipkanDariBank(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        abort_if($ujian->isPublished() || $ujian->isClosed(), 422, 'Ujian yang sudah terbit/ditutup tidak bisa diubah soalnya.');

        $data = $request->validate([
            'soal'   => 'required|array|min:1',
            'soal.*' => 'uuid',
        ]);

        $bankSoal = BankSoal::with('opsi')
            ->where('id_pelajaran', $ujian->id_pelajaran)
            ->whereIn('uuid', $data['soal'])
            ->get()
            ->keyBy('uuid');

        $jumlahDisisipkan = 0;

        DB::transaction(function () use ($ujian, $data, $bankSoal, &$jumlahDisisipkan) {
            $urutan = (int) $ujian->soal()->max('urutan');

            foreach ($data['soal'] as $uuid) {
                $sumber = $bankSoal->get($uuid);
                if (!$sumber) {
                    continue; // bukan milik mapel ujian ini, atau sudah dihapus — lewati diam2
                }

                $urutan++;
                $soalBaru = UjianSoal::create([
                    'id_ujian'   => $ujian->uuid,
                    'tipe'       => $sumber->tipe,
                    'teks_soal'  => $sumber->teks_soal,
                    'poin'       => $sumber->poin,
                    'urutan'     => $urutan,
                    'meta'       => $sumber->meta,
                    'penjelasan' => $sumber->penjelasan,
                    'skor_mode'  => $sumber->skor_mode,
                ]);

                foreach ($sumber->opsi as $o) {
                    UjianSoalOpsi::create([
                        'id_soal'   => $soalBaru->uuid,
                        'teks_opsi' => $o->teks_opsi,
                        'is_benar'  => $o->is_benar,
                        'urutan'    => $o->urutan,
                    ]);
                }

                $jumlahDisisipkan++;
            }
        });

        return back()->with('success', $jumlahDisisipkan . ' soal disisipkan dari Bank Soal.');
    }

    /**
     * Simpan salinan satu soal ujian ke Bank Soal mapel ini, supaya bisa dipakai ulang
     * di ujian lain — salinan independen (edit di ujian TIDAK mengubah bank, & sebaliknya).
     */
    public function simpanKeBank(Request $request, Ujian $ujian, UjianSoal $soal)
    {
        $this->authorize('manage', $ujian);
        abort_unless($soal->id_ujian === $ujian->uuid, 404);

        DB::transaction(function () use ($request, $ujian, $soal) {
            $soalBank = BankSoal::create([
                'id_pelajaran' => $ujian->id_pelajaran,
                'created_by'   => $request->user()->uuid,
                'tipe'         => $soal->tipe,
                'teks_soal'    => $soal->teks_soal,
                'poin'         => $soal->poin,
                'urutan'       => (int) BankSoal::where('id_pelajaran', $ujian->id_pelajaran)->max('urutan') + 1,
                'meta'         => $soal->meta,
                'penjelasan'   => $soal->penjelasan,
                'skor_mode'    => $soal->skor_mode,
            ]);

            foreach ($soal->opsi as $o) {
                BankSoalOpsi::create([
                    'id_soal'   => $soalBank->uuid,
                    'teks_opsi' => $o->teks_opsi,
                    'is_benar'  => $o->is_benar,
                    'urutan'    => $o->urutan,
                ]);
            }
        });

        return back()->with('success', 'Soal disimpan ke Bank Soal.');
    }
}
