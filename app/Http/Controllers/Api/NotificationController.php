<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UnreadNotificationCount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'unread' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = $request->user()->notifications()->latest();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate(15)->withQueryString();

        return response()->json([
            'data' => collect($notifications->items())->map(static fn ($notification): array => [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => $notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ]),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id, UnreadNotificationCount $counts): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();
        $counts->forget($request->user());

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllRead(Request $request, UnreadNotificationCount $counts): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        $counts->forget($request->user());

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function unreadCount(Request $request, UnreadNotificationCount $counts): JsonResponse
    {
        return response()->json(['count' => $counts->count($request->user())]);
    }
}
