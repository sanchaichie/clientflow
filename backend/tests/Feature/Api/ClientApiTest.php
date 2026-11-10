<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Acme Corp',
            'contact_person' => 'Jane Doe',
            'email' => 'jane@acme.test',
            'phone' => '+1 555 0100',
            'status' => 'lead',
        ], $overrides);
    }

    // --- Create -------------------------------------------------------------

    public function test_pm_creates_a_client_that_is_assigned_to_them(): void
    {
        $pm = User::factory()->projectManager()->create();

        $this->actingAs($pm)->postJson('/api/clients', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.company_name', 'Acme Corp')
            ->assertJsonPath('data.status', 'lead')
            ->assertJsonPath('data.account_manager_id', $pm->id);

        $this->assertDatabaseHas('clients', ['company_name' => 'Acme Corp', 'created_by' => $pm->id]);
    }

    public function test_pm_cannot_assign_a_client_to_someone_else(): void
    {
        $pm = User::factory()->projectManager()->create();
        $otherPm = User::factory()->projectManager()->create();

        $this->actingAs($pm)->postJson('/api/clients', $this->validPayload(['account_manager_id' => $otherPm->id]))
            ->assertJsonValidationErrors('account_manager_id');
    }

    public function test_admin_can_assign_an_account_manager_but_only_an_active_pm(): void
    {
        $admin = User::factory()->admin()->create();
        $pm = User::factory()->projectManager()->create();
        $member = User::factory()->teamMember()->create();

        $this->actingAs($admin)->postJson('/api/clients', $this->validPayload(['account_manager_id' => $pm->id]))
            ->assertCreated()->assertJsonPath('data.account_manager_id', $pm->id);

        $this->actingAs($admin)->postJson('/api/clients', $this->validPayload(['account_manager_id' => $member->id]))
            ->assertJsonValidationErrors('account_manager_id');
    }

    public function test_client_creation_is_validated(): void
    {
        $this->actingAs(User::factory()->projectManager()->create())
            ->postJson('/api/clients', ['company_name' => '', 'email' => 'not-an-email', 'status' => 'vip'])
            ->assertJsonValidationErrors(['company_name', 'email', 'status']);
    }

    public function test_team_member_cannot_create_or_list_clients(): void
    {
        $member = User::factory()->teamMember()->create();

        $this->actingAs($member)->postJson('/api/clients', $this->validPayload())->assertForbidden();
        $this->actingAs($member)->getJson('/api/clients')->assertForbidden();
    }

    // --- Read ---------------------------------------------------------------

    public function test_pm_lists_only_visible_clients(): void
    {
        $pm = User::factory()->projectManager()->create();
        $mine = Client::factory()->create(['account_manager_id' => $pm->id]);
        $viaProject = Project::factory()->create(['manager_id' => $pm->id])->client;
        Client::factory(3)->create(); // someone else's

        $ids = $this->actingAs($pm)->getJson('/api/clients')->assertOk()->json('data.*.id');

        $this->assertEqualsCanonicalizing([$mine->id, $viaProject->id], $ids);
    }

    public function test_clients_can_be_searched_filtered_and_paginated(): void
    {
        $admin = User::factory()->admin()->create();
        Client::factory()->active()->create(['company_name' => 'Northwind Traders']);
        Client::factory()->lead()->create(['company_name' => 'Northwind Labs']);
        Client::factory(5)->active()->create();

        $this->actingAs($admin)->getJson('/api/clients?search=northwind')->assertJsonCount(2, 'data');
        $this->actingAs($admin)->getJson('/api/clients?search=northwind&status=lead')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.company_name', 'Northwind Labs');
        $this->actingAs($admin)->getJson('/api/clients?per_page=3')
            ->assertJsonCount(3, 'data')->assertJsonPath('meta.total', 7);
    }

    public function test_archived_clients_are_hidden_unless_requested(): void
    {
        $admin = User::factory()->admin()->create();
        Client::factory(2)->create();
        $archived = Client::factory()->archived()->create();

        $this->actingAs($admin)->getJson('/api/clients')->assertJsonCount(2, 'data');
        $this->actingAs($admin)->getJson('/api/clients?archived=1')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $archived->id);
    }

    public function test_client_detail_shows_only_projects_the_user_can_see(): void
    {
        $pm = User::factory()->projectManager()->create();
        $client = Client::factory()->create(['account_manager_id' => $pm->id]);
        $mine = Project::factory()->for($client)->create(['manager_id' => $pm->id]);
        Project::factory()->for($client)->create(); // another PM's project

        $this->actingAs($pm)->getJson("/api/clients/{$client->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonPath('data.projects.0.id', $mine->id);
    }

    public function test_pm_cannot_view_an_unrelated_client(): void
    {
        $this->actingAs(User::factory()->projectManager()->create())
            ->getJson('/api/clients/'.Client::factory()->create()->id)
            ->assertForbidden();
    }

    // --- Update / archive -----------------------------------------------------

    public function test_account_manager_can_update_their_client(): void
    {
        $pm = User::factory()->projectManager()->create();
        $client = Client::factory()->create(['account_manager_id' => $pm->id]);

        $this->actingAs($pm)->putJson("/api/clients/{$client->id}", ['status' => 'inactive', 'phone' => '123'])
            ->assertOk()->assertJsonPath('data.status', 'inactive')->assertJsonPath('data.phone', '123');
    }

    public function test_pm_cannot_update_a_client_they_only_see_through_a_project(): void
    {
        $pm = User::factory()->projectManager()->create();
        $client = Project::factory()->create(['manager_id' => $pm->id])->client;

        $this->actingAs($pm)->putJson("/api/clients/{$client->id}", ['phone' => '123'])->assertForbidden();
    }

    public function test_delete_archives_and_archived_clients_become_read_only(): void
    {
        $pm = User::factory()->projectManager()->create();
        $client = Client::factory()->create(['account_manager_id' => $pm->id]);

        $this->actingAs($pm)->deleteJson("/api/clients/{$client->id}")->assertNoContent();

        $this->assertNotNull($client->fresh()->archived_at);
        $this->assertDatabaseHas('clients', ['id' => $client->id]); // not deleted
        $this->actingAs($pm)->putJson("/api/clients/{$client->id}", ['phone' => '1'])->assertForbidden();
        $this->actingAs($pm)->postJson("/api/clients/{$client->id}/restore")->assertForbidden();
    }

    public function test_admin_can_restore_an_archived_client(): void
    {
        $client = Client::factory()->archived()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/clients/{$client->id}/restore")
            ->assertOk()->assertJsonPath('data.archived_at', null);
    }
}
