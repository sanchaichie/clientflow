<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\User;

/** Sent when someone is added to a project, or made its manager. */
class ProjectAssigned extends ClientFlowNotification
{
    public function __construct(public Project $project, public ?User $actor, public bool $asManager = false) {}

    public function toArray(object $notifiable): array
    {
        $role = $this->asManager ? 'manager of' : 'a member of';

        return [
            'kind' => 'project_assigned',
            'message' => ($this->actor?->name ?? 'Someone')." made you {$role} \"{$this->project->name}\"",
            'url' => "/projects/{$this->project->id}",
            'project_id' => $this->project->id,
            'actor' => $this->actor?->name,
        ];
    }
}
