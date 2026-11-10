<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProjectService
{
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

            if (! empty($data['member_ids'])) {
                $project->members()->attach($data['member_ids']);
            }

            return $project;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated UpdateProjectRequest data
     */
    public function update(Project $project, array $data): Project
    {
        // manager_id is only present when an admin sent it (UpdateProjectRequest).
        $project->fill($data)->save();

        return $project;
    }

    public function archive(Project $project): void
    {
        $project->forceFill(['archived_at' => now()])->save();
    }

    public function restore(Project $project): void
    {
        $project->forceFill(['archived_at' => null])->save();
    }

    /** @param  list<int>  $userIds */
    public function addMembers(Project $project, array $userIds): void
    {
        $project->members()->syncWithoutDetaching($userIds);
    }

    /**
     * Removing a member also unassigns their open tasks in this project, so
     * no task stays assigned to someone who can no longer see it.
     */
    public function removeMember(Project $project, User $user): void
    {
        DB::transaction(function () use ($project, $user) {
            $project->members()->detach($user->id);
            $project->tasks()->where('assignee_id', $user->id)->update(['assignee_id' => null]);
        });
    }
}
