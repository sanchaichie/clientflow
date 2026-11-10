<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMemberApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_adds_members_without_duplicates(): void
    {
        $pm = User::factory()->projectManager()->create();
        $project = Project::factory()->create(['manager_id' => $pm->id]);
        [$a, $b] = User::factory(2)->teamMember()->create();
        $project->members()->attach($a);

        $this->actingAs($pm)->postJson("/api/projects/{$project->id}/members", ['user_ids' => [$a->id, $b->id]])
            ->assertOk()->assertJsonCount(2, 'data');

        $this->assertDatabaseCount('project_members', 2);
    }

    public function test_inactive_users_and_admins_cannot_be_added(): void
    {
        $pm = User::factory()->projectManager()->create();
        $project = Project::factory()->create(['manager_id' => $pm->id]);

        $this->actingAs($pm)->postJson("/api/projects/{$project->id}/members", [
            'user_ids' => [User::factory()->inactive()->create()->id, User::factory()->admin()->create()->id],
        ])->assertJsonValidationErrors(['user_ids.0', 'user_ids.1']);
    }

    public function test_removing_a_member_unassigns_their_tasks_in_that_project_only(): void
    {
        $pm = User::factory()->projectManager()->create();
        $member = User::factory()->teamMember()->create();
        $project = Project::factory()->create(['manager_id' => $pm->id]);
        $other = Project::factory()->create();
        $project->members()->attach($member);
        $other->members()->attach($member);
        $here = Task::factory()->for($project)->create(['assignee_id' => $member->id]);
        $elsewhere = Task::factory()->for($other)->create(['assignee_id' => $member->id]);

        $this->actingAs($pm)->deleteJson("/api/projects/{$project->id}/members/{$member->id}")->assertNoContent();

        $this->assertFalse($project->hasMember($member));
        $this->assertNull($here->fresh()->assignee_id);
        $this->assertSame($member->id, $elsewhere->fresh()->assignee_id);
    }

    public function test_removing_a_non_member_returns_404(): void
    {
        $pm = User::factory()->projectManager()->create();
        $project = Project::factory()->create(['manager_id' => $pm->id]);

        $this->actingAs($pm)
            ->deleteJson("/api/projects/{$project->id}/members/".User::factory()->create()->id)
            ->assertNotFound();
    }

    public function test_only_the_manager_can_change_membership(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->teamMember()->create();
        $project->members()->attach($member);
        $newcomer = User::factory()->teamMember()->create();

        $this->actingAs($member)->postJson("/api/projects/{$project->id}/members", ['user_ids' => [$newcomer->id]])
            ->assertForbidden();
        $this->actingAs(User::factory()->projectManager()->create())
            ->deleteJson("/api/projects/{$project->id}/members/{$member->id}")
            ->assertForbidden();
    }
}
