<?php

namespace Tests\Feature\Collaboration;

use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;

    private User $member;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pm = User::factory()->projectManager()->create();
        $this->member = User::factory()->teamMember()->create();
        $this->project = Project::factory()->active()->create(['manager_id' => $this->pm->id]);
        $this->project->members()->attach($this->member);
    }

    private function actions(): array
    {
        return ActivityLog::query()->orderBy('id')->pluck('action')->all();
    }

    public function test_task_lifecycle_is_recorded_with_who_and_what(): void
    {
        $id = $this->actingAs($this->pm)->postJson('/api/tasks', [
            'project_id' => $this->project->id, 'title' => 'Write copy', 'assignee_id' => $this->member->id,
        ])->json('data.id');

        $this->actingAs($this->member)->patchJson("/api/tasks/{$id}/status", ['status' => 'review']);
        $this->actingAs($this->member)->postJson("/api/tasks/{$id}/comments", ['body' => 'Ready']);
        $this->actingAs($this->pm)->deleteJson("/api/tasks/{$id}");

        $this->assertSame(['task.created', 'task.status_changed', 'task.commented', 'task.deleted'], $this->actions());

        $move = ActivityLog::firstWhere('action', 'task.status_changed');
        $this->assertSame($this->member->id, $move->user_id);
        $this->assertSame($this->project->id, $move->project_id);
        // assertEquals: MySQL's JSON type does not preserve key order.
        $this->assertEquals(['title' => 'Write copy', 'from' => 'todo', 'to' => 'review'], $move->properties);
    }

    public function test_reassigning_logs_from_and_to_names(): void
    {
        $other = User::factory()->teamMember()->create(['name' => 'Bea']);
        $this->project->members()->attach($other);
        $task = Task::factory()->for($this->project)->create(['assignee_id' => $this->member->id]);

        $this->actingAs($this->pm)->putJson("/api/tasks/{$task->id}", ['assignee_id' => $other->id])->assertOk();

        $log = ActivityLog::firstWhere('action', 'task.assigned');
        $this->assertSame($this->member->name, $log->properties['from']);
        $this->assertSame('Bea', $log->properties['to']);
    }

    public function test_project_changes_are_recorded(): void
    {
        $newcomer = User::factory()->teamMember()->create(['name' => 'Nia']);

        $this->actingAs($this->pm)->putJson("/api/projects/{$this->project->id}", ['status' => 'on_hold']);
        $this->actingAs($this->pm)->postJson("/api/projects/{$this->project->id}/members", ['user_ids' => [$newcomer->id, $this->member->id]]);
        $this->actingAs($this->pm)->deleteJson("/api/projects/{$this->project->id}/members/{$newcomer->id}");
        $this->actingAs($this->pm)->deleteJson("/api/projects/{$this->project->id}");

        $this->assertSame(
            ['project.status_changed', 'project.members_added', 'project.member_removed', 'project.archived'],
            $this->actions(),
        );
        // Only the genuinely new member is listed (the existing one was skipped).
        $this->assertSame(['Nia'], ActivityLog::firstWhere('action', 'project.members_added')->properties['members']);
    }

    public function test_feed_only_shows_activity_from_visible_projects(): void
    {
        // Explicit "todo": a random factory status could already be "completed",
        // making the move a no-op that logs nothing.
        $task = Task::factory()->for($this->project)->withStatus(TaskStatus::Todo)->create();
        $this->actingAs($this->pm)->patchJson("/api/tasks/{$task->id}/status", ['status' => 'completed']);
        $otherProject = Project::factory()->create();
        $foreign = Task::factory()->for($otherProject)->withStatus(TaskStatus::Todo)->create();
        $this->actingAs($otherProject->manager)->patchJson("/api/tasks/{$foreign->id}/status", ['status' => 'completed']);

        $this->actingAs($this->member)->getJson('/api/activity')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.project_id', $this->project->id);
        $this->actingAs(User::factory()->teamMember()->create())->getJson('/api/activity')->assertJsonCount(0, 'data');
        $this->actingAs(User::factory()->admin()->create())->getJson('/api/activity')->assertJsonCount(2, 'data');
    }

    public function test_feed_can_be_filtered_to_one_task(): void
    {
        [$a, $b] = Task::factory(2)->for($this->project)->withStatus(TaskStatus::Todo)->create();
        $this->actingAs($this->pm)->patchJson("/api/tasks/{$a->id}/status", ['status' => 'review']);
        $this->actingAs($this->pm)->patchJson("/api/tasks/{$b->id}/status", ['status' => 'review']);

        $this->actingAs($this->member)->getJson("/api/activity?task_id={$a->id}")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject_id', $a->id)
            ->assertJsonPath('data.0.user.id', $this->pm->id);
    }

    public function test_dashboard_now_shows_recent_activity(): void
    {
        $task = Task::factory()->for($this->project)->withStatus(TaskStatus::Todo)->create();
        $this->actingAs($this->pm)->patchJson("/api/tasks/{$task->id}/status", ['status' => 'completed']);
        Client::factory()->create();

        $this->actingAs($this->member)->getJson('/api/dashboard')
            ->assertJsonCount(1, 'data.recent_activity')
            ->assertJsonPath('data.recent_activity.0.action', 'task.status_changed');
    }
}
