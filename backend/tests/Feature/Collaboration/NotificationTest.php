<?php

namespace Tests\Feature\Collaboration;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ProjectAssigned;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommented;
use App\Notifications\TaskStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
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

    // --- Who gets notified ----------------------------------------------------

    public function test_assignee_is_notified_but_not_the_person_assigning(): void
    {
        Notification::fake();

        $this->actingAs($this->pm)->postJson('/api/tasks', [
            'project_id' => $this->project->id, 'title' => 'X', 'assignee_id' => $this->member->id,
        ])->assertCreated();

        Notification::assertSentTo($this->member, TaskAssigned::class);
        Notification::assertNotSentTo($this->pm, TaskAssigned::class);
    }

    public function test_assigning_a_task_to_yourself_sends_nothing(): void
    {
        Notification::fake();

        $this->actingAs($this->pm)->postJson('/api/tasks', [
            'project_id' => $this->project->id, 'title' => 'X', 'assignee_id' => $this->pm->id,
        ]);

        Notification::assertNothingSent();
    }

    public function test_status_change_notifies_manager_and_assignee_except_the_mover(): void
    {
        $task = Task::factory()->for($this->project)->create(['assignee_id' => $this->member->id, 'status' => 'todo']);
        Notification::fake();

        $this->actingAs($this->member)->patchJson("/api/tasks/{$task->id}/status", ['status' => 'review']);

        Notification::assertSentTo($this->pm, TaskStatusChanged::class,
            fn (TaskStatusChanged $n) => $n->to->value === 'review');
        Notification::assertNotSentTo($this->member, TaskStatusChanged::class);
    }

    public function test_reordering_within_a_column_sends_nothing(): void
    {
        $task = Task::factory()->for($this->project)->create(['assignee_id' => $this->member->id, 'status' => 'todo']);
        Notification::fake();

        $this->actingAs($this->pm)->patchJson("/api/tasks/{$task->id}/status", ['status' => 'todo', 'position' => 0]);

        Notification::assertNothingSent();
    }

    public function test_comment_notifies_assignee_and_manager_except_the_author(): void
    {
        $colleague = User::factory()->teamMember()->create();
        $this->project->members()->attach($colleague);
        $task = Task::factory()->for($this->project)->create(['assignee_id' => $this->member->id]);
        Notification::fake();

        $this->actingAs($colleague)->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Nice']);

        Notification::assertSentTo([$this->member, $this->pm], TaskCommented::class);
        Notification::assertNotSentTo($colleague, TaskCommented::class);
    }

    public function test_new_project_members_are_notified_once(): void
    {
        $newcomer = User::factory()->teamMember()->create();
        Notification::fake();

        $this->actingAs($this->pm)->postJson("/api/projects/{$this->project->id}/members", [
            'user_ids' => [$newcomer->id, $this->member->id],
        ]);

        Notification::assertSentTo($newcomer, ProjectAssigned::class);
        Notification::assertNotSentTo($this->member, ProjectAssigned::class); // already a member
    }

    public function test_admin_creating_a_project_notifies_the_chosen_manager(): void
    {
        Notification::fake();

        $this->actingAs(User::factory()->admin()->create())->postJson('/api/projects', [
            'client_id' => $this->project->client_id, 'name' => 'New', 'manager_id' => $this->pm->id,
        ])->assertCreated();

        Notification::assertSentTo($this->pm, ProjectAssigned::class, fn (ProjectAssigned $n) => $n->asManager);
    }

    public function test_deactivated_users_are_not_notified(): void
    {
        // Explicit "todo" so the move really changes status (otherwise the test
        // would pass trivially because nothing happened).
        $task = Task::factory()->for($this->project)->create(['assignee_id' => $this->member->id, 'status' => 'todo']);
        $this->pm->forceFill(['is_active' => false])->save();
        Notification::fake();

        $this->actingAs($this->member)->patchJson("/api/tasks/{$task->id}/status", ['status' => 'completed']);

        Notification::assertNothingSent();
    }

    // --- Reading and marking ----------------------------------------------------

    private function notifyMember(int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            $task = Task::factory()->for($this->project)->create();
            $this->member->notify(new TaskAssigned($task, $this->pm));
        }
    }

    public function test_user_lists_their_notifications_with_unread_count(): void
    {
        $this->notifyMember(3);

        $this->actingAs($this->member)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.unread_count', 3)
            ->assertJsonPath('data.0.kind', 'task_assigned')
            ->assertJsonStructure(['data' => [['id', 'kind', 'message', 'url', 'read_at', 'created_at']]]);

        $this->actingAs($this->member)->getJson('/api/notifications/unread-count')
            ->assertExactJson(['data' => ['unread_count' => 3]]);
    }

    public function test_marking_one_and_all_as_read(): void
    {
        $this->notifyMember(3);
        $id = $this->member->notifications()->first()->id;

        $this->actingAs($this->member)->patchJson("/api/notifications/{$id}/read")
            ->assertOk()->assertJsonPath('data.read_at', fn ($v) => $v !== null);
        $this->actingAs($this->member)->getJson('/api/notifications?unread=1')->assertJsonCount(2, 'data');

        $this->actingAs($this->member)->postJson('/api/notifications/read-all')->assertOk();
        $this->assertSame(0, $this->member->fresh()->unreadNotifications()->count());
    }

    public function test_users_cannot_see_or_mark_someone_elses_notifications(): void
    {
        $this->notifyMember();
        $id = $this->member->notifications()->first()->id;

        $this->actingAs($this->pm)->getJson('/api/notifications')->assertJsonCount(0, 'data');
        $this->actingAs($this->pm)->patchJson("/api/notifications/{$id}/read")->assertNotFound();
        $this->assertNull($this->member->notifications()->first()->read_at);
    }
}
