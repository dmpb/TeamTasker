<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\ActivityLogRepository;
use Illuminate\Database\Eloquent\Collection;

class ActivityLogService
{
    public function __construct(public ActivityLogRepository $activityLogRepository) {}

    /**
     * @return Collection<int, ActivityLog>
     */
    public function listProjectLogs(
        Project $project,
        int $limit = 50,
        ?string $event = null,
        ?int $actorId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $query = null,
    ): Collection {
        return $this->activityLogRepository->listForProject(
            $project,
            $limit,
            $event,
            $actorId,
            $dateFrom,
            $dateTo,
            $query,
        );
    }

    public function recordTaskCreated(Task $task, ?User $actor = null): void
    {
        $this->activityLogRepository->createLog(
            project: $task->project,
            actor: $actor,
            event: 'task.created',
            subject: $task,
            metadata: [
                'column_id' => $task->column_id,
                'assignee_id' => $task->assignee_id,
            ],
        );
    }

    public function recordTaskUpdated(Task $task, ?User $actor = null): void
    {
        $this->activityLogRepository->createLog(
            project: $task->project,
            actor: $actor,
            event: 'task.updated',
            subject: $task,
        );
    }

    public function recordTaskMoved(Task $task, int $fromColumnId, int $toColumnId, ?User $actor = null): void
    {
        $this->activityLogRepository->createLog(
            project: $task->project,
            actor: $actor,
            event: 'task.moved',
            subject: $task,
            metadata: [
                'from_column_id' => $fromColumnId,
                'to_column_id' => $toColumnId,
            ],
        );
    }

    public function recordTaskDeleted(Task $task, ?User $actor = null): void
    {
        $this->activityLogRepository->createLog(
            project: $task->project,
            actor: $actor,
            event: 'task.deleted',
            subject: $task,
        );
    }

    public function recordCommentCreated(Comment $comment, ?User $actor = null): void
    {
        $this->activityLogRepository->createLog(
            project: $comment->task->project,
            actor: $actor,
            event: 'comment.created',
            subject: $comment,
            metadata: [
                'task_id' => $comment->task_id,
            ],
        );
    }

    public function recordCommentUpdated(Comment $comment, ?User $actor = null): void
    {
        $this->activityLogRepository->createLog(
            project: $comment->task->project,
            actor: $actor,
            event: 'comment.updated',
            subject: $comment,
            metadata: [
                'task_id' => $comment->task_id,
            ],
        );
    }

    public function recordCommentDeleted(Comment $comment, ?User $actor = null): void
    {
        $this->activityLogRepository->createLog(
            project: $comment->task->project,
            actor: $actor,
            event: 'comment.deleted',
            subject: $comment,
            metadata: [
                'task_id' => $comment->task_id,
            ],
        );
    }
}
