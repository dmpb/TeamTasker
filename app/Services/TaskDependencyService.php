<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskDependency;
use App\Repositories\TaskDependencyRepository;
use InvalidArgumentException;

class TaskDependencyService
{
    public function __construct(protected TaskDependencyRepository $dependencyRepository) {}

    public function addDependency(Task $dependent, Task $prerequisite): void
    {
        if ($dependent->project_id !== $prerequisite->project_id) {
            throw new InvalidArgumentException(__('Tasks must belong to the same project.'));
        }

        if ($dependent->is($prerequisite)) {
            throw new InvalidArgumentException(__('A task cannot depend on itself.'));
        }

        if ($this->dependencyRepository->exists($dependent, $prerequisite)) {
            throw new InvalidArgumentException(__('This dependency already exists.'));
        }

        if ($this->wouldCreateCycle($dependent, $prerequisite)) {
            throw new InvalidArgumentException(__('This dependency would create a cycle.'));
        }

        $this->dependencyRepository->create($dependent, $prerequisite);
    }

    public function removeDependency(Task $dependent, Task $prerequisite): void
    {
        $this->dependencyRepository->delete($dependent, $prerequisite);
    }

    private function wouldCreateCycle(Task $dependent, Task $prerequisite): bool
    {
        $stack = [$prerequisite->id];
        $visited = [];

        while ($stack !== []) {
            $id = (int) array_pop($stack);

            if ($id === $dependent->id) {
                return true;
            }

            if (isset($visited[$id])) {
                continue;
            }

            $visited[$id] = true;

            $next = TaskDependency::query()
                ->where('dependent_task_id', $id)
                ->pluck('prerequisite_task_id')
                ->all();

            foreach ($next as $pid) {
                $stack[] = (int) $pid;
            }
        }

        return false;
    }
}
