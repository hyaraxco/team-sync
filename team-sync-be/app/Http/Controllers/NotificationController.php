<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\NotificationIndexRequest;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function getUnreadCount(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 401);
        }

        $unreadCount = $user->unreadNotifications()->count();

        return ResponseHelper::jsonResponse(
            true,
            'Unread Notification Count Retrieved Successfully',
            ['unread_count' => $unreadCount],
            200
        );
    }

    public function getMyNotifications(NotificationIndexRequest $request)
    {
        $user = $request->user();

        if (! $user) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 401);
        }

        if ($request->hasAny(['page', 'per_page'])) {
            $perPage = (int) ($request->validated('per_page') ?? $request->validated('limit') ?? 10);
            $page = (int) ($request->validated('page') ?? 1);

            $paginator = $user->notifications()
                ->latest()
                ->paginate($perPage, ['*'], 'page', $page);

            return ResponseHelper::jsonResponse(
                true,
                'Notifications Retrieved Successfully',
                [
                    'items' => NotificationResource::collection($paginator->items()),
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                    ],
                ],
                200
            );
        }

        $limit = (int) ($request->validated('limit') ?? 5);
        $notifications = $user->notifications()
            ->latest()
            ->limit($limit)
            ->get();

        return ResponseHelper::jsonResponse(
            true,
            'Notifications Retrieved Successfully',
            NotificationResource::collection($notifications),
            200
        );
    }

    public function markAllAsRead(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 401);
        }

        $count = $user->unreadNotifications()->count();
        $user->unreadNotifications->markAsRead();

        return ResponseHelper::jsonResponse(
            true,
            "{$count} notifications marked as read",
            ['marked_count' => $count],
            200
        );
    }

    public function markAsRead(Request $request, string $notificationId)
    {
        $user = $request->user();

        if (! $user) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 401);
        }

        $notification = $user->notifications()
            ->where('id', $notificationId)
            ->first();

        if (! $notification) {
            return ResponseHelper::jsonResponse(false, 'Notification Not Found', null, 404);
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
            $notification->refresh();
        }

        return ResponseHelper::jsonResponse(
            true,
            'Notification Marked As Read',
            new NotificationResource($notification),
            200
        );
    }
}
