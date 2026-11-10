<?php

namespace Database\Seeders;

use App\Enums\CommentType;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Services\TaskCommentService;
use App\Services\TaskService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Realistic demo data for local development and manual testing.
 * Every demo account uses the password "password" — never run in production.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Fixed, documented accounts — one per role (see README).
        $admin = User::factory()->admin()->create([
            'name' => 'Ada Admin',
            'email' => 'admin@clientflow.test',
        ]);
        $pm = User::factory()->projectManager()->create([
            'name' => 'Paula Manager',
            'email' => 'pm@clientflow.test',
        ]);
        $member = User::factory()->teamMember()->create([
            'name' => 'Tom Member',
            'email' => 'member@clientflow.test',
        ]);

        $managers = User::factory(2)->projectManager()->create()->push($pm);
        $members = User::factory(6)->teamMember()->create()->push($member);
        User::factory()->teamMember()->inactive()->create(['name' => 'Ivy Inactive']);

        $clients = Client::factory(12)
            ->sequence(fn () => [
                'account_manager_id' => $managers->random()->id,
                'created_by' => $admin->id,
            ])
            ->create();
        Client::factory(2)->archived()->create(['created_by' => $admin->id]);

        foreach ($clients->random(8) as $client) {
            $this->seedProjectsFor($client, $managers, $members);
        }

        // Make sure the demo member always has something to see.
        $project = Project::query()->where('manager_id', $pm->id)->first()
            ?? Project::factory()->active()->for($clients->first())->create(['manager_id' => $pm->id]);
        $project->members()->syncWithoutDetaching([$member->id]);
        Task::factory()->overdue()->for($project)->create(['assignee_id' => $member->id, 'created_by' => $pm->id]);
        Task::factory()->dueToday()->for($project)->create(['assignee_id' => $member->id, 'created_by' => $pm->id]);
        $project->recalculateProgress();

        $this->seedCollaborationStory($project, $pm, $member);
    }

    /**
     * A short story played through the real services, so the demo accounts
     * start with genuine activity history and unread notifications.
     */
    private function seedCollaborationStory(Project $project, User $pm, User $member): void
    {
        $tasks = app(TaskService::class);
        $comments = app(TaskCommentService::class);

        $task = $tasks->create($project, [
            'title' => 'Prepare the client kickoff deck',
            'description' => 'Outline goals, timeline and responsibilities for the kickoff call.',
            'priority' => 'high',
            'due_date' => today()->addDays(3)->toDateString(),
            'assignee_id' => $member->id,
        ], $pm);                                                         // → member notified (assigned)

        $tasks->changeStatus($task, TaskStatus::InProgress, null, $member); // → PM notified (status)
        $comments->create($task, [
            'type' => CommentType::WorkUpdate->value,
            'body' => 'First draft is done — slides 1–8 ready, still waiting on the timeline.',
        ], $member);                                                     // → PM notified (work update)
        $comments->create($task, [
            'body' => 'Looks good! I will send you the timeline this afternoon.',
        ], $pm);                                                         // → member notified (comment)
    }

    /**
     * @param  Collection<int, User>  $managers
     * @param  Collection<int, User>  $members
     */
    private function seedProjectsFor(Client $client, $managers, $members): void
    {
        $projects = Project::factory(fake()->numberBetween(1, 2))
            ->for($client)
            ->create(['manager_id' => $managers->random()->id]);

        foreach ($projects as $project) {
            $team = $members->random(fake()->numberBetween(2, 4));
            $project->members()->attach($team->pluck('id'));

            $tasks = Task::factory(fake()->numberBetween(4, 10))
                ->for($project)
                ->sequence(fn () => [
                    'assignee_id' => $team->random()->id,
                    'created_by' => $project->manager_id,
                ])
                ->create();

            // Members plus the project's manager may comment. concat() returns a new
            // collection; push() would mutate $team on every call.
            $participants = $team->concat([$managers->firstWhere('id', $project->manager_id)]);

            foreach ($tasks->random(min(3, $tasks->count())) as $task) {
                TaskComment::factory(fake()->numberBetween(1, 3))
                    ->for($task)
                    ->sequence(fn () => [
                        'user_id' => $participants->random()->id,
                        'type' => fake()->boolean(25) ? CommentType::WorkUpdate : CommentType::Comment,
                    ])
                    ->create();
            }

            $project->recalculateProgress();
        }
    }
}
