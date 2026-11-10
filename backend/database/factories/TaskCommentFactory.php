<?php

namespace Database\Factories;

use App\Enums\CommentType;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskComment>
 */
class TaskCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'type' => CommentType::Comment,
            'body' => fake()->sentences(2, true),
        ];
    }

    public function workUpdate(): static
    {
        return $this->state(fn () => ['type' => CommentType::WorkUpdate]);
    }
}
