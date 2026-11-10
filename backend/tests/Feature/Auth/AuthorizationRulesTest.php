<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Policy and visibility rules from docs/DECISIONS.md (010), checked directly.
 * HTTP-level tests for each resource follow in Milestone 4.
 */
class AuthorizationRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pm;

    private User $otherPm;

    private User $member;

    private User $outsider;

    private Project $project;

    private Project $otherProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->pm = User::factory()->projectManager()->create();
        $this->otherPm = User::factory()->projectManager()->create();
        $this->member = User::factory()->teamMember()->create();
        $this->outsider = User::factory()->teamMember()->create();

        $this->project = Project::factory()->create(['manager_id' => $this->pm->id]);
        $this->project->members()->attach($this->member);
        $this->otherProject = Project::factory()->create(['manager_id' => $this->otherPm->id]);
    }

    public function test_admin_can_do_everything(): void
    {
        $this->assertTrue($this->admin->can('update', $this->otherProject));
        $this->assertTrue($this->admin->can('restore', $this->project));
        $this->assertTrue($this->admin->can('create', User::class));
        $this->assertSame(2, Project::visibleTo($this->admin)->count());
    }

    // --- Projects -----------------------------------------------------------

    public function test_project_visibility_follows_management_and_membership(): void
    {
        $this->assertTrue($this->pm->can('view', $this->project));
        $this->assertTrue($this->member->can('view', $this->project));
        $this->assertFalse($this->outsider->can('view', $this->project));
        $this->assertFalse($this->pm->can('view', $this->otherProject));

        $this->assertEquals([$this->project->id], Project::visibleTo($this->pm)->pluck('id')->all());
        $this->assertEquals([$this->project->id], Project::visibleTo($this->member)->pluck('id')->all());
        $this->assertSame(0, Project::visibleTo($this->outsider)->count());
    }

    public function test_only_the_projects_manager_can_edit_or_archive_it(): void
    {
        $this->assertTrue($this->pm->can('update', $this->project));
        $this->assertTrue($this->pm->can('delete', $this->project));
        $this->assertFalse($this->otherPm->can('update', $this->project));
        $this->assertFalse($this->member->can('update', $this->project));
        $this->assertFalse($this->pm->can('restore', $this->project));
    }

    public function test_only_project_managers_can_create_projects(): void
    {
        $this->assertTrue($this->pm->can('create', Project::class));
        $this->assertFalse($this->member->can('create', Project::class));
    }

    // --- Tasks --------------------------------------------------------------

    public function test_member_can_move_only_their_own_tasks(): void
    {
        $mine = Task::factory()->for($this->project)->create(['assignee_id' => $this->member->id]);
        $colleagues = Task::factory()->for($this->project)->create(['assignee_id' => null]);

        $this->assertTrue($this->member->can('view', $colleagues));
        $this->assertTrue($this->member->can('updateStatus', $mine));
        $this->assertFalse($this->member->can('updateStatus', $colleagues));
        $this->assertFalse($this->member->can('update', $mine));
        $this->assertFalse($this->member->can('delete', $mine));
    }

    public function test_project_manager_controls_tasks_in_their_project_only(): void
    {
        $task = Task::factory()->for($this->project)->create();
        $foreign = Task::factory()->for($this->otherProject)->create();

        $this->assertTrue($this->pm->can('create', [Task::class, $this->project]));
        $this->assertFalse($this->pm->can('create', [Task::class, $this->otherProject]));
        $this->assertTrue($this->pm->can('update', $task));
        $this->assertTrue($this->pm->can('delete', $task));
        $this->assertFalse($this->pm->can('view', $foreign));
        $this->assertFalse($this->pm->can('updateStatus', $foreign));
    }

    public function test_commenting_requires_access_to_the_project(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->assertTrue($this->member->can('comment', $task));
        $this->assertTrue($this->pm->can('comment', $task));
        $this->assertFalse($this->outsider->can('comment', $task));
    }

    public function test_task_list_contains_only_tasks_from_visible_projects(): void
    {
        $visible = Task::factory(2)->for($this->project)->create();
        Task::factory(3)->for($this->otherProject)->create();

        $this->assertEqualsCanonicalizing(
            $visible->pluck('id')->all(),
            Task::visibleTo($this->member)->pluck('id')->all(),
        );
        $this->assertSame(0, Task::visibleTo($this->outsider)->count());
        $this->assertSame(5, Task::visibleTo($this->admin)->count());
    }

    // --- Clients ------------------------------------------------------------

    public function test_pm_sees_assigned_clients_and_clients_of_their_projects(): void
    {
        $assigned = Client::factory()->create(['account_manager_id' => $this->pm->id]);
        $viaProject = $this->project->client;
        $unrelated = $this->otherProject->client;

        $this->assertTrue($this->pm->can('view', $assigned));
        $this->assertTrue($this->pm->can('view', $viaProject));
        $this->assertFalse($this->pm->can('view', $unrelated));

        $this->assertEqualsCanonicalizing(
            [$assigned->id, $viaProject->id],
            Client::visibleTo($this->pm)->pluck('id')->all(),
        );
    }

    public function test_pm_edits_only_clients_they_account_manage(): void
    {
        $assigned = Client::factory()->create(['account_manager_id' => $this->pm->id]);

        $this->assertTrue($this->pm->can('update', $assigned));
        $this->assertFalse($this->pm->can('update', $this->project->client)); // visible, not assigned
        $this->assertFalse($this->pm->can('restore', $assigned));
    }

    public function test_team_members_have_no_client_access(): void
    {
        $this->assertFalse($this->member->can('viewAny', Client::class));
        $this->assertFalse($this->member->can('view', $this->project->client));
        $this->assertFalse($this->member->can('create', Client::class));
        $this->assertSame(0, Client::visibleTo($this->member)->count());
    }
}
