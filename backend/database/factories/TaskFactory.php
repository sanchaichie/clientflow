<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(TaskStatus::cases());

        return [
            'project_id' => Project::factory(),
            'assignee_id' => null,
            'created_by' => null,
            'title' => rtrim(fake()->sentence(5), '.'),
            'description' => fake()->optional()->paragraph(),
            'status' => $status,
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'due_date' => fake()->optional(0.8)->dateTimeBetween('-2 weeks', '+4 weeks'),
            'position' => fake()->numberBetween(0, 1000),
            'completed_at' => $status === TaskStatus::Completed ? now() : null,
        ];
    }

    public function withStatus(TaskStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'completed_at' => $status === TaskStatus::Completed ? now() : null,
        ]);
    }

    public function completed(): static
    {
        return $this->withStatus(TaskStatus::Completed);
    }

    public function overdue(): static
    {
        return $this->withStatus(TaskStatus::InProgress)
            ->state(fn () => ['due_date' => today()->subDays(3)]);
    }

    public function dueToday(): static
    {
        return $this->withStatus(TaskStatus::Todo)
            ->state(fn () => ['due_date' => today()]);
    }
}
