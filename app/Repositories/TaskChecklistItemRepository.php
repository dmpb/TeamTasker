<?php

namespace App\Repositories;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskChecklistItemRepository
{
    public function nextPosition(Task $task): int
    {
        $max = TaskChecklistItem::query()->where('task_id', $task->id)->max('position');

        return $max === null ? 0 : (int) $max + 1;
    }

    /**
     * @return Collection<int, TaskChecklistItem>
     */
    public function listOrderedForTask(Task $task): Collection
    {
        return TaskChecklistItem::query()
            ->where('task_id', $task->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function create(Task $task, string $title, ?User $creator = null): TaskChecklistItem
    {
        return TaskChecklistItem::query()->create([
            'task_id' => $task->id,
            'title' => $title,
            'position' => $this->nextPosition($task),
            'is_completed' => false,
            'created_by' => $creator?->id,
        ]);
    }

    public function updateItem(TaskChecklistItem $item, string $title, bool $isCompleted): TaskChecklistItem
    {
        $item->update([
            'title' => $title,
            'is_completed' => $isCompleted,
        ]);

        return $item->refresh();
    }

    public function deleteItem(TaskChecklistItem $item): void
    {
        $item->delete();
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorderForTask(Task $task, array $orderedIds): void
    {
        DB::transaction(function () use ($task, $orderedIds): void {
            $items = TaskChecklistItem::query()
                ->where('task_id', $task->id)
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->count() !== count($orderedIds)) {
                throw new InvalidArgumentException('Invalid checklist reorder payload.');
            }

            $ids = $items->pluck('id')->all();
            sort($ids);
            $sortedPayload = $orderedIds;
            sort($sortedPayload);

            if ($ids !== $sortedPayload) {
                throw new InvalidArgumentException('Invalid checklist reorder payload.');
            }

            foreach ($orderedIds as $position => $id) {
                TaskChecklistItem::query()
                    ->where('task_id', $task->id)
                    ->whereKey($id)
                    ->update(['position' => $position]);
            }
        });
    }
}
