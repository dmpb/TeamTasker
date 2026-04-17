<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;

class CommentPolicy
{
    public function view(User $user, Comment $comment): bool
    {
        return $user->can('view', $comment->task->project);
    }

    /**
     * Add a comment when the user can view the task's project.
     */
    public function create(User $user, Task $task): bool
    {
        return $task->project->archived_at === null
            && $user->can('view', $task->project);
    }

    public function update(User $user, Comment $comment): bool
    {
        if ($comment->task->project->archived_at !== null) {
            return false;
        }

        if ($comment->user_id === $user->id) {
            return $user->can('view', $comment->task->project);
        }

        return $user->can('update', $comment->task->project);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->update($user, $comment);
    }
}
