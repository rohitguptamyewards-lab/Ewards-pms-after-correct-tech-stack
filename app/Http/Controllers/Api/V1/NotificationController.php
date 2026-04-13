<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Get notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $notifications = DB::table('notifications')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $unreadCount = DB::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(int $id): JsonResponse
    {
        DB::table('notifications')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Marked as read']);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(): JsonResponse
    {
        DB::table('notifications')
            ->where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All marked as read']);
    }

    /**
     * Helper: Create a notification for a user.
     */
    public static function notify(int $userId, string $type, string $title, ?string $message = null, ?array $data = null): void
    {
        DB::table('notifications')->insert([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'data'       => $data ? json_encode($data) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Helper: Notify multiple users.
     */
    public static function notifyMany(array $userIds, string $type, string $title, ?string $message = null, ?array $data = null): void
    {
        $now = now();
        $rows = [];
        foreach (array_unique($userIds) as $uid) {
            if (!$uid) continue;
            $rows[] = [
                'user_id'    => $uid,
                'type'       => $type,
                'title'      => $title,
                'message'    => $message,
                'data'       => $data ? json_encode($data) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows) {
            DB::table('notifications')->insert($rows);
        }
    }
}
