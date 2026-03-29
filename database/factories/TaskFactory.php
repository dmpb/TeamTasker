<?php

namespace Database\Factories;

use App\Models\Column;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $column = Column::factory()->create();

        return [
            'project_id' => $column->project_id,
            'column_id' => $column->id,
            'assignee_id' => null,
            'title' => fake()->sentence(3),
            'description' => null,
            'position' => 0,
        ];
    }

    public function forColumn(Column $column): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $column->project_id,
            'column_id' => $column->id,
        ]);
    }

    public function atPosition(int $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }
}
