<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public ?User $actor,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $this->task->loadMissing(['project.team']);

        $team = $this->task->project->team;
        $project = $this->task->project;

        return [
            'kind' => 'task.assigned',
            'title' => __('Task assigned to you'),
            'body' => $this->task->title,
            'team_id' => $team->id,
            'project_id' => $project->id,
            'task_id' => $this->task->id,
            'url' => route('teams.projects.tasks.show', [$team, $project, $this->task]),
        ];
    }
}
