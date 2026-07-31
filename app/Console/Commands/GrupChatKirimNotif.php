<?php

namespace App\Console\Commands;

use App\Models\GrupChatMember;
use App\Models\User;
use App\Notifications\GrupChatDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Kirim notifikasi digest untuk pesan Grup Chat yang belum dibaca & belum
 * dinotifikasi. Dijadwalkan berkala (bukan real-time per pesan) — lihat
 * catatan di GrupChatMessenger::kirim(): kirim pesan sengaja TIDAK menyentuh
 * FCM sama sekali karena app ini tidak punya queue worker & FCM_QUEUE_CONNECTION
 * dipaksa 'sync', sehingga push ke puluhan/ratusan anggota di dalam request
 * kirim pesan akan menabrak max_execution_time di shared hosting.
 *
 * last_notified_seq adalah watermark yang SUDAH dimajukan di dua tempat lain:
 * GrupChatController::tandaiTerbaca() (user membuka/poll grupnya sendiri) dan
 * GrupChatMessenger::kirim() (pengirim, supaya tidak dinotif pesannya sendiri).
 * Command ini hanya menjaring sisanya: anggota yang belum melihat layar chat
 * sama sekali sejak pesan baru masuk.
 */
class GrupChatKirimNotif extends Command
{
    protected $signature = 'grupchat:kirim-notif';

    protected $description = 'Kirim notifikasi digest pesan Grup Chat yang belum dibaca';

    public function handle(): int
    {
        $baris = GrupChatMember::query()
            ->join('grup_chats', 'grup_chats.uuid', '=', 'grup_chat_members.grup_id')
            ->whereNull('grup_chat_members.left_at')
            ->where('grup_chats.status', 'aktif')
            ->whereColumn('grup_chats.last_seq', '>', 'grup_chat_members.last_notified_seq')
            ->where(function ($q) {
                $q->whereNull('grup_chat_members.muted_until')
                    ->orWhere('grup_chat_members.muted_until', '<', now());
            })
            ->get([
                'grup_chat_members.uuid as member_uuid',
                'grup_chat_members.user_id',
                'grup_chat_members.last_notified_seq',
                'grup_chats.uuid as grup_id',
                'grup_chats.nama as grup_nama',
                'grup_chats.last_seq',
                'grup_chats.last_message_preview',
                'grup_chats.last_message_by',
            ]);

        if ($baris->isEmpty()) {
            $this->info('Tidak ada digest untuk dikirim.');

            return self::SUCCESS;
        }

        $users = User::whereIn('uuid', $baris->pluck('user_id')->unique())->get()->keyBy('uuid');
        $terkirim = 0;

        foreach ($baris->groupBy('user_id') as $userId => $rows) {
            $user = $users->get($userId);
            if (! $user) {
                continue;
            }

            $groups = $rows->map(fn ($r) => [
                'grup_id' => $r->grup_id,
                'nama' => $r->grup_nama,
                'unread' => (int) $r->last_seq - (int) $r->last_notified_seq,
                'preview' => $r->last_message_preview,
                'oleh' => $r->last_message_by,
            ])->values()->all();

            $user->notify(new GrupChatDigestNotification($groups));
            $terkirim++;
        }

        // Majukan watermark SETELAH semua notifikasi terkirim, satu per baris
        // (member, grup) memakai last_seq milik grupnya sendiri -- bukan
        // subquery lintas-DB yang sintaksnya beda-beda antara SQLite/MySQL.
        foreach ($baris as $r) {
            DB::table('grup_chat_members')
                ->where('uuid', $r->member_uuid)
                ->update([
                    'last_notified_seq' => $r->last_seq,
                    'last_notified_at' => now(),
                ]);
        }

        $this->info("Digest terkirim ke {$terkirim} user ({$baris->count()} grup).");

        return self::SUCCESS;
    }
}
