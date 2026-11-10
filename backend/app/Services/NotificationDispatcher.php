<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Sends a notification to a set of users, skipping duplicates, missing users,
 * deactivated users, and the person who performed the action (nobody needs
 * to be told about their own change).
 */
class NotificationDispatcher
{
    /** @param  iterable<User|null>  $recipients */
    public function send(iterable $recipients, Notification $notification, ?User $actor = null): void
    {
        $users = collect($recipients)
            ->filter(fn (?User $u) => $u !== null && $u->is_active && $u->id !== $actor?->id)
            ->unique('id')
            ->values();

        if ($users->isNotEmpty()) {
            NotificationFacade::send($users, $notification);
        }
    }
}
