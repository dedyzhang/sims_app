<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Digest pesan Grup Chat yang belum dibaca. Satu notifikasi per user per
 * jadwal grupchat:kirim-notif walau ia punya pesan baru di beberapa grup
 * sekaligus — supaya bell/push tidak dibanjiri satu notifikasi per grup.
 */
class GrupChatDigestNotification extends Notification
{
    use Queueable;

    /** @param array<int, array{grup_id: string, nama: string, unread: int, preview: ?string, oleh: ?string}> $groups */
    public function __construct(public array $groups)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /** Payload data-only untuk FCM; reuse judul/pesan/url dari toArray(). */
    public function toFcm(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title' => $data['judul'],
            'message' => $data['message'],
            'url' => $data['url'],
            'type' => $data['type'],
        ];
    }

    public function toArray(object $notifiable): array
    {
        if (count($this->groups) === 1) {
            return $this->satuGrup($this->groups[0]);
        }

        return $this->banyakGrup();
    }

    /** @param array{grup_id: string, nama: string, unread: int, preview: ?string, oleh: ?string} $grup */
    private function satuGrup(array $grup): array
    {
        $preview = trim((string) ($grup['preview'] ?? ''));
        $message = match (true) {
            $preview === '' => "{$grup['unread']} pesan baru",
            (bool) $grup['oleh'] => $grup['oleh'].': '.Str::limit($preview, 100),
            default => Str::limit($preview, 100),
        };

        return [
            'type' => 'grup_chat_digest',
            'groups' => $this->groups,
            'judul' => $grup['nama'],
            'message' => $message,
            'url' => '/grup/'.$grup['grup_id'],
        ];
    }

    private function banyakGrup(): array
    {
        $totalUnread = array_sum(array_column($this->groups, 'unread'));
        $namaGrup = collect($this->groups)->pluck('nama')->implode(', ');

        return [
            'type' => 'grup_chat_digest',
            'groups' => $this->groups,
            'judul' => 'Pesan baru di Grup Chat',
            'message' => "{$totalUnread} pesan baru di ".count($this->groups).' grup: '.Str::limit($namaGrup, 100),
            'url' => '/grup',
        ];
    }
}
