<?php

namespace App\Services;

use App\Models\GrupChat;
use App\Models\GrupChatMember;
use App\Models\GrupChatMessage;
use App\Models\User;
use App\Support\ChatAttachments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Penulisan pesan grup + serialisasi untuk endpoint poll.
 *
 * PENTING: request kirim pesan sengaja TIDAK menyentuh FCM sama sekali. Aplikasi
 * ini tidak menjalankan queue worker dan FCM_QUEUE_CONNECTION dipaksa 'sync'
 * (lihat config/services.php + app/Console/Commands/DiagnoseFcm.php), jadi mengirim
 * push ke ~60 anggota di dalam request akan memakan 15-50 detik dan menabrak
 * max_execution_time di shared hosting. Notifikasi ditangani command terjadwal
 * grupchat:kirim-notif secara digest (Fase 4).
 */
class GrupChatMessenger
{
    /** Batas panjang pesan — cukup untuk paragraf pengumuman, mencegah abuse. */
    public const MAX_BODY = 4000;

    /**
     * Tulis satu pesan. Counter `seq` diambil di bawah lockForUpdate supaya dua
     * pengirim bersamaan tidak pernah menghasilkan seq kembar (unique grup_id+seq
     * akan menolak keras kalau sampai bocor).
     *
     * Otorisasi dicek DI SINI juga (bukan cuma di GrupChatController), sengaja
     * redundan: ini satu-satunya primitive yang benar-benar menulis pesan, jadi
     * pemanggil MANA PUN — controller sekarang, atau command/tool/agent lain nanti
     * yang memanggil service ini langsung — tidak bisa melewati aturan mode
     * pengumuman/reply-ke-staf hanya karena ia tidak lewat controller.
     */
    public function kirim(
        GrupChat $grup,
        User $user,
        string $peran,
        ?string $body,
        ?GrupChatMessage $replyTo = null,
        array $attachment = []
    ): GrupChatMessage {
        $replyTo
            ? Gate::forUser($user)->authorize('reply', [$grup, $replyTo])
            : Gate::forUser($user)->authorize('send', $grup);

        $nama = $user->displayName();

        return DB::transaction(function () use ($grup, $user, $peran, $body, $replyTo, $attachment, $nama) {
            $locked = GrupChat::whereKey($grup->uuid)->lockForUpdate()->first();
            $seq = ((int) $locked->last_seq) + 1;

            $pesan = GrupChatMessage::create([
                'grup_id' => $grup->uuid,
                'seq' => $seq,
                'user_id' => $user->getKey(),
                'sender_nama' => Str::limit($nama, 78, ''),
                'sender_peran' => $peran,
                'body' => $body,
                'reply_to_id' => $replyTo?->uuid,
                'reply_snippet' => $replyTo ? Str::limit((string) $replyTo->bodyTampil(), 155) : null,
                'reply_nama' => $replyTo?->sender_nama,
                'attachment_path' => $attachment['path'] ?? null,
                'attachment_type' => $attachment['type'] ?? null,
                'attachment_name' => $attachment['name'] ?? null,
                'attachment_size' => $attachment['size'] ?? null,
            ]);

            $locked->update([
                'last_seq' => $seq,
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($this->preview($pesan), 155),
                'last_message_by' => $pesan->sender_nama,
            ]);

            // Pengirim otomatis dianggap sudah baca DAN sudah dinotif, sehingga
            // digest tidak pernah mengirim push balik ke dirinya sendiri.
            GrupChatMember::where('grup_id', $grup->uuid)
                ->where('user_id', $user->getKey())
                ->update([
                    'last_read_seq' => $seq,
                    'last_read_at' => now(),
                    'last_notified_seq' => $seq,
                ]);

            $grup->refresh();

            return $pesan;
        });
    }

    /**
     * Moderasi: tandai pesan terhapus, hapus berkas lampiran dari disk, bersihkan
     * kutipan balasan yang mengutipnya, dan perbarui preview grup bila pesan ini
     * yang terakhir — supaya daftar grup & kutipan balasan tidak membocorkan isi
     * pesan yang sudah dimoderasi.
     *
     * Sama seperti kirim(): otorisasi dicek di sini juga, bukan cuma di controller.
     */
    public function hapus(GrupChat $grup, GrupChatMessage $pesan, User $penghapus): GrupChatMessage
    {
        Gate::forUser($penghapus)->authorize('hapus', [$grup, $pesan]);

        $pathLama = null;

        $locked = DB::transaction(function () use ($grup, $pesan, $penghapus, &$pathLama) {
            $locked = GrupChatMessage::whereKey($pesan->uuid)->lockForUpdate()->first();

            // Idempoten: dua permintaan hapus beruntun (klik ganda, race) tidak
            // boleh saling menimpa deleted_by/deleted_at satu sama lain.
            if ($locked->deleted_at !== null) {
                return $locked;
            }

            $pathLama = $locked->attachment_path;

            $locked->update([
                'deleted_at' => now(),
                'deleted_by' => $penghapus->getKey(),
                'attachment_path' => null,
                'attachment_type' => null,
                'attachment_name' => null,
                'attachment_size' => null,
            ]);

            // Tanpa ini, pesan yang sudah dimoderasi tetap bocor utuh lewat kutipan
            // di setiap balasan yang mengutipnya — reply_snippet/reply_nama adalah
            // snapshot permanen yang dibuat saat balasan dikirim, bukan live lookup.
            GrupChatMessage::where('reply_to_id', $locked->uuid)
                ->update(['reply_snippet' => 'Pesan ini dihapus.', 'reply_nama' => null]);

            // Kunci ulang grup DI DALAM transaksi ini (bukan pakai $grup dari awal
            // request) — kalau tidak, pesan baru yang masuk tepat di antara request
            // dimulai dan hapus() dieksekusi membuat perbandingan seq ini memakai
            // last_seq basi, salah menimpa preview grup.
            $lockedGrup = GrupChat::whereKey($grup->uuid)->lockForUpdate()->first();
            if ((int) $locked->seq === (int) $lockedGrup->last_seq) {
                $lockedGrup->update(['last_message_preview' => 'Pesan ini dihapus.']);
            }

            return $locked;
        });

        // Di LUAR transaksi: kalau commit gagal setelah baris ini, file lampiran
        // tetap ada di disk (bukan terlanjur terhapus lalu di-rollback sehingga DB
        // bilang "belum dihapus" padahal filenya sudah hilang selamanya).
        if ($pathLama) {
            ChatAttachments::delete($pathLama);
        }

        return $locked;
    }

    /** Teks ringkas untuk daftar grup & isi notifikasi. */
    public function preview(GrupChatMessage $pesan): string
    {
        if ($pesan->body !== null && trim($pesan->body) !== '') {
            return trim($pesan->body);
        }

        return $pesan->attachment_type === 'image' ? '📷 Foto' : '📎 Berkas';
    }

    /** Bentuk JSON satu pesan untuk endpoint poll & render awal. */
    public function serialize(GrupChatMessage $pesan): array
    {
        $dihapus = $pesan->isDihapus();

        return [
            'uuid' => $pesan->uuid,
            'seq' => (int) $pesan->seq,
            'user_id' => $pesan->user_id,
            'nama' => $pesan->sender_nama,
            'peran' => $pesan->sender_peran,
            'body' => $pesan->bodyTampil(),
            'dihapus' => $dihapus,
            'reply_nama' => $dihapus ? null : $pesan->reply_nama,
            'reply_snippet' => $dihapus ? null : $pesan->reply_snippet,
            // Lampiran pada pesan yang dihapus tidak pernah diekspos.
            'lampiran' => $dihapus || ! $pesan->attachment_path ? null : [
                'tipe' => $pesan->attachment_type,
                'nama' => $pesan->attachment_name,
                'ukuran' => (int) $pesan->attachment_size,
                'url' => route('grup.lampiran.unduh', [$pesan->grup_id, $pesan->uuid]),
            ],
            'waktu' => $pesan->created_at?->toIso8601String(),
            'jam' => $pesan->created_at?->format('H:i'),
            'tanggal' => $pesan->created_at?->format('Y-m-d'),
        ];
    }
}
