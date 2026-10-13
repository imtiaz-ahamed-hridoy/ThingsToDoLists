<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notifications = $user->unreadNotifications;

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    public function markAsRead(Request $request, $id = null)
    {
        $user = $request->user();
        if ($user->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($id) {
            $user->unreadNotifications->where('id', $id)->markAsRead();
        } else {
            $user->unreadNotifications->markAsRead();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifications marked as read'
        ]);
    }
}
