<?php

namespace App\Http\Controllers;

use App\Models\GrupChat;
use App\Models\GrupChatMember;
use App\Models\GrupChatMessage;
use App\Models\User;
use App\Services\GrupChatMessenger;
use App\Support\ChatAttachments;
use App\Support\GrupChatMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GrupChatController extends Controller
{
    /** Jumlah pesan yang dirender server-side saat halaman dibuka. */
    private const HALAMAN_AWAL = 50;

    public function __construct(private GrupChatMessenger $messenger) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', GrupChat::class);

        $semua = GrupChatMenu::daftar($request->user());

        return view('grup.index', [
            'grupAktif' => $semua->where('status', 'aktif')->values(),
            'grupArsip' => $semua->where('status', 'arsip')->values(),
        ]);
    }

    public function show(Request $request, GrupChat $grup)
    {
        $this->authorize('view', $grup);

        $user = $request->user();
        $member = $this->member($grup, $user);
        $batas = $member?->batasSeq() ?? 0;

        $pesan = GrupChatMessage::where('grup_id', $grup->uuid)
            ->where('seq', '>=', $batas)
            ->orderByDesc('seq')
            ->limit(self::HALAMAN_AWAL)
            ->get()
            ->reverse()
            ->values();

        $this->tandaiTerbaca($grup, $member);

        $bolehKirim = $request->user()->can('send', $grup);

        return view('grup.show', [
            'grup' => $grup,
            'member' => $member,
            'pesan' => $pesan->map(fn ($p) => $this->messenger->serialize($p)),
            'lastSeq' => (int) $grup->last_seq,
            'batasSeq' => $batas,
            'jumlahAnggota' => GrupChatMember::where('grup_id', $grup->uuid)->whereNull('left_at')->count(),
            'bolehKirim' => $bolehKirim,
            'bolehModerasi' => $request->user()->can('moderasi', $grup),
            'bolehBalasPengumuman' => $this->bolehBalasPengumuman($grup, $member, $bolehKirim),
        ]);
    }

    /**
     * Endpoint polling.
     *
     * Jalur cepat: kalau cursor klien sudah sama dengan grup.last_seq, balas
     * langsung tanpa menyentuh tabel pesan. Ini yang membuat polling 4 detik
     * untuk ratusan siswa tetap layak di shared hosting — steady-state hanya
     * satu pembacaan primary key.
     */
    public function poll(Request $request, GrupChat $grup): JsonResponse
    {
        $this->authorize('view', $grup);

        $user = $request->user();
        $member = $this->member($grup, $user);
        $after = max(0, (int) $request->query('after', 0));
        $lastSeq = (int) $grup->last_seq;
        $bolehKirim = $user->can('send', $grup);

        $meta = [
            'last_seq' => $lastSeq,
            'mode' => $grup->mode,
            'status' => $grup->status,
            'boleh_kirim' => $bolehKirim,
            'boleh_moderasi' => $user->can('moderasi', $grup),
            'boleh_balas_pengumuman' => $this->bolehBalasPengumuman($grup, $member, $bolehKirim),
        ];

        if ($after >= $lastSeq) {
            return response()->json($meta + ['messages' => []]);
        }

        $batas = max($after + 1, $member?->batasSeq() ?? 0);

        $pesan = GrupChatMessage::where('grup_id', $grup->uuid)
            ->where('seq', '>=', $batas)
            ->orderBy('seq')
            ->limit(200)
            ->get();

        $this->tandaiTerbaca($grup, $member);

        return response()->json($meta + [
            'messages' => $pesan->map(fn ($p) => $this->messenger->serialize($p))->all(),
        ]);
    }

    public function members(Request $request, GrupChat $grup): JsonResponse
    {
        $this->authorize('view', $grup);

        $members = GrupChatMember::with(['user.guru:uuid,id_login,nama', 'user.siswa:uuid,id_login,nama'])
            ->where('grup_id', $grup->uuid)
            ->whereNull('left_at')
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->user_id,
                    'nama' => $m->user?->getNameAttribute() ?? 'Anggota',
                    'peran' => $m->peran,
                    'is_online' => $m->user ? $m->user->isOnline() : false,
                ];
            });

        // Sort by peran: admin, walikelas, guru, orangtua, siswa, then by name
        $sorted = $members->sortBy([
            fn($a) => match($a['peran']) {
                'admin' => 1,
                'walikelas' => 2,
                'guru' => 3,
                'orangtua' => 4,
                'siswa' => 5,
                default => 9,
            },
            'nama',
        ])->values();

        return response()->json($sorted);
    }

    public function store(Request $request, GrupChat $grup): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.GrupChatMessenger::MAX_BODY],
            'reply_to_id' => ['nullable', 'uuid'],
        ], [], ['body' => 'pesan']);

        $body = trim($data['body']);
        abort_if($body === '', 422, 'Pesan tidak boleh kosong.');

        [$user, $member, $replyTo] = $this->siapkanPengirim($grup, $request, $data['reply_to_id'] ?? null);

        $pesan = $this->messenger->kirim($grup, $user, $member?->peran ?? 'admin', $body, $replyTo);

        return response()->json([
            'message' => $this->messenger->serialize($pesan),
            'last_seq' => (int) $pesan->seq,
        ], 201);
    }

    /**
     * Kirim lampiran (foto/berkas), opsional dengan keterangan & balasan.
     *
     * Penyimpanan pakai App\Support\ChatAttachments (disk 'local', prefix chat/) —
     * kelas yang sama dipakai lampiran chatbot AI, jangan duplikasi logikanya di sini.
     */
    public function lampiran(Request $request, GrupChat $grup): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:jpeg,jpg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv'],
            'body' => ['nullable', 'string', 'max:'.GrupChatMessenger::MAX_BODY],
            'reply_to_id' => ['nullable', 'uuid'],
        ], [], ['file' => 'berkas', 'body' => 'keterangan']);

        [$user, $member, $replyTo] = $this->siapkanPengirim($grup, $request, $data['reply_to_id'] ?? null);

        $file = $data['file'];
        $isGambar = str_starts_with((string) $file->getMimeType(), 'image/');

        $attachment = [
            'path' => $isGambar ? ChatAttachments::storeImage($file) : ChatAttachments::storeFile($file),
            'type' => $isGambar ? 'image' : 'file',
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ];

        $body = trim((string) ($data['body'] ?? ''));

        try {
            $pesan = $this->messenger->kirim(
                $grup,
                $user,
                $member?->peran ?? 'admin',
                $body === '' ? null : $body,
                $replyTo,
                $attachment
            );
        } catch (\Throwable $e) {
            // Berkas sudah terlanjur tersimpan di disk sebelum kirim() dipanggil —
            // kalau penulisan pesan gagal, jangan tinggalkan file yatim tanpa
            // baris pesan yang menunjuknya.
            ChatAttachments::delete($attachment['path']);
            throw $e;
        }

        return response()->json([
            'message' => $this->messenger->serialize($pesan),
            'last_seq' => (int) $pesan->seq,
        ], 201);
    }

    /** Unduh/tampilkan lampiran satu pesan. Foto tampil inline, berkas lain dipaksa unduh. */
    public function unduhLampiran(Request $request, GrupChat $grup, GrupChatMessage $pesan)
    {
        $this->authorize('view', $grup);
        abort_unless($pesan->grup_id === $grup->uuid && ! $pesan->isDihapus() && $pesan->attachment_path, 404);

        // Riwayat sebelum bergabung tetap tersembunyi lewat jalur unduh langsung,
        // bukan cuma di daftar pesan — siswa/ortu tidak berhak atas lampiran yang
        // dikirim sebelum mereka jadi anggota.
        $member = $this->member($grup, $request->user());
        abort_unless($pesan->seq >= ($member?->batasSeq() ?? 0), 404);

        $absolute = ChatAttachments::resolveAbsolutePath($pesan->attachment_path);
        abort_unless($absolute, 404);

        if ($pesan->attachment_type === 'image') {
            return response()->file($absolute);
        }

        return response()->download($absolute, $pesan->attachment_name ?: basename($absolute));
    }

    public function hapus(Request $request, GrupChat $grup, GrupChatMessage $pesan): JsonResponse
    {
        abort_unless($pesan->grup_id === $grup->uuid, 404);
        $this->authorize('hapus', [$grup, $pesan]);

        $pesan = $this->messenger->hapus($grup, $pesan, $request->user());

        return response()->json(['message' => $this->messenger->serialize($pesan)]);
    }

    public function badge(Request $request): JsonResponse
    {
        return response()->json(['unread' => GrupChatMenu::unreadTotal($request->user())]);
    }

    // ─────────────────────── internal ───────────────────────

    /**
     * Urutan bersama store()/lampiran(): validasi target balas (jika ada),
     * otorisasi 'reply' atau 'send' sesuai itu, lalu ambil baris keanggotaan
     * pengirim. Dipisah supaya kedua endpoint tidak mengulang dansa yang sama.
     *
     * @return array{0: User, 1: ?GrupChatMember, 2: ?GrupChatMessage}
     */
    private function siapkanPengirim(GrupChat $grup, Request $request, ?string $replyToId): array
    {
        $user = $request->user();
        $replyTo = $this->resolveReply($grup, $replyToId);
        $replyTo ? $this->authorize('reply', [$grup, $replyTo]) : $this->authorize('send', $grup);

        return [$user, $this->member($grup, $user), $replyTo];
    }

    /** Validasi target balas: harus di grup yang sama dan belum dihapus. */
    private function resolveReply(GrupChat $grup, ?string $uuid): ?GrupChatMessage
    {
        if (! $uuid) {
            return null;
        }

        $pesan = GrupChatMessage::where('uuid', $uuid)
            ->where('grup_id', $grup->uuid)
            ->whereNull('deleted_at')
            ->first();

        abort_if(! $pesan, 422, 'Pesan yang dibalas tidak ditemukan.');

        return $pesan;
    }

    /**
     * Boleh membalas (bukan menulis bebas) walau `send` ditolak: mode pengumuman
     * membolehkan siswa/ortu membalas pesan staf saja — lihat GrupChatPolicy::reply().
     * Kalau member staf, $bolehKirim sudah true, jadi ini otomatis false untuknya.
     *
     * isArsip() dicek terpisah dari $bolehKirim: grup yang diarsipkan SEKALIGUS
     * bermode pengumuman membuat $bolehKirim false karena arsip, bukan karena
     * bukan-staf — tanpa cek ini komposer akan terbuka untuk membalas padahal
     * reply() di GrupChatPolicy tetap menolaknya (grup arsip = read-only total).
     */
    private function bolehBalasPengumuman(GrupChat $grup, ?GrupChatMember $member, bool $bolehKirim): bool
    {
        return ! $bolehKirim && ! $grup->isArsip() && $grup->isModePengumuman() && ($member?->can_write ?? false);
    }

    private function member(GrupChat $grup, User $user): ?GrupChatMember
    {
        return GrupChatMember::where('grup_id', $grup->uuid)
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->first();
    }

    /**
     * Majukan watermark baca.
     *
     * last_notified_seq ikut dimajukan supaya digest FCM tidak pernah mengirim push
     * untuk pesan yang sudah dilihat user di layar. Anti-spam ini gratis: user yang
     * sedang membuka grup otomatis tak menghasilkan notifikasi apa pun.
     */
    private function tandaiTerbaca(GrupChat $grup, ?GrupChatMember $member): void
    {
        if (! $member || $member->last_read_seq >= $grup->last_seq) {
            return;
        }

        DB::table('grup_chat_members')
            ->where('uuid', $member->uuid)
            ->update([
                'last_read_seq' => $grup->last_seq,
                'last_read_at' => now(),
                'last_notified_seq' => $grup->last_seq,
                'updated_at' => now(),
            ]);

        $member->last_read_seq = (int) $grup->last_seq;
    }
}
