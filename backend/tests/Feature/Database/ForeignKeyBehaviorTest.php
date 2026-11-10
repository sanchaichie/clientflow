<?php

namespace Tests\Feature\Database;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Verifies the on-delete rules documented in docs/ERD.md. */
class ForeignKeyBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_with_projects_cannot_be_hard_deleted(): void
    {
        $project = Project::factory()->create();

        $this->expectException(QueryException::class);
        $project->client->delete();
    }

    public function test_a_user_who_manages_a_project_cannot_be_deleted(): void
    {
        $project = Project::factory()->create();

        $this->expectException(QueryException::class);
        $project->manager->delete();
    }

    public function test_deleting_a_project_removes_its_tasks_comments_and_memberships(): void
    {
        $project = Project::factory()->create();
        $project->members()->attach(User::factory()->create());
        $task = Task::factory()->for($project)->create();
        TaskComment::factory()->for($task)->create();

        $project->delete();

        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('task_comments', 0);
        $this->assertDatabaseCount('project_members', 0);
    }

    public function test_deleting_a_user_keeps_their_tasks_and_history(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['assignee_id' => $user->id, 'created_by' => $user->id]);
        $client = Client::factory()->create(['account_manager_id' => $user->id]);
        $log = ActivityLog::create([
            'user_id' => $user->id,
            'project_id' => $task->project_id,
            'subject_type' => $task->getMorphClass(),
            'subject_id' => $task->id,
            'action' => 'task.created',
        ]);

        $user->delete();

        $this->assertNull($task->fresh()->assignee_id);
        $this->assertNull($task->fresh()->created_by);
        $this->assertNull($client->fresh()->account_manager_id);
        $this->assertNull($log->fresh()->user_id);
    }

    public function test_a_user_can_join_a_project_only_once(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $project->members()->attach($user);

        $this->expectException(QueryException::class);
        $project->members()->attach($user);
    }
}
