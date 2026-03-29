<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Task;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $task = Task::factory()->create();
        $team = $task->project->team;
        $author = User::factory()->create();
        TeamMember::factory()->forTeam($team)->forUser($author)->create();

        return [
            'task_id' => $task->id,
            'user_id' => $author->id,
            'body' => fake()->sentence(10),
        ];
    }

    public function forTask(Task $task): static
    {
        return $this->state(fn (array $attributes): array => [
            'task_id' => $task->id,
        ]);
    }

    public function byUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
        ]);
    }
}
