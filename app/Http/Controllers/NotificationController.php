<?php

namespace App\Http\Controllers;

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

        return response()->json([
            'ok' => true,
            'notifications' => $formatted,
            'unreadCount' => $unreadCount,
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
}
