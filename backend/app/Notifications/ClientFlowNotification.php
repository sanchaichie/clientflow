<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Base class for ClientFlow's in-app notifications (database channel only).
 *
 * Not queued on purpose: writing one row is cheap, and it happens inside the
 * same transaction as the change that caused it, so a rolled-back change
 * never leaves a notification behind. (A queued notification would also need
 * a running queue worker.)
 *
 * Every notification stores the same shape so the frontend can render any
 * of them: { kind, message, url, task_id?, project_id?, actor? }.
 */
abstract class ClientFlowNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    abstract public function toArray(object $notifiable): array;
}
