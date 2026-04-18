<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCommentCreatedNotification;

class NotificationService
{
    public function notifyTaskAssigned(Task $task, ?User $actor): void
    {
        if ($task->assignee_id === null) {
            return;
        }

        if ($actor !== null && (int) $actor->id === (int) $task->assignee_id) {
            return;
        }

        $assignee = User::query()->find($task->assignee_id);

        if ($assignee === null) {
            return;
        }

        $assignee->notify(new TaskAssignedNotification($task, $actor));
    }

    public function notifyCommentOnAssignedTask(Task $task, User $commentAuthor): void
    {
        if ($task->assignee_id === null || (int) $task->assignee_id === (int) $commentAuthor->id) {
            return;
        }

        $assignee = User::query()->find($task->assignee_id);

        if ($assignee === null) {
            return;
        }

        $assignee->notify(new TaskCommentCreatedNotification($task, $commentAuthor));
    }
}
