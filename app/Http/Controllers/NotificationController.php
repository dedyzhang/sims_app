<?php

namespace App\Http\Controllers;

use App\Models\UserFcmToken;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Get notifications JSON */
    public function getNotifications(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false], 401);
        }

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        $formatted = $notifications->map(function ($n) {
            return [
                'id' => $n->id,
                'read_at' => $n->read_at,
                'data' => $n->data,
                'time_ago' => $n->created_at->locale('id')->diffForHumans(),
            ];
        });

        $unreadCount = $user->unreadNotifications()->count();

        // Khusus pengumuman resmi → dipakai badge di menu sidebar "Pengumuman".
        $unreadPengumuman = $user->unreadNotifications()
            ->where('data->type', 'pengumuman')
            ->count();

        return response()->json([
            'ok' => true,
            'notifications' => $formatted,
            'unreadCount' => $unreadCount,
            'unreadPengumuman' => $unreadPengumuman,
        ]);
    }

    /** Mark single notification as read */
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->find($id);
        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['ok' => true]);
    }

    /** Mark all notifications as read */
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }

    /** Simpan/registrasi token FCM dari perangkat (dipanggil Android via WebView). */
    public function storeFcmToken(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false], 401);
        }

        $data = $request->validate([
            'token'       => 'required|string',
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
