<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_user_who_receives_a_set_password_link(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/users', ['name' => 'New Person', 'email' => 'new@example.com', 'role' => 'project_manager'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.role.slug', 'project_manager');

        $user = User::firstWhere('email', 'new@example.com');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_project_manager_and_member_cannot_create_users(): void
    {
        $payload = ['name' => 'X', 'email' => 'x@example.com', 'role' => 'admin'];

        $this->actingAs(User::factory()->projectManager()->create())
            ->postJson('/api/users', $payload)->assertForbidden();
        $this->actingAs(User::factory()->teamMember()->create())
            ->postJson('/api/users', $payload)->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_user_creation_is_validated(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/users', ['name' => '', 'email' => $existing->email, 'role' => 'superuser'])
            ->assertJsonValidationErrors(['name', 'email', 'role']);
    }

    public function test_project_manager_can_list_users_but_member_cannot(): void
    {
        User::factory(3)->create();

        $this->actingAs(User::factory()->projectManager()->create())
            ->getJson('/api/users?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'links', 'meta' => ['total', 'per_page']]);

        $this->actingAs(User::factory()->teamMember()->create())
            ->getJson('/api/users')->assertForbidden();
    }

    public function test_users_can_be_filtered_by_role_and_search(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Zed Admin']);
        User::factory()->projectManager()->create(['name' => 'Paula PM']);
        User::factory()->teamMember()->create(['name' => 'Tom Member']);

        $this->actingAs($admin)->getJson('/api/users?role=project_manager')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Paula PM');
        $this->actingAs($admin)->getJson('/api/users?search=tom')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Tom Member');
    }

    public function test_role_cannot_be_changed_by_a_non_admin_even_on_themselves(): void
    {
        $pm = User::factory()->projectManager()->create();

        $this->actingAs($pm)->putJson("/api/users/{$pm->id}", ['role' => 'admin'])->assertForbidden();

        $this->assertTrue($pm->fresh()->isProjectManager());
    }

    public function test_admin_can_change_a_users_role(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->teamMember()->create();

        $this->actingAs($admin)->putJson("/api/users/{$member->id}", ['role' => 'project_manager'])
            ->assertOk()->assertJsonPath('data.role.slug', 'project_manager');
    }

    public function test_admin_cannot_demote_or_deactivate_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->putJson("/api/users/{$admin->id}", ['role' => 'team_member', 'is_active' => false])
            ->assertJsonValidationErrors(['role', 'is_active']);
        $this->actingAs($admin)->deleteJson("/api/users/{$admin->id}")->assertUnprocessable();

        $this->assertTrue($admin->fresh()->isAdmin());
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_deactivating_a_user_keeps_the_record_and_ends_their_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'member-session', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($admin)->deleteJson("/api/users/{$member->id}")->assertNoContent();

        $this->assertFalse($member->fresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['id' => 'member-session']);
    }

    public function test_missing_user_returns_a_generic_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/users/999999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }
}
