<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectAssigned;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly NotificationDispatcher $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated StoreProjectRequest data
     */
    public function create(array $data, User $creator): Project
    {
        return DB::transaction(function () use ($data, $creator) {
            $project = new Project($data);
            // A PM's project is theirs; admins chose a manager in the request.
            $project->manager_id = $creator->isAdmin() ? $data['manager_id'] : $creator->id;
            $project->save();

            $this->activity->log($creator, 'project.created', $project);

            // Admin created it for someone else: tell the new manager.
            $this->notifier->send([$project->manager], new ProjectAssigned($project, $creator, asManager: true), $creator);

            if (! empty($data['member_ids'])) {
                $this->attachMembers($project, $data['member_ids'], $creator);
            }

            return $project;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated UpdateProjectRequest data
     */
    public function update(Project $project, array $data, User $actor): Project
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            // manager_id is only present when an admin sent it (UpdateProjectRequest).
            $project->fill($data);

            $statusChanged = $project->isDirty('status');
            $previousStatus = $project->getOriginal('status');
            $managerChanged = $project->isDirty('manager_id');
            $otherFields = array_keys(array_diff_key($project->getDirty(), array_flip(['status', 'manager_id'])));

            $project->save();

            if ($statusChanged) {
                $this->activity->log($actor, 'project.status_changed', $project, [
                    'from' => $previousStatus?->value,
                    'to' => $project->status->value,
                ]);
            }
            if ($managerChanged) {
                $project->load('manager'); // the cached relation may still point to the old manager
                $this->activity->log($actor, 'project.manager_changed', $project, ['to' => $project->manager->name]);
                $this->notifier->send([$project->manager], new ProjectAssigned($project, $actor, asManager: true), $actor);
            }
            if ($otherFields) {
                $this->activity->log($actor, 'project.updated', $project, ['fields' => $otherFields]);
            }

            return $project;
        });
    }

    public function archive(Project $project, User $actor): void
    {
        DB::transaction(function () use ($project, $actor) {
            $project->forceFill(['archived_at' => now()])->save();
            $this->activity->log($actor, 'project.archived', $project);
        });
    }

    public function restore(Project $project, User $actor): void
    {
        DB::transaction(function () use ($project, $actor) {
            $project->forceFill(['archived_at' => null])->save();
            $this->activity->log($actor, 'project.restored', $project);
        });
    }

    /** @param  list<int>  $userIds */
    public function addMembers(Project $project, array $userIds, User $actor): void
    {
        DB::transaction(fn () => $this->attachMembers($project, $userIds, $actor));
    }

    /**
     * Removing a member also unassigns their open tasks in this project, so
     * no task stays assigned to someone who can no longer see it.
     */
    public function removeMember(Project $project, User $user, User $actor): void
    {
        DB::transaction(function () use ($project, $user, $actor) {
            $project->members()->detach($user->id);
            $project->tasks()->where('assignee_id', $user->id)->update(['assignee_id' => null]);
            $this->activity->log($actor, 'project.member_removed', $project, ['member' => $user->name]);
        });
    }

    /**
     * Attach only users who aren't members yet; log and notify just those.
     *
     * @param  list<int>  $userIds
     */
    private function attachMembers(Project $project, array $userIds, User $actor): void
    {
        $result = $project->members()->syncWithoutDetaching($userIds);
        $added = User::query()->whereKey($result['attached'])->orderBy('name')->get();

        if ($added->isEmpty()) {
            return;
        }

        $this->activity->log($actor, 'project.members_added', $project, ['members' => $added->pluck('name')->all()]);
        $this->notifier->send($added, new ProjectAssigned($project, $actor), $actor);
    }
}
