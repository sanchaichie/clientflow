<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Spec §10: "All protected operations must enforce authorization on the
 * backend." One readable table: each role × each protected operation on
 * records the user does NOT own → expected HTTP status.
 *
 * "owner" = the PM who manages the project/client; the member belongs to the
 * project. "foreign" records belong to someone else entirely.
 */
class RoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, User> */
    private array $users;

    private Project $project;

    private Project $foreignProject;

    private Task $task;

    private Task $foreignTask;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = [
            'guest' => new User,
            'admin' => User::factory()->admin()->create(),
            'pm' => $pm = User::factory()->projectManager()->create(),
            'member' => $member = User::factory()->teamMember()->create(),
            'outsider' => User::factory()->teamMember()->create(),
        ];

        $this->client = Client::factory()->create(['account_manager_id' => $pm->id]);
        $this->project = Project::factory()->active()->for($this->client)->create(['manager_id' => $pm->id]);
        $this->project->members()->attach($member);
        $this->task = Task::factory()->for($this->project)->create(['assignee_id' => null, 'status' => 'todo']);

        $this->foreignProject = Project::factory()->active()->create();
        $this->foreignTask = Task::factory()->for($this->foreignProject)->create(['status' => 'todo']);
    }

    /**
     * [role, method, url-template, expected]
     * Placeholders: {project} {foreignProject} {task} {foreignTask} {client}
     */
    public static function matrix(): array
    {
        $rows = [
            // Reports & user management
            ['GET', '/api/reports', ['guest' => 401, 'admin' => 200, 'pm' => 200, 'member' => 403]],
            ['GET', '/api/users', ['guest' => 401, 'admin' => 200, 'pm' => 200, 'member' => 403]],
            ['POST', '/api/users', ['guest' => 401, 'admin' => 422, 'pm' => 403, 'member' => 403]],
            // Clients
            ['GET', '/api/clients', ['guest' => 401, 'admin' => 200, 'pm' => 200, 'member' => 403]],
            ['GET', '/api/clients/{client}', ['guest' => 401, 'admin' => 200, 'pm' => 200, 'member' => 403]],
            ['DELETE', '/api/clients/{client}', ['guest' => 401, 'admin' => 204, 'pm' => 204, 'member' => 403]],
            ['POST', '/api/clients/{client}/restore', ['guest' => 401, 'admin' => 200, 'pm' => 403, 'member' => 403]],
            // Projects
            ['GET', '/api/projects/{project}', ['guest' => 401, 'admin' => 200, 'pm' => 200, 'member' => 200, 'outsider' => 403]],
            ['GET', '/api/projects/{foreignProject}', ['admin' => 200, 'pm' => 403, 'member' => 403]],
            ['PUT', '/api/projects/{project}', ['admin' => 200, 'pm' => 200, 'member' => 403, 'outsider' => 403]],
            ['PUT', '/api/projects/{foreignProject}', ['pm' => 403, 'member' => 403]],
            ['DELETE', '/api/projects/{foreignProject}', ['admin' => 204, 'pm' => 403, 'member' => 403]],
            // Tasks
            ['GET', '/api/tasks/{task}', ['admin' => 200, 'pm' => 200, 'member' => 200, 'outsider' => 403]],
            ['GET', '/api/tasks/{foreignTask}', ['pm' => 403, 'member' => 403]],
            ['PUT', '/api/tasks/{task}', ['admin' => 200, 'pm' => 200, 'member' => 403]],
            ['PATCH', '/api/tasks/{task}/status', ['admin' => 200, 'pm' => 200, 'member' => 403, 'outsider' => 403]],
            ['DELETE', '/api/tasks/{foreignTask}', ['admin' => 204, 'pm' => 403, 'member' => 403]],
            ['POST', '/api/tasks/{task}/comments', ['admin' => 201, 'pm' => 201, 'member' => 201, 'outsider' => 403]],
            ['GET', '/api/tasks/{foreignTask}/comments', ['pm' => 403, 'member' => 403]],
        ];

        $cases = [];
        foreach ($rows as [$method, $url, $expectations]) {
            foreach ($expectations as $role => $status) {
                $cases["{$role} {$method} {$url} → {$status}"] = [$role, $method, $url, $status];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_role_matrix(string $role, string $method, string $url, int $expected): void
    {
        $url = strtr($url, [
            '{project}' => $this->project->id,
            '{foreignProject}' => $this->foreignProject->id,
            '{task}' => $this->task->id,
            '{foreignTask}' => $this->foreignTask->id,
            '{client}' => $this->client->id,
        ]);

        // Minimal valid bodies so allowed writes succeed (or 422 where noted).
        $body = match (true) {
            str_ends_with($url, '/status') => ['status' => 'review'],
            str_ends_with($url, '/comments') => ['body' => 'Hello'],
            $method === 'PUT' => ['name' => 'Renamed', 'title' => 'Renamed'],
            default => [],
        };

        if ($role !== 'guest') {
            $this->actingAs($this->users[$role]);
        }

        $this->json($method, $url, $body)->assertStatus($expected);
    }
}
