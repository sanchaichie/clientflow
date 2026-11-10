<?php

namespace Tests\Feature\Collaboration;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskDueSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DueSoonReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = User::factory()->teamMember()->create();
        $this->project = Project::factory()->active()->create();
        $this->project->members()->attach($this->member);
    }

    private function task(array $attributes): Task
    {
        return Task::factory()->for($this->project)->create(array_merge([
            'assignee_id' => $this->member->id, 'status' => 'in_progress',
        ], $attributes));
    }

    public function test_open_tasks_due_today_or_tomorrow_are_reminded(): void
    {
        Notification::fake();
        $today = $this->task(['due_date' => today()]);
        $tomorrow = $this->task(['due_date' => today()->addDay()]);
        $this->task(['due_date' => today()->addDays(5)]);                        // too far away
        $this->task(['due_date' => today(), 'status' => 'completed']);            // done
        $this->task(['due_date' => today()->subDay()]);                           // already overdue

        $this->artisan('tasks:notify-due-soon')->assertSuccessful();

        Notification::assertSentToTimes($this->member, TaskDueSoon::class, 2);
        Notification::assertSentTo($this->member, TaskDueSoon::class, fn (TaskDueSoon $n) => in_array($n->task->id, [$today->id, $tomorrow->id]));
    }

    public function test_each_task_is_reminded_only_once(): void
    {
        Notification::fake();
        $this->task(['due_date' => today()->addDay()]);

        $this->artisan('tasks:notify-due-soon');
        $this->artisan('tasks:notify-due-soon');

        Notification::assertSentToTimes($this->member, TaskDueSoon::class, 1);
    }

    public function test_changing_the_due_date_allows_a_new_reminder(): void
    {
        $task = $this->task(['due_date' => today()->addDay()]);
        $this->artisan('tasks:notify-due-soon');
        $this->assertNotNull($task->fresh()->due_soon_notified_at);

        $this->actingAs($this->project->manager)
            ->putJson("/api/tasks/{$task->id}", ['due_date' => today()->toDateString()])
            ->assertOk();

        $this->assertNull($task->fresh()->due_soon_notified_at);
    }

    public function test_archived_projects_and_inactive_assignees_are_skipped(): void
    {
        Notification::fake();
        $this->task(['due_date' => today()]);
        $this->project->forceFill(['archived_at' => now()])->save();
        $other = Project::factory()->active()->create();
        $inactive = User::factory()->teamMember()->inactive()->create();
        Task::factory()->for($other)->create(['assignee_id' => $inactive->id, 'due_date' => today(), 'status' => 'todo']);

        $this->artisan('tasks:notify-due-soon');

        Notification::assertNothingSent();
    }

    public function test_reminder_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('tasks:notify-due-soon')->assertSuccessful();
    }
}
