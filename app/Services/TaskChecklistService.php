<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use App\Repositories\TaskChecklistItemRepository;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class TaskChecklistService
{
    public function __construct(protected TaskChecklistItemRepository $checklistRepository) {}

    /**
     * @return Collection<int, TaskChecklistItem>
     */
    public function listItems(Task $task): Collection
    {
        return $this->checklistRepository->listOrderedForTask($task);
    }

    public function addItem(Task $task, string $title, ?User $actor = null): TaskChecklistItem
    {
        $normalized = trim($title);

        if ($normalized === '') {
            throw new InvalidArgumentException(__('Checklist item title is required.'));
        }

        return $this->checklistRepository->create($task, $normalized, $actor);
    }

    public function updateItem(Task $task, TaskChecklistItem $item, string $title, bool $isCompleted): TaskChecklistItem
    {
        $this->assertItemBelongsToTask($item, $task);

        return $this->checklistRepository->updateItem($item, trim($title), $isCompleted);
    }

    public function deleteItem(Task $task, TaskChecklistItem $item): void
    {
        $this->assertItemBelongsToTask($item, $task);
        $this->checklistRepository->deleteItem($item);
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorderItems(Task $task, array $orderedIds): void
    {
        $this->checklistRepository->reorderForTask($task, $orderedIds);
    }

    private function assertItemBelongsToTask(TaskChecklistItem $item, Task $task): void
    {
        if ((int) $item->task_id !== (int) $task->id) {
            throw new InvalidArgumentException(__('Checklist item does not belong to this task.'));
        }
    }
}
