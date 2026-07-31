<?php

namespace App\Http\Controllers;

use App\Models\Agenda;
use App\Models\ClassroomAssignment;
use App\Models\ClassroomAssignmentFile;
use App\Models\GuruTidakHadir;
use App\Models\PenugasanPengganti;
use App\Models\TugasKelas;
use App\Services\ClassroomService;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TugasKelasController extends Controller
{
    private const EXT_DIIZINKAN = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx'];

    public function __construct(private ClassroomService $classroomService)
    {
    }

    /** Halaman guru (yang tidak hadir) untuk upload tugas ke jam kosongnya sendiri, hari ini. */
    public function formSaya(Request $request)
    {
        $idGuru = auth()->user()->guru?->uuid;
        abort_unless($idGuru, 403, 'Akun ini tidak memiliki profil guru.');

        $tanggal = $request->tanggal && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->tanggal)
            ? $request->tanggal : now()->toDateString();

        $absenHariIni = GuruTidakHadir::where('id_guru', $idGuru)->where('tanggal', $tanggal)->first();

        if ($absenHariIni) {
            $slots = PenugasanPengganti::with(['jadwal.kelas:uuid,tingkat,kelas', 'jadwal.pelajaran:uuid,nama', 'tugasKelas'])
                ->whereHas('guruTidakHadir', fn ($q) => $q->where('id_guru', $idGuru)->where('tanggal', $tanggal))
                ->get();
        } else {
            $slots = collect();
        }

        return view('piket.tugas-saya', compact('slots', 'tanggal', 'absenHariIni'));
    }

    public function upload(Request $request, PenugasanPengganti $penugasanPengganti)
    {
        $this->authorize('upload', [TugasKelas::class, $penugasanPengganti]);

        $data = $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['required', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:10240'],
        ]);

        $tugas = DB::transaction(function () use ($penugasanPengganti, $data, $request) {
            $tugas = TugasKelas::updateOrCreate(
                ['id_penugasan_pengganti' => $penugasanPengganti->uuid],
                [
                    'jenis' => 'upload_guru_asli',
                    'judul' => $data['judul'],
                    'deskripsi' => $data['deskripsi'],
                    'dibuat_oleh' => auth()->id(),
                ]
            );

            if ($request->hasFile('file')) {
                $this->simpanFile($tugas, $request->file('file'));
            }

            // Langsung distribusikan ke siswa tanpa perlu konfirmasi piket
            return $this->distribusikan($tugas, $penugasanPengganti);
        });

        activity('piket')
            ->causedBy(auth()->user())
            ->performedOn($tugas)
            ->log('Upload & Distribusi tugas untuk jam kosong (guru asli)');

        return response()->json($this->formatTugas($tugas));
    }

    /** Halaman guru piket: daftar jam kosong hari ini + status tugas, titip manual/konfirmasi. */
    public function index(Request $request)
    {
        $this->authorize('viewAny', TugasKelas::class);

        $tanggal = $request->tanggal && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->tanggal)
            ? $request->tanggal : now()->toDateString();

        $slots = PenugasanPengganti::with([
            'guruTidakHadir.guru:uuid,nama',
            'jadwal.kelas:uuid,tingkat,kelas',
            'jadwal.pelajaran:uuid,nama',
            'tugasKelas',
        ])
            ->whereHas('guruTidakHadir', fn ($q) => $q->where('tanggal', $tanggal))
            ->get();

        $bolehKelola = auth()->user()->can('manage', [TugasKelas::class, $tanggal]);

        return view('piket.tugas', compact('slots', 'tanggal', 'bolehKelola'));
    }

    /** Guru piket titip tugas manual + langsung terkonfirmasi ke agenda & Ruang Kelas siswa (satu aksi). */
    public function titip(Request $request, PenugasanPengganti $penugasanPengganti)
    {
        $tanggal = $penugasanPengganti->guruTidakHadir->tanggal->toDateString();
        $this->authorize('manage', [TugasKelas::class, $tanggal]);

        $data = $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['required', 'string', 'max:5000'],
        ]);

        $tugas = DB::transaction(function () use ($penugasanPengganti, $data) {
            $tugas = TugasKelas::updateOrCreate(
                ['id_penugasan_pengganti' => $penugasanPengganti->uuid],
                [
                    'jenis' => 'titip_manual_piket',
                    'judul' => $data['judul'],
                    'deskripsi' => $data['deskripsi'],
                    'dibuat_oleh' => auth()->id(),
                ]
            );

            return $this->distribusikan($tugas, $penugasanPengganti);
        });

        activity('piket')
            ->causedBy(auth()->user())
            ->performedOn($tugas)
            ->log('Titip tugas manual & konfirmasi ke agenda');

        return response()->json($this->formatTugas($tugas));
    }

    /** Guru piket konfirmasi draft upload dari guru asli -> catat ke agenda & Ruang Kelas siswa. */
    public function konfirmasi(PenugasanPengganti $penugasanPengganti)
    {
        $tanggal = $penugasanPengganti->guruTidakHadir->tanggal->toDateString();
        $this->authorize('manage', [TugasKelas::class, $tanggal]);

        $tugas = $penugasanPengganti->tugasKelas;
        abort_unless($tugas, 404, 'Belum ada tugas yang diupload guru asli untuk slot ini.');

        if (! $tugas->isTerkonfirmasi()) {
            DB::transaction(fn () => $this->distribusikan($tugas, $penugasanPengganti));
            $tugas->refresh();

            activity('piket')
                ->causedBy(auth()->user())
                ->performedOn($tugas)
                ->log('Konfirmasi distribusi tugas ke agenda');
        }

        return response()->json($this->formatTugas($tugas));
    }

    public function download(TugasKelas $tugasKelas)
    {
        $this->authorize('view', $tugasKelas);

        abort_unless($tugasKelas->file_path && Storage::disk('local')->exists($tugasKelas->file_path), 404);

        return Storage::disk('local')->download($tugasKelas->file_path, $tugasKelas->file_nama_asli ?? 'tugas');
    }

    /**
     * Titik distribusi utama: sekali dipanggil, tugas tercatat DUA arah —
     * 1) Buku Agenda (`agendas`) — riwayat resmi guru/kepala sekolah.
     * 2) Ruang Kelas siswa (`ClassroomAssignment`, published) — supaya siswa BENAR-BENAR
     *    menerima tugasnya, bukan cuma tercatat administratif. Ruang Kelas dicari/dibuat
     *    lewat jadwal ajar guru (Jadwal->id_kelas + id_pelajaran, via ClassroomService,
     *    konsisten dengan bagaimana Ruang Kelas biasa dibuat dari Ngajar).
     */
    private function distribusikan(TugasKelas $tugas, PenugasanPengganti $penugasanPengganti): TugasKelas
    {
        if ($tugas->isTerkonfirmasi()) {
            return $tugas;
        }

        $slot = $penugasanPengganti->jadwal;
        $guruTidakHadir = $penugasanPengganti->guruTidakHadir;

        $agenda = $this->buatAgenda($tugas, $slot, $guruTidakHadir);
        $tugas->id_agenda = $agenda->uuid;

        // Jam istirahat/upacara dsb (id_kelas atau id_pelajaran kosong) tidak punya
        // Ruang Kelas untuk dituju — agenda tetap tercatat, tapi lewati bagian siswa.
        if ($slot?->id_kelas && $slot->id_pelajaran) {
            $assignment = $this->buatClassroomAssignment($tugas, $slot, $guruTidakHadir);
            $tugas->id_classroom_assignment = $assignment->uuid;
        }

        $tugas->save();

        return $tugas;
    }

    private function buatAgenda(TugasKelas $tugas, $slot, $guruTidakHadir): Agenda
    {
        $penanda = $tugas->jenis === 'upload_guru_asli'
            ? 'Materi dari guru asli ('.($guruTidakHadir->guru?->nama ?? '-').'), diunggah jarak jauh saat berhalangan.'
            : 'Kelas diisi tugas titipan guru piket karena guru asli ('.($guruTidakHadir->guru?->nama ?? '-').') tidak hadir.';

        return Agenda::create([
            'tanggal' => $guruTidakHadir->tanggal,
            'id_jadwal' => $slot?->uuid,
            'id_guru' => $guruTidakHadir->id_guru,
            'id_kelas' => $slot?->id_kelas,
            'id_pelajaran' => $slot?->id_pelajaran,
            'pembahasan' => $tugas->judul,
            'kegiatan' => $tugas->deskripsi,
            'proses' => 'selesai',
            'kendala' => $penanda,
            'semester' => now()->month >= 7 ? 1 : 2,
        ]);
    }

    private function buatClassroomAssignment(TugasKelas $tugas, $slot, $guruTidakHadir): ClassroomAssignment
    {
        $kelas = $slot->kelas;
        $pelajaran = $slot->pelajaran;

        $classroom = $this->classroomService->subjectRoom($kelas, $pelajaran, auth()->user());

        // Dibuat atas nama guru asli kalau akunnya ada (tugas ini secara substansi
        // "dari" dia) — fallback ke akun yang login (guru piket) kalau guru asli tidak
        // punya akun login terhubung.
        $pembuat = $guruTidakHadir->guru?->user?->uuid ?? auth()->id();

        $catatanSumber = $tugas->jenis === 'upload_guru_asli'
            ? "\n\n_(Dikirim langsung oleh {$guruTidakHadir->guru?->nama} saat berhalangan hadir.)_"
            : "\n\n_(Dititipkan oleh guru piket karena {$guruTidakHadir->guru?->nama} tidak hadir.)_";

        $assignment = ClassroomAssignment::create([
            'classroom_id' => $classroom->uuid,
            'created_by' => $pembuat,
            'title' => $tugas->judul,
            'instructions' => $tugas->deskripsi.$catatanSumber,
            'type' => 'tugas',
            'max_score' => 100,
            'allow_late' => true,
            'opens_at' => now(),
            'due_at' => null,
            'status' => 'published',
        ]);

        $this->classroomService->linkToKelas($assignment, [$kelas->uuid], $classroom, auth()->user());

        if ($tugas->file_path && Storage::disk('local')->exists($tugas->file_path)) {
            $this->salinFileKeClassroomAssignment($tugas, $assignment);
        }

        return $assignment;
    }

    /**
     * File tugas piket disimpan di disk 'local' tanpa kompresi (lihat simpanFile()).
     * Untuk lampiran Ruang Kelas, file disalin apa adanya ke disk 'public' — TIDAK lewat
     * FileCompressionService (yang cuma dukung image/pdf, sedangkan Piket juga izinkan
     * doc/docx) — jadi tanpa resize/WebP-convert seperti upload materi Ruang Kelas normal,
     * hanya disalin mentah supaya siswa tetap dapat filenya.
     */
    private function salinFileKeClassroomAssignment(TugasKelas $tugas, ClassroomAssignment $assignment): void
    {
        $ext = pathinfo($tugas->file_path, PATHINFO_EXTENSION) ?: 'bin';
        $storedName = (string) Str::uuid().'.'.$ext;
        $relPath = 'classroom/assignments/'.$storedName;

        $isi = Storage::disk('local')->get($tugas->file_path);
        Storage::disk('public')->put($relPath, $isi);

        $size = Storage::disk('local')->size($tugas->file_path);
        $mime = Storage::disk('local')->mimeType($tugas->file_path) ?: 'application/octet-stream';

        ClassroomAssignmentFile::create([
            'assignment_id' => $assignment->uuid,
            'original_name' => $tugas->file_nama_asli ?? $storedName,
            'stored_name' => $storedName,
            'path' => $relPath,
            'mime' => $mime,
            'size_original' => $size,
            'size_compressed' => $size,
        ]);
    }

    private function simpanFile(TugasKelas $tugas, $file): void
    {
        $ext = Uploads::safeExtension($file, self::EXT_DIIZINKAN, 'bin');
        $nama = (string) Str::uuid().'.'.$ext;
        $dir = 'tugas-kelas/'.$tugas->uuid;
        Storage::disk('local')->putFileAs($dir, $file, $nama);

        $tugas->update([
            'file_path' => $dir.'/'.$nama,
            'file_nama_asli' => $file->getClientOriginalName(),
        ]);
    }

    private function formatTugas(TugasKelas $tugas): array
    {
        return [
            'id' => $tugas->uuid,
            'id_penugasan_pengganti' => $tugas->id_penugasan_pengganti,
            'jenis' => $tugas->jenis,
            'judul' => $tugas->judul,
            'deskripsi' => $tugas->deskripsi,
            'ada_file' => $tugas->file_path !== null,
            'terkonfirmasi' => $tugas->isTerkonfirmasi(),
            'diterima_siswa' => $tugas->id_classroom_assignment !== null,
        ];
    }

    public function destroy(TugasKelas $tugasKelas)
    {
        $this->authorize('delete', $tugasKelas);

        DB::transaction(function () use ($tugasKelas) {
            // Hapus agenda
            if ($tugasKelas->id_agenda) {
                \App\Models\Agenda::where('uuid', $tugasKelas->id_agenda)->delete();
            }

            // Hapus ClassroomAssignment
            if ($tugasKelas->id_classroom_assignment) {
                $assignment = \App\Models\ClassroomAssignment::find($tugasKelas->id_classroom_assignment);
                if ($assignment) {
                    \App\Models\ClassroomAssignmentFile::where('assignment_id', $assignment->uuid)->delete();
                    $assignment->delete();
                }
            }

            // Hapus file lokal
            if ($tugasKelas->file_path && Storage::disk('local')->exists($tugasKelas->file_path)) {
                Storage::disk('local')->delete($tugasKelas->file_path);
            }

            $tugasKelas->delete();
        });

        activity('piket')
            ->causedBy(auth()->user())
            ->performedOn($tugasKelas)
            ->log('Menghapus tugas kelas');

        return response()->json(['message' => 'Tugas berhasil dihapus.']);
    }
}
