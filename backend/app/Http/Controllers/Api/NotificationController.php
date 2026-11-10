<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * In-app notifications of the current user. Every query goes through
 * $user->notifications(), so nobody can read or mark someone else's.
 */
class NotificationController extends Controller
{
    /** GET /api/notifications?unread=1&per_page= — includes meta.unread_count. */
    public function index(ListNotificationsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->paginate($request->validated('per_page') ?? 15)
            ->withQueryString();

        return NotificationResource::collection($notifications)
            ->additional(['meta' => ['unread_count' => $user->unreadNotifications()->count()]]);
    }

    /** GET /api/notifications/unread-count — cheap endpoint for the bell's polling. */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['unread_count' => $request->user()->unreadNotifications()->count()]]);
    }

    /** PATCH /api/notifications/{id}/read */
    public function markRead(Request $request, string $id): NotificationResource
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return new NotificationResource($notification);
    }

    /** POST /api/notifications/read-all */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['data' => ['unread_count' => 0]]);
    }
}
