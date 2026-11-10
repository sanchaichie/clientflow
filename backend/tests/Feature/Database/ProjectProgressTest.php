<?php

namespace Tests\Feature\Database;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_is_zero_for_a_project_without_tasks(): void
    {
        $project = Project::factory()->create();

        $this->assertSame(0, $project->recalculateProgress());
        $this->assertSame(0, $project->fresh()->progress);
    }

    public function test_progress_is_the_rounded_share_of_completed_tasks(): void
    {
        $project = Project::factory()->create();
        Task::factory()->completed()->for($project)->create();
        Task::factory(2)->withStatus(TaskStatus::Review)->for($project)->create();

        $this->assertSame(33, $project->recalculateProgress()); // 1/3

        $project->tasks()->where('status', TaskStatus::Review)->limit(1)->update(['status' => TaskStatus::Completed]);

        $this->assertSame(67, $project->recalculateProgress()); // 2/3, rounded
        $this->assertSame(67, $project->fresh()->progress);
    }

    public function test_progress_only_counts_this_projects_tasks(): void
    {
        $project = Project::factory()->create();
        Task::factory()->completed()->for($project)->create();
        Task::factory()->withStatus(TaskStatus::Todo)->for($project)->create();
        Task::factory(5)->completed()->create(); // other projects

        $this->assertSame(50, $project->recalculateProgress());
    }

    public function test_progress_is_not_mass_assignable(): void
    {
        $project = Project::factory()->create();

        $project->update(['progress' => 99]);

        $this->assertSame(0, $project->fresh()->progress);
    }
}
