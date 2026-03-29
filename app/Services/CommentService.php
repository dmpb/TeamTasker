<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Repositories\CommentRepository;
use Illuminate\Database\Eloquent\Collection;

class CommentService
{
    public function __construct(
        public CommentRepository $commentRepository,
        public ActivityLogService $activityLogService,
    ) {}

    /**
     * @return Collection<int, Comment>
     */
    public function listTaskComments(Task $task): Collection
    {
        return $this->commentRepository->listCommentsForTask($task);
    }

    public function createComment(Task $task, User $user, string $body, ?User $actor = null): Comment
    {
        $comment = $this->commentRepository->createComment($task, $user, $body);
        $this->activityLogService->recordCommentCreated($comment, $actor ?? $user);

        return $comment;
    }

    public function updateComment(Comment $comment, string $body, ?User $actor = null): Comment
    {
        $updatedComment = $this->commentRepository->updateComment($comment, $body);
        $this->activityLogService->recordCommentUpdated($updatedComment, $actor);

        return $updatedComment;
    }

    public function deleteComment(Comment $comment, ?User $actor = null): void
    {
        $this->activityLogService->recordCommentDeleted($comment, $actor);
        $this->commentRepository->deleteComment($comment);
    }
}
