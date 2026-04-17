<?php

namespace App\Repositories;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class CommentRepository
{
    /**
     * @return Collection<int, Comment>
     */
    public function listCommentsForTask(Task $task, ?string $bodySearch = null): Collection
    {
        $query = Comment::query()
            ->where('task_id', $task->id)
            ->with(['user', 'task.project'])
            ->orderBy('id');

        if ($bodySearch !== null && $bodySearch !== '') {
            $escaped = addcslashes($bodySearch, '%_\\');
            $query->where('body', 'like', '%'.$escaped.'%');
        }

        return $query->get();
    }

    public function createComment(Task $task, User $user, string $body): Comment
    {
        return Comment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => $body,
        ]);
    }

    public function updateComment(Comment $comment, string $body): Comment
    {
        $comment->update([
            'body' => $body,
        ]);

        return $comment->refresh();
    }

    public function deleteComment(Comment $comment): void
    {
        $comment->delete();
    }
}
