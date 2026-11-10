<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    // --- Create -------------------------------------------------------------

    public function test_pm_creates_a_project_they_manage_with_initial_members(): void
    {
        $pm = User::factory()->projectManager()->create();
        $client = Client::factory()->create(['account_manager_id' => $pm->id]);
        $members = User::factory(2)->teamMember()->create();

        $this->actingAs($pm)->postJson('/api/projects', [
            'client_id' => $client->id,
            'name' => 'Website relaunch',
            'start_date' => '2026-12-01',
            'end_date' => '2027-02-01',
            'member_ids' => $members->pluck('id')->all(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.manager_id', $pm->id)
            ->assertJsonPath('data.status', 'planning')
            ->assertJsonPath('data.progress', 0)
            ->assertJsonCount(2, 'data.members');
    }

    public function test_pm_cannot_set_the_manager_or_progress(): void
    {
        $pm = User::factory()->projectManager()->create();
        $client = Client::factory()->create(['account_manager_id' => $pm->id]);

        $this->actingAs($pm)->postJson('/api/projects', [
            'client_id' => $client->id, 'name' => 'X',
            'manager_id' => User::factory()->projectManager()->create()->id,
        ])->assertJsonValidationErrors('manager_id');

        $id = $this->actingAs($pm)->postJson('/api/projects', [
            'client_id' => $client->id, 'name' => 'X', 'progress' => 90,
        ])->assertCreated()->json('data.id');

        $this->assertSame(0, Project::find($id)->progress); // ignored, derived from tasks
    }

    public function test_admin_must_choose_an_active_pm_as_manager(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)->postJson('/api/projects', ['client_id' => $client->id, 'name' => 'X'])
            ->assertJsonValidationErrors('manager_id');
        $this->actingAs($admin)->postJson('/api/projects', [
            'client_id' => $client->id, 'name' => 'X',
            'manager_id' => User::factory()->projectManager()->inactive()->create()->id,
        ])->assertJsonValidationErrors('manager_id');
    }

    public function test_project_needs_a_visible_unarchived_client_and_valid_dates(): void
    {
        $pm = User::factory()->projectManager()->create();
        $foreignClient = Client::factory()->create();
        $archivedClient = Client::factory()->archived()->create(['account_manager_id' => $pm->id]);

        $this->actingAs($pm)->postJson('/api/projects', ['client_id' => $foreignClient->id, 'name' => 'X'])
            ->assertJsonValidationErrors('client_id');
        $this->actingAs($pm)->postJson('/api/projects', ['client_id' => $archivedClient->id, 'name' => 'X'])
            ->assertJsonValidationErrors('client_id');
        $this->actingAs($pm)->postJson('/api/projects', [
            'client_id' => Client::factory()->create(['account_manager_id' => $pm->id])->id,
            'name' => 'X', 'start_date' => '2027-01-10', 'end_date' => '2027-01-01',
        ])->assertJsonValidationErrors('end_date');
    }

    public function test_team_member_cannot_create_projects(): void
    {
        $this->actingAs(User::factory()->teamMember()->create())
            ->postJson('/api/projects', ['client_id' => Client::factory()->create()->id, 'name' => 'X'])
            ->assertForbidden();
    }

    // --- Read ---------------------------------------------------------------

    public function test_member_lists_only_their_projects(): void
    {
        $member = User::factory()->teamMember()->create();
        $mine = Project::factory()->create();
        $mine->members()->attach($member);
        Project::factory(3)->create();

        $this->actingAs($member)->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonStructure(['data' => [['client' => ['id', 'company_name'], 'manager' => ['id', 'name'], 'members_count', 'tasks_count']]]);
    }

    public function test_member_cannot_open_a_project_they_are_not_on(): void
    {
        $this->actingAs(User::factory()->teamMember()->create())
            ->getJson('/api/projects/'.Project::factory()->create()->id)
            ->assertForbidden();
    }

    public function test_projects_can_be_filtered_and_sorted(): void
    {
        $admin = User::factory()->admin()->create();
        Project::factory()->active()->create(['name' => 'Beta']);
        Project::factory()->active()->create(['name' => 'Alpha']);
        Project::factory()->create(['status' => 'completed', 'name' => 'Gamma']);

        $this->actingAs($admin)->getJson('/api/projects?status=active&sort=name')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.1.name', 'Beta');
        $this->actingAs($admin)->getJson('/api/projects?sort=-name')->assertJsonPath('data.0.name', 'Gamma');
        $this->actingAs($admin)->getJson('/api/projects?search=amm')->assertJsonCount(1, 'data');
    }

    public function test_unknown_sort_column_falls_back_to_the_default(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/projects?sort=password')
            ->assertOk();
    }

    // --- Update / archive -----------------------------------------------------

    public function test_manager_updates_status_but_cannot_reassign_the_project(): void
    {
        $pm = User::factory()->projectManager()->create();
        $project = Project::factory()->create(['manager_id' => $pm->id, 'status' => 'planning']);

        $this->actingAs($pm)->putJson("/api/projects/{$project->id}", ['status' => 'on_hold'])
            ->assertOk()->assertJsonPath('data.status', 'on_hold');
        $this->actingAs($pm)->putJson("/api/projects/{$project->id}", [
            'manager_id' => User::factory()->projectManager()->create()->id,
        ])->assertJsonValidationErrors('manager_id');
    }

    public function test_end_date_is_checked_against_the_stored_start_date(): void
    {
        $pm = User::factory()->projectManager()->create();
        $project = Project::factory()->create(['manager_id' => $pm->id, 'start_date' => '2027-03-01']);

        $this->actingAs($pm)->putJson("/api/projects/{$project->id}", ['end_date' => '2027-02-01'])
            ->assertJsonValidationErrors('end_date');
    }

    public function test_other_pm_and_members_cannot_update_a_project(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->teamMember()->create();
        $project->members()->attach($member);

        $this->actingAs(User::factory()->projectManager()->create())
            ->putJson("/api/projects/{$project->id}", ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($member)
            ->putJson("/api/projects/{$project->id}", ['name' => 'Hijacked'])->assertForbidden();
    }

    public function test_archiving_keeps_the_project_and_makes_it_read_only(): void
    {
        $pm = User::factory()->projectManager()->create();
        $project = Project::factory()->create(['manager_id' => $pm->id]);
        Task::factory()->for($project)->create();

        $this->actingAs($pm)->deleteJson("/api/projects/{$project->id}")->assertNoContent();

        $this->assertNotNull($project->fresh()->archived_at);
        $this->assertDatabaseCount('tasks', 1);
        $this->actingAs($pm)->putJson("/api/projects/{$project->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($pm)->getJson('/api/projects')->assertJsonCount(0, 'data');
        $this->actingAs($pm)->getJson('/api/projects?archived=1')->assertJsonCount(1, 'data');
    }

    public function test_only_admin_restores_projects(): void
    {
        $pm = User::factory()->projectManager()->create();
        $project = Project::factory()->archived()->create(['manager_id' => $pm->id]);

        $this->actingAs($pm)->postJson("/api/projects/{$project->id}/restore")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/projects/{$project->id}/restore")->assertOk();
    }
}
