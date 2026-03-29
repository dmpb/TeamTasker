<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Repositories\CommentRepository;
use Illuminate\Database\Eloquent\Collection;

class CommentService
{
    public function __construct(public CommentRepository $commentRepository) {}

    /**
     * @return Collection<int, Comment>
     */
    public function listTaskComments(Task $task): Collection
    {
        return $this->commentRepository->listCommentsForTask($task);
    }

    public function createComment(Task $task, User $user, string $body): Comment
    {
        return $this->commentRepository->createComment($task, $user, $body);
    }

    public function updateComment(Comment $comment, string $body): Comment
    {
        return $this->commentRepository->updateComment($comment, $body);
    }

    public function deleteComment(Comment $comment): void
    {
        $this->commentRepository->deleteComment($comment);
    }
}
