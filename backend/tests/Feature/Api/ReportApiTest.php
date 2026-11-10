<?php

namespace Tests\Feature\Api;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_members_cannot_view_reports(): void
    {
        $this->actingAs(User::factory()->teamMember()->create())->getJson('/api/reports')->assertForbidden();
    }

    public function test_report_groups_projects_and_tasks_by_status_including_zeros(): void
    {
        $admin = User::factory()->admin()->create();
        $p = Project::factory()->active()->create();
        Project::factory()->create(['status' => 'completed']);
        Project::factory()->archived()->create(); // excluded
        Task::factory(2)->for($p)->withStatus(TaskStatus::Todo)->create();
        Task::factory()->for($p)->completed()->create();

        $data = $this->actingAs($admin)->getJson('/api/reports')->assertOk()->json('data');

        $this->assertSame(['planning' => 0, 'active' => 1, 'on_hold' => 0, 'completed' => 1, 'cancelled' => 0], $data['projects_by_status']);
        $this->assertSame(['todo' => 2, 'in_progress' => 0, 'review' => 0, 'completed' => 1], $data['tasks_by_status']);
        $this->assertSame(['total' => 3, 'completed' => 1, 'pending' => 2, 'rate' => 33], $data['completion']);
    }

    public function test_overdue_list_and_member_summary(): void
    {
        $admin = User::factory()->admin()->create();
        $ann = User::factory()->create(['name' => 'Ann']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $p = Project::factory()->active()->create();
        Task::factory()->for($p)->completed()->create(['assignee_id' => $ann->id]);
        Task::factory()->for($p)->overdue()->create(['assignee_id' => $ann->id]);
        Task::factory()->for($p)->completed()->create(['assignee_id' => $bob->id]);
        // No due date: a random factory date in the past would make it overdue too.
        Task::factory()->for($p)->withStatus(TaskStatus::Todo)->create(['assignee_id' => null, 'due_date' => null]);

        $data = $this->actingAs($admin)->getJson('/api/reports')->json('data');

        $this->assertSame(1, $data['overdue']['count']);
        $this->assertCount(1, $data['overdue']['tasks']);
        $this->assertSame($ann->id, $data['overdue']['tasks'][0]['assignee']['id']);
        $this->assertSame([
            ['user_id' => $ann->id, 'name' => 'Ann', 'assigned' => 2, 'completed' => 1, 'pending' => 1, 'overdue' => 1, 'completion_rate' => 50],
            ['user_id' => $bob->id, 'name' => 'Bob', 'assigned' => 1, 'completed' => 1, 'pending' => 0, 'overdue' => 0, 'completion_rate' => 100],
        ], $data['members']);
    }

    public function test_date_range_limits_to_records_created_in_it(): void
    {
        $admin = User::factory()->admin()->create();
        $p = Project::factory()->active()->create(['created_at' => '2026-01-10']);
        Task::factory()->for($p)->withStatus(TaskStatus::Todo)->create(['created_at' => '2026-01-15']);
        Task::factory()->for($p)->withStatus(TaskStatus::Todo)->create(['created_at' => '2026-03-01']);

        $data = $this->actingAs($admin)->getJson('/api/reports?from=2026-01-01&to=2026-01-31')->json('data');

        $this->assertSame(1, $data['completion']['total']);
        $this->assertSame(1, $data['projects_by_status']['active']);
        $this->assertSame(['from' => '2026-01-01', 'to' => '2026-01-31'], $data['range']);
    }

    public function test_range_is_validated(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/reports?from=2026-02-01&to=2026-01-01')
            ->assertJsonValidationErrors('to');
        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/reports?from=yesterday')
            ->assertJsonValidationErrors('from');
    }

    public function test_pm_report_only_covers_their_projects(): void
    {
        $pm = User::factory()->projectManager()->create();
        $mine = Project::factory()->active()->create(['manager_id' => $pm->id]);
        Task::factory(2)->for($mine)->create();
        Task::factory(5)->for(Project::factory()->active()->create())->create(); // another PM's

        $this->actingAs($pm)->getJson('/api/reports')
            ->assertOk()
            ->assertJsonPath('data.completion.total', 2)
            ->assertJsonPath('data.projects_by_status.active', 1);
    }

    public function test_member_summary_is_one_query_regardless_of_team_size(): void
    {
        // Load the role up front: otherwise only the first request pays for that
        // one-time lookup, and the counts differ for a reason unrelated to N+1.
        $admin = User::factory()->admin()->create()->load('role');
        $p = Project::factory()->active()->create();

        $count = function () use ($admin) {
            \DB::flushQueryLog();
            \DB::enableQueryLog();
            $this->actingAs($admin)->getJson('/api/reports')->assertOk();

            return count(\DB::getQueryLog());
        };

        foreach (User::factory(2)->create() as $u) {
            Task::factory()->for($p)->overdue()->create(['assignee_id' => $u->id]);
        }
        $small = $count();
        foreach (User::factory(10)->create() as $u) {
            Task::factory()->for($p)->overdue()->create(['assignee_id' => $u->id]);
        }

        $this->assertSame($small, $count());
    }
}
