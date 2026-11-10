<?php

namespace Tests\Feature\Database;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_is_idempotent(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame(count(RoleSlug::cases()), Role::count());
    }

    public function test_demo_accounts_exist_for_each_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::firstWhere('email', 'admin@clientflow.test')->isAdmin());
        $this->assertTrue(User::firstWhere('email', 'pm@clientflow.test')->isProjectManager());
        $this->assertTrue(User::firstWhere('email', 'member@clientflow.test')->isTeamMember());
    }

    public function test_demo_data_is_not_seeded_in_production(): void
    {
        $this->app['env'] = 'production';

        // In production db:seed asks for confirmation; deploy scripts pass --force.
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(count(RoleSlug::cases()), Role::count());
        $this->assertSame(0, User::count());
    }
}
