<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $project = Project::factory()->create();
        $column = Column::factory()->forProject($project)->create();
        $task = Task::factory()->forColumn($column)->create();

        return [
            'project_id' => $project->id,
            'actor_id' => User::factory(),
            'event' => fake()->randomElement([
                'task.created',
                'task.updated',
                'task.moved',
                'task.deleted',
                'comment.created',
                'comment.updated',
                'comment.deleted',
            ]),
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'metadata' => null,
        ];
    }
}
