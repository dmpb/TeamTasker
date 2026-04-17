<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ColumnController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Models\TeamMember;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::post('teams/{team}/members', [TeamController::class, 'storeMember'])->name('teams.members.store');

    Route::scopeBindings()->group(function () {
        Route::bind('member', static function (string $value, RoutingRoute $route): TeamMember {
            $teamId = (int) $route->parameter('team');

            /** @var TeamMember $member */
            $member = TeamMember::query()
                ->whereKey((int) $value)
                ->where('team_id', $teamId)
                ->firstOrFail();

            return $member;
        });

        Route::patch('teams/{team}/members/{member}', [TeamController::class, 'updateMember'])->name('teams.members.update');
        Route::delete('teams/{team}/members/{member}', [TeamController::class, 'destroyMember'])->name('teams.members.destroy');

        Route::get('teams/{team}/projects', [ProjectController::class, 'index'])->name('teams.projects.index');
        Route::post('teams/{team}/projects', [ProjectController::class, 'store'])->name('teams.projects.store');
        Route::patch('teams/{team}/projects/{project}', [ProjectController::class, 'update'])->name('teams.projects.update');
        Route::post('teams/{team}/projects/{project}/archive', [ProjectController::class, 'archive'])->name('teams.projects.archive');
        Route::post('teams/{team}/projects/{project}/unarchive', [ProjectController::class, 'unarchive'])->name('teams.projects.unarchive');
        Route::delete('teams/{team}/projects/{project}', [ProjectController::class, 'destroy'])->name('teams.projects.destroy');

        Route::get('teams/{team}/projects/{project}/board', [ColumnController::class, 'board'])->name('teams.projects.board');
        Route::get('teams/{team}/projects/{project}/activity', [ActivityLogController::class, 'index'])->name('teams.projects.activity.index');
        Route::post('teams/{team}/projects/{project}/columns/reorder', [ColumnController::class, 'reorder'])->name('teams.projects.columns.reorder');
        Route::post('teams/{team}/projects/{project}/columns/{column}/tasks', [TaskController::class, 'store'])->name('teams.projects.columns.tasks.store');
        Route::post('teams/{team}/projects/{project}/columns', [ColumnController::class, 'store'])->name('teams.projects.columns.store');
        Route::patch('teams/{team}/projects/{project}/columns/{column}', [ColumnController::class, 'update'])->name('teams.projects.columns.update');
        Route::delete('teams/{team}/projects/{project}/columns/{column}', [ColumnController::class, 'destroy'])->name('teams.projects.columns.destroy');
        Route::patch('teams/{team}/projects/{project}/tasks/{task}', [TaskController::class, 'update'])->name('teams.projects.tasks.update');
        Route::post('teams/{team}/projects/{project}/tasks/{task}/move', [TaskController::class, 'move'])->name('teams.projects.tasks.move');
        Route::delete('teams/{team}/projects/{project}/tasks/{task}', [TaskController::class, 'destroy'])->name('teams.projects.tasks.destroy');

        Route::get('teams/{team}/projects/{project}/tasks/{task}/comments', [CommentController::class, 'index'])->name('teams.projects.tasks.comments.index');
        Route::post('teams/{team}/projects/{project}/tasks/{task}/comments', [CommentController::class, 'store'])->name('teams.projects.tasks.comments.store');
        Route::patch('teams/{team}/projects/{project}/tasks/{task}/comments/{comment}', [CommentController::class, 'update'])->name('teams.projects.tasks.comments.update');
        Route::delete('teams/{team}/projects/{project}/tasks/{task}/comments/{comment}', [CommentController::class, 'destroy'])->name('teams.projects.tasks.comments.destroy');
    });
});

require __DIR__.'/settings.php';
