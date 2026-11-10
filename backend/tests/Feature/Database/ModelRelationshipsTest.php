<?php

namespace Tests\Feature\Database;

use App\Enums\ProjectStatus;
use App\Enums\RoleSlug;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_helpers_reflect_the_assigned_role(): void
    {
        $admin = User::factory()->admin()->create();
        $pm = User::factory()->projectManager()->create();
        $member = User::factory()->create(); // default state

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($pm->isProjectManager());
        $this->assertTrue($member->isTeamMember());
        $this->assertFalse($member->isAdmin());
        $this->assertSame(RoleSlug::ProjectManager, $pm->role->slug);
    }

    public function test_project_links_client_manager_members_and_tasks(): void
    {
        $pm = User::factory()->projectManager()->create();
        $members = User::factory(2)->create();
        $project = Project::factory()->create(['manager_id' => $pm->id]);
        $project->members()->attach($members);
        Task::factory(3)->for($project)->create();

        $project->refresh();

        $this->assertTrue($project->manager->is($pm));
        $this->assertInstanceOf(Client::class, $project->client);
        $this->assertCount(2, $project->members);
        $this->assertCount(3, $project->tasks);
        $this->assertTrue($pm->managedProjects->contains($project));
        $this->assertTrue($members[0]->projects->contains($project));
    }

    public function test_client_has_account_manager_and_projects(): void
    {
        $pm = User::factory()->projectManager()->create();
        $client = Client::factory()->create(['account_manager_id' => $pm->id]);
        Project::factory(2)->for($client)->create();

        $this->assertTrue($client->accountManager->is($pm));
        $this->assertCount(2, $client->projects);
        $this->assertTrue($pm->managedClients->contains($client));
    }

    public function test_task_links_assignee_and_comments_with_authors(): void
    {
        $member = User::factory()->create();
        $task = Task::factory()->create(['assignee_id' => $member->id]);
        TaskComment::factory()->for($task)->create(['user_id' => $member->id]);

        $this->assertTrue($task->assignee->is($member));
        $this->assertTrue($member->assignedTasks->contains($task));
        $this->assertTrue($task->comments->first()->author->is($member));
    }

    public function test_statuses_are_cast_to_enums(): void
    {
        $project = Project::factory()->create(['status' => 'on_hold']);
        $task = Task::factory()->create(['status' => 'in_progress']);

        $this->assertSame(ProjectStatus::OnHold, $project->fresh()->status);
        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_not_archived_scope_hides_archived_records(): void
    {
        Client::factory(2)->create();
        Client::factory()->archived()->create();

        $this->assertSame(2, Client::notArchived()->count());
        $this->assertSame(3, Client::count());
    }

    public function test_overdue_and_due_today_scopes_ignore_completed_tasks(): void
    {
        $overdue = Task::factory()->overdue()->create();
        $dueToday = Task::factory()->dueToday()->create();
        Task::factory()->completed()->create(['due_date' => today()->subDays(5)]);
        Task::factory()->completed()->create(['due_date' => today()]);
        Task::factory()->withStatus(TaskStatus::Todo)->create(['due_date' => today()->addWeek()]);

        $this->assertEquals([$overdue->id], Task::overdue()->pluck('id')->all());
        $this->assertEquals([$dueToday->id], Task::dueToday()->pluck('id')->all());
        $this->assertTrue($overdue->isOverdue());
        $this->assertFalse($dueToday->isOverdue());
    }
}
