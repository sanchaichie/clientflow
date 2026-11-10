<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards against N+1 queries (spec §8, §13 challenge 6): the number of
 * queries for a list page must not grow with the number of rows.
 */
class QueryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /** Seed $n rows of everything, then measure one list request. */
    private function queriesForListing(string $url, int $n, User $viewer): int
    {
        Project::factory($n)
            ->has(Task::factory(2)->state(['assignee_id' => $viewer->id]))
            ->create(['manager_id' => $viewer->id, 'client_id' => Client::factory()->create(['account_manager_id' => $viewer->id])->id])
            ->each(fn (Project $p) => $p->members()->attach($viewer));

        $this->actingAs($viewer);

        return $this->countQueries(fn () => $this->getJson($url)->assertOk());
    }

    public function test_project_listing_uses_a_constant_number_of_queries(): void
    {
        $small = $this->queriesForListing('/api/projects', 2, User::factory()->projectManager()->create());
        $this->refreshDatabase();
        $large = $this->queriesForListing('/api/projects', 12, User::factory()->projectManager()->create());

        $this->assertSame($small, $large, "Project listing ran {$small} queries for 2 rows but {$large} for 12.");
    }

    public function test_task_listing_uses_a_constant_number_of_queries(): void
    {
        $small = $this->queriesForListing('/api/tasks', 2, User::factory()->projectManager()->create());
        $this->refreshDatabase();
        $large = $this->queriesForListing('/api/tasks', 12, User::factory()->projectManager()->create());

        $this->assertSame($small, $large, "Task listing ran {$small} queries for 4 rows but {$large} for 24.");
    }

    public function test_client_listing_uses_a_constant_number_of_queries(): void
    {
        $small = $this->queriesForListing('/api/clients', 2, User::factory()->projectManager()->create());
        $this->refreshDatabase();
        $large = $this->queriesForListing('/api/clients', 12, User::factory()->projectManager()->create());

        $this->assertSame($small, $large);
    }
}
