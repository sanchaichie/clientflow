<?php

namespace Tests\Feature\Api;

use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_load_the_dashboard(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_admin_sees_totals_across_everything_except_archived(): void
    {
        $admin = User::factory()->admin()->create();
        Client::factory(3)->create();
        Client::factory()->archived()->create();
        $active = Project::factory()->active()->create();
        Project::factory()->create(['status' => 'completed']);
        Project::factory()->active()->archived()->create();
        Task::factory()->for($active)->overdue()->create();
        Task::factory()->for($active)->dueToday()->create();
        Task::factory()->for($active)->completed()->create(['due_date' => today()->subDay()]);

        $stats = $this->actingAs($admin)->getJson('/api/dashboard')->assertOk()->json('data.stats');

        // 3 visible + 3 auto-created by the project factories (one archived client excluded).
        $this->assertSame(6, $stats['total_clients']);
        $this->assertSame(1, $stats['active_projects']);
        $this->assertSame(1, $stats['completed_projects']);
        $this->assertSame(2, $stats['pending_tasks']);
        $this->assertSame(1, $stats['overdue_tasks']);
        $this->assertSame(1, $stats['due_today_tasks']);
    }

    public function test_team_member_sees_only_their_own_task_figures_and_no_clients(): void
    {
        $member = User::factory()->teamMember()->create();
        $project = Project::factory()->active()->create();
        $project->members()->attach($member);
        Task::factory()->for($project)->overdue()->create(['assignee_id' => $member->id]);
        Task::factory(4)->for($project)->overdue()->create(); // colleagues' tasks
        Project::factory()->active()->create();              // not a member

        $data = $this->actingAs($member)->getJson('/api/dashboard')->assertOk()->json('data');

        $this->assertSame('my_tasks', $data['scope']);
        $this->assertNull($data['stats']['total_clients']);
        $this->assertSame(1, $data['stats']['active_projects']);
        $this->assertSame(1, $data['stats']['pending_tasks']);
        $this->assertSame(1, $data['stats']['overdue_tasks']);
        $this->assertCount(1, $data['upcoming_tasks']);
    }

    public function test_due_today_follows_the_configured_agency_timezone(): void
    {
        // 20:00 UTC on June 1st is already June 2nd in Manila (UTC+8).
        $this->travelTo(Carbon::parse('2026-06-01 20:00:00', 'UTC'));
        $admin = User::factory()->admin()->create();
        $p = Project::factory()->active()->create();
        Task::factory()->for($p)->withStatus(TaskStatus::Todo)->create(['due_date' => '2026-06-02']);

        $utc = $this->actingAs($admin)->getJson('/api/dashboard')->json('data.stats.due_today_tasks');

        $original = date_default_timezone_get();
        config(['app.timezone' => 'Asia/Manila']);
        date_default_timezone_set('Asia/Manila');
        try {
            $manila = $this->actingAs($admin)->getJson('/api/dashboard')->json('data.stats.due_today_tasks');
        } finally {
            date_default_timezone_set($original);
        }

        $this->assertSame(0, $utc);    // in UTC it is still June 1st
        $this->assertSame(1, $manila); // for a Manila agency the task is due today
    }

    public function test_pm_sees_progress_for_their_open_projects_only(): void
    {
        $pm = User::factory()->projectManager()->create();
        $mine = Project::factory()->active()->create(['manager_id' => $pm->id]);
        Task::factory()->for($mine)->completed()->create();
        Task::factory()->for($mine)->withStatus(TaskStatus::Todo)->create();
        $mine->recalculateProgress();
        Project::factory()->create(['manager_id' => $pm->id, 'status' => 'completed']);
        Project::factory()->active()->create(); // another PM's

        $this->actingAs($pm)->getJson('/api/dashboard')
            ->assertJsonCount(1, 'data.project_progress')
            ->assertJsonPath('data.project_progress.0.id', $mine->id)
            ->assertJsonPath('data.project_progress.0.progress', 50)
            ->assertJsonPath('data.recent_activity', []);
    }
}
