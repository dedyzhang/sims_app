<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserFcmToken;
use App\Support\NotificationGate;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class NotificationController extends Controller
{
    /** Get notifications JSON */
    public function getNotifications(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false], 401);
        }

        // Tandai/bersihkan sampah unread yang gagal gate agar tidak memenuhi window.
        $this->purgeInaccessibleUnread($user);

        $feed = $this->visibleNotifications($user, unreadOnly: false, limit: 20);
        $unreadStats = $this->unreadStats($user);

        $formatted = $feed->map(function ($n) {
            return [
                'id' => $n->id,
                'read_at' => $n->read_at,
                'data' => $n->data,
                'time_ago' => $n->created_at->locale('id')->diffForHumans(),
            ];
        });

        return response()->json([
            'ok' => true,
            'notifications' => $formatted,
            'unreadCount' => $unreadStats['unread'],
            'unreadPengumuman' => $unreadStats['pengumuman'],
        ]);
    }

    /** Mark single notification as read */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->find($id);
        if ($notification && NotificationGate::userCanView($user, (array) ($notification->data ?? []))) {
            $notification->markAsRead();
        }

        return response()->json(['ok' => true]);
    }

    /** Mark all notifications as read */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();

        // Sampah yang gagal gate ikut ditandai dibaca supaya tidak mengunci badge/feed.
        $this->purgeInaccessibleUnread($user);

        // Setelah purge, sisa unread adalah yang boleh dilihat — tandai semua.
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * Query dasar: exclude tipe yang jelas tidak relevan bagi peran user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany|\Illuminate\Database\Eloquent\Builder
     */
    private function baseQuery(User $user, bool $unreadOnly = false)
    {
        $query = $unreadOnly ? $user->unreadNotifications() : $user->notifications();

        $excluded = NotificationGate::excludedTypesFor($user);
        if ($excluded !== []) {
            $query->where(function ($q) use ($excluded) {
                $q->whereNotIn('data->type', $excluded)
                    ->orWhereNull('data->type');
            });
        }

        return $query;
    }

    /** @return Collection<int, DatabaseNotification> */
    private function visibleNotifications(User $user, bool $unreadOnly = false, int $limit = 20): Collection
    {
        // Ambil lebih banyak dari limit, filter gate, lalu potong — window besar
        // karena sampah role-impossible sudah di-exclude di SQL.
        $chunk = $this->baseQuery($user, $unreadOnly)
            ->orderBy('created_at', 'desc')
            ->take(max($limit * 5, 100))
            ->get();

        $preload = NotificationGate::preload($user, $chunk);

        return $chunk
            ->filter(fn (DatabaseNotification $n) => NotificationGate::userCanView($user, (array) ($n->data ?? []), $preload))
            ->take($limit)
            ->values();
    }

    /**
     * Hitung unread tanpa cap take(20) pada feed.
     *
     * @return array{unread:int, pengumuman:int}
     */
    private function unreadStats(User $user): array
    {
        $unread = 0;
        $pengumuman = 0;

        $this->baseQuery($user, unreadOnly: true)
            ->orderBy('created_at', 'desc')
            ->chunkById(200, function (Collection $chunk) use ($user, &$unread, &$pengumuman) {
                $preload = NotificationGate::preload($user, $chunk);
                foreach ($chunk as $n) {
                    if (! NotificationGate::userCanView($user, (array) ($n->data ?? []), $preload)) {
                        continue;
                    }
                    $unread++;
                    if (($n->data['type'] ?? null) === 'pengumuman') {
                        $pengumuman++;
                    }
                }
            });

        return ['unread' => $unread, 'pengumuman' => $pengumuman];
    }

    /**
     * Tandai dibaca semua unread yang gagal gate (termasuk yang sudah di-exclude SQL
     * tapi masih tersimpan dari kebocoran historis).
     */
    private function purgeInaccessibleUnread(User $user): void
    {
        // Ambil SEMUA unread user (tanpa exclude SQL) agar sampah historis ikut dibersihkan.
        $user->unreadNotifications()
            ->orderBy('created_at', 'desc')
            ->chunkById(200, function (Collection $chunk) use ($user) {
                $preload = NotificationGate::preload($user, $chunk);
                foreach ($chunk as $n) {
                    if (! NotificationGate::userCanView($user, (array) ($n->data ?? []), $preload)) {
                        $n->markAsRead();
                    }
                }
            });
    }

    /** Simpan/registrasi token FCM dari perangkat (dipanggil Android via WebView). */
    public function storeFcmToken(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false], 401);
        }

        $data = $request->validate([
            'token' => 'required|string',
            'device_type' => 'nullable|string',
        ]);

        // Upsert per token (token unik global): bila perangkat yang sama login
        // sebagai user lain, token berpindah ke user tersebut — tanpa langgar unique.
        UserFcmToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_uuid' => $user->uuid, 'device_type' => $data['device_type'] ?? null],
        );

        return response()->json(['ok' => true]);
    }

    /** Hapus token saat logout (best-effort, tak pernah error ke pemanggil). */
    public function destroyFcmToken(Request $request)
    {
        $user = $request->user();
        $token = $request->input('token');

        if ($user && $token) {
            UserFcmToken::where('user_uuid', $user->uuid)
                ->where('token', $token)
                ->delete();
        }

        return response()->json(['ok' => true]);
    }
}
