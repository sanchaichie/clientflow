<?php

namespace Tests\Feature\Api;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;

    private User $member;

    private User $colleague;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pm = User::factory()->projectManager()->create();
        $this->member = User::factory()->teamMember()->create();
        $this->colleague = User::factory()->teamMember()->create();
        $this->project = Project::factory()->active()->create(['manager_id' => $this->pm->id]);
        $this->project->members()->attach([$this->member->id, $this->colleague->id]);
    }

    // --- Create -------------------------------------------------------------

    public function test_manager_creates_and_assigns_a_task(): void
    {
        $this->actingAs($this->pm)->postJson('/api/tasks', [
            'project_id' => $this->project->id,
            'title' => 'Design homepage',
            'priority' => 'high',
            'due_date' => '2027-01-15',
            'assignee_id' => $this->member->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.assignee.id', $this->member->id)
            ->assertJsonPath('data.creator.id', $this->pm->id);
    }

    public function test_tasks_can_only_be_assigned_to_project_members(): void
    {
        $this->actingAs($this->pm)->postJson('/api/tasks', [
            'project_id' => $this->project->id,
            'title' => 'X',
            'assignee_id' => User::factory()->teamMember()->create()->id,
        ])->assertJsonValidationErrors('assignee_id');
    }

    public function test_task_creation_is_validated(): void
    {
        $this->actingAs($this->pm)->postJson('/api/tasks', [
            'project_id' => $this->project->id, 'title' => '', 'priority' => 'whenever', 'status' => 'done',
        ])->assertJsonValidationErrors(['title', 'priority', 'status']);

        $this->actingAs($this->pm)->postJson('/api/tasks', ['project_id' => 999999, 'title' => 'X'])
            ->assertJsonValidationErrors('project_id');
    }

    public function test_members_and_other_pms_cannot_create_tasks(): void
    {
        $payload = ['project_id' => $this->project->id, 'title' => 'X'];

        $this->actingAs($this->member)->postJson('/api/tasks', $payload)->assertForbidden();
        $this->actingAs(User::factory()->projectManager()->create())->postJson('/api/tasks', $payload)->assertForbidden();
    }

    public function test_tasks_cannot_be_added_to_an_archived_project(): void
    {
        $this->project->forceFill(['archived_at' => now()])->save();

        $this->actingAs($this->pm)->postJson('/api/tasks', ['project_id' => $this->project->id, 'title' => 'X'])
            ->assertForbidden();
    }

    public function test_creating_a_completed_task_updates_project_progress(): void
    {
        Task::factory()->for($this->project)->withStatus(TaskStatus::Todo)->create();

        $this->actingAs($this->pm)->postJson('/api/tasks', [
            'project_id' => $this->project->id, 'title' => 'Done already', 'status' => 'completed',
        ])->assertCreated()->assertJsonPath('data.completed_at', fn ($v) => $v !== null);

        $this->assertSame(50, $this->project->fresh()->progress);
    }

    // --- Read ---------------------------------------------------------------

    public function test_member_sees_all_tasks_in_their_projects_and_nothing_else(): void
    {
        Task::factory(2)->for($this->project)->create();
        Task::factory(3)->create(); // other projects

        $this->actingAs($this->member)->getJson('/api/tasks')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($this->member)
            ->getJson('/api/tasks/'.Task::factory()->create()->id)
            ->assertForbidden();
    }

    public function test_tasks_filter_by_assignee_me_status_priority_and_due(): void
    {
        $mine = Task::factory()->for($this->project)->overdue()->create(['assignee_id' => $this->member->id, 'priority' => 'urgent']);
        Task::factory()->for($this->project)->dueToday()->create(['assignee_id' => $this->colleague->id]);
        Task::factory()->for($this->project)->completed()->create(['assignee_id' => $this->member->id]);

        $as = $this->actingAs($this->member);
        $as->getJson('/api/tasks?assignee_id=me')->assertJsonCount(2, 'data');
        $as->getJson('/api/tasks?assignee_id=me&status=in_progress')->assertJsonCount(1, 'data');
        $as->getJson('/api/tasks?priority=urgent')->assertJsonPath('data.0.id', $mine->id);
        $as->getJson('/api/tasks?due=overdue')->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_overdue', true);
        $as->getJson('/api/tasks?due=today')->assertJsonCount(1, 'data');
    }

    public function test_tasks_of_archived_projects_are_hidden_from_general_lists(): void
    {
        Task::factory()->for($this->project)->create();
        $this->project->forceFill(['archived_at' => now()])->save();

        $this->actingAs($this->pm)->getJson('/api/tasks')->assertJsonCount(0, 'data');
        $this->actingAs($this->pm)->getJson("/api/tasks?project_id={$this->project->id}")->assertJsonCount(1, 'data');
    }

    // --- Update -------------------------------------------------------------

    public function test_manager_edits_task_details(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->actingAs($this->pm)->putJson("/api/tasks/{$task->id}", [
            'title' => 'Renamed', 'priority' => 'low', 'assignee_id' => $this->colleague->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed')
            ->assertJsonPath('data.assignee_id', $this->colleague->id);
    }

    public function test_member_cannot_edit_task_details_even_their_own(): void
    {
        $task = Task::factory()->for($this->project)->create(['assignee_id' => $this->member->id]);

        $this->actingAs($this->member)->putJson("/api/tasks/{$task->id}", ['title' => 'Mine now'])->assertForbidden();
    }

    // --- Status / Kanban ------------------------------------------------------

    public function test_assignee_moves_their_task_and_progress_and_completed_at_follow(): void
    {
        $task = Task::factory()->for($this->project)->withStatus(TaskStatus::Review)
            ->create(['assignee_id' => $this->member->id]);

        $this->actingAs($this->member)->patchJson("/api/tasks/{$task->id}/status", ['status' => 'completed'])
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame(100, $this->project->fresh()->progress);

        $this->actingAs($this->member)->patchJson("/api/tasks/{$task->id}/status", ['status' => 'in_progress']);
        $this->assertNull($task->fresh()->completed_at);
        $this->assertSame(0, $this->project->fresh()->progress);
    }

    public function test_member_cannot_move_a_colleagues_task(): void
    {
        $task = Task::factory()->for($this->project)->create(['assignee_id' => $this->colleague->id]);

        $this->actingAs($this->member)->patchJson("/api/tasks/{$task->id}/status", ['status' => 'completed'])
            ->assertForbidden();
    }

    public function test_dropping_at_a_position_shifts_the_cards_below(): void
    {
        $review = fn () => Task::factory()->for($this->project)->withStatus(TaskStatus::Review);
        $first = $review()->create(['position' => 0]);
        $second = $review()->create(['position' => 1]);
        $moving = Task::factory()->for($this->project)->withStatus(TaskStatus::Todo)->create(['position' => 0]);

        $this->actingAs($this->pm)->patchJson("/api/tasks/{$moving->id}/status", ['status' => 'review', 'position' => 1])
            ->assertOk()->assertJsonPath('data.position', 1);

        $this->assertSame(0, $first->fresh()->position);
        $this->assertSame(2, $second->fresh()->position);
    }

    public function test_status_change_without_position_appends_to_the_column(): void
    {
        Task::factory()->for($this->project)->withStatus(TaskStatus::Review)->create(['position' => 4]);
        $task = Task::factory()->for($this->project)->withStatus(TaskStatus::Todo)->create();

        $this->actingAs($this->pm)->patchJson("/api/tasks/{$task->id}/status", ['status' => 'review'])
            ->assertJsonPath('data.position', 5);
    }

    // --- Delete -------------------------------------------------------------

    public function test_manager_deletes_a_task_and_progress_is_recalculated(): void
    {
        Task::factory()->for($this->project)->completed()->create();
        $open = Task::factory()->for($this->project)->withStatus(TaskStatus::Todo)->create();
        $this->project->recalculateProgress();
        $this->assertSame(50, $this->project->progress);

        $this->actingAs($this->pm)->deleteJson("/api/tasks/{$open->id}")->assertNoContent();

        $this->assertModelMissing($open);
        $this->assertSame(100, $this->project->fresh()->progress);
    }

    public function test_assignee_cannot_delete_their_task(): void
    {
        $task = Task::factory()->for($this->project)->create(['assignee_id' => $this->member->id]);

        $this->actingAs($this->member)->deleteJson("/api/tasks/{$task->id}")->assertForbidden();
        $this->assertModelExists($task);
    }
}
