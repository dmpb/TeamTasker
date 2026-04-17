<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\CommentRepository;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class CommentService
{
    public function __construct(
        public CommentRepository $commentRepository,
        public ActivityLogService $activityLogService,
    ) {}

    /**
     * @return Collection<int, Comment>
     */
    public function listTaskComments(Task $task, ?string $bodySearch = null): Collection
    {
        return $this->commentRepository->listCommentsForTask($task, $bodySearch);
    }

    public function createComment(Project $project, Task $task, User $user, string $body, ?User $actor = null): Comment
    {
        $this->assertTaskBelongsToProject($task, $project);

        $comment = $this->commentRepository->createComment($task, $user, $body);
        $this->activityLogService->recordCommentCreated($comment, $actor ?? $user);

        return $comment;
    }

    public function updateComment(Project $project, Task $task, Comment $comment, string $body, ?User $actor = null): Comment
    {
        $this->assertTaskBelongsToProject($task, $project);
        $this->assertCommentBelongsToTask($comment, $task);

        $updatedComment = $this->commentRepository->updateComment($comment, $body);
        $this->activityLogService->recordCommentUpdated($updatedComment, $actor);

        return $updatedComment;
    }

    public function deleteComment(Project $project, Task $task, Comment $comment, ?User $actor = null): void
    {
        $this->assertTaskBelongsToProject($task, $project);
        $this->assertCommentBelongsToTask($comment, $task);

        $this->activityLogService->recordCommentDeleted($comment, $actor);
        $this->commentRepository->deleteComment($comment);
    }

    private function assertTaskBelongsToProject(Task $task, Project $project): void
    {
        if ($task->project_id !== $project->id) {
            throw new InvalidArgumentException('Task does not belong to the given project.');
        }
    }

    private function assertCommentBelongsToTask(Comment $comment, Task $task): void
    {
        if ($comment->task_id !== $task->id) {
            throw new InvalidArgumentException('Comment does not belong to the given task.');
        }
    }
}
