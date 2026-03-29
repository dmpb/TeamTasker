<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\CommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommentController extends Controller
{
    public function __construct(protected CommentService $commentService) {}

    public function index(Team $team, Project $project, Task $task): Response
    {
        $this->abortUnlessTaskInProject($team, $project, $task);

        $this->authorize('view', $task);
        /** @var User $user */
        $user = auth()->user();

        $comments = $this->commentService->listTaskComments($task);

        return Inertia::render('teams/projects/tasks/comments', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
            ],
            'can' => [
                'createComments' => $user->can('create', [Comment::class, $task]),
            ],
            'comments' => $comments->map(fn (Comment $comment): array => [
                'id' => $comment->id,
                'body' => $comment->body,
                'created_at' => $comment->created_at?->toIso8601String(),
                'user' => [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                ],
                'can' => [
                    'update' => $user->can('update', $comment),
                    'delete' => $user->can('delete', $comment),
                ],
            ])->values()->all(),
        ]);
    }

    public function store(StoreCommentRequest $request, Team $team, Project $project, Task $task): RedirectResponse
    {
        $this->abortUnlessTaskInProject($team, $project, $task);

        /** @var array{ body: string } $validated */
        $validated = $request->validated();

        $this->commentService->createComment($task, $request->user(), $validated['body'], $request->user());

        return redirect()->route('teams.projects.tasks.comments.index', [$team, $project, $task]);
    }

    public function update(UpdateCommentRequest $request, Team $team, Project $project, Task $task, Comment $comment): RedirectResponse
    {
        $this->abortUnlessTaskInProject($team, $project, $task);

        /** @var array{ body: string } $validated */
        $validated = $request->validated();

        $this->commentService->updateComment($comment, $validated['body'], $request->user());

        return redirect()->route('teams.projects.tasks.comments.index', [$team, $project, $task]);
    }

    public function destroy(Request $request, Team $team, Project $project, Task $task, Comment $comment): RedirectResponse
    {
        $this->abortUnlessTaskInProject($team, $project, $task);

        abort_unless($comment->task_id === $task->id, 404);

        $this->authorize('delete', $comment);

        $this->commentService->deleteComment($comment, $request->user());

        return redirect()->route('teams.projects.tasks.comments.index', [$team, $project, $task]);
    }

    private function abortUnlessTaskInProject(Team $team, Project $project, Task $task): void
    {
        abort_unless($project->team_id === $team->id && $task->project_id === $project->id, 404);
    }
}
