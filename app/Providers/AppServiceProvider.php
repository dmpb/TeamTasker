<?php

namespace App\Providers;

use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\Team;
use App\Models\TeamInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Route::bind('invitation', function (string $value, RoutingRoute $route): TeamInvitation {
            $team = $route->parameter('team');
            $teamId = $team instanceof Team ? $team->id : (int) $team;

            return TeamInvitation::query()
                ->where('team_id', $teamId)
                ->whereKey((int) $value)
                ->firstOrFail();
        });

        Route::bind('label', function (string $value, RoutingRoute $route): Label {
            $project = $route->parameter('project');
            $projectId = $project instanceof Project ? $project->id : (int) $project;

            return Label::query()
                ->where('project_id', $projectId)
                ->whereKey((int) $value)
                ->firstOrFail();
        });

        Route::bind('checklistItem', function (string $value, RoutingRoute $route): TaskChecklistItem {
            $task = $route->parameter('task');
            $taskId = $task instanceof Task ? $task->id : (int) $task;

            return TaskChecklistItem::query()
                ->where('task_id', $taskId)
                ->whereKey((int) $value)
                ->firstOrFail();
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Model::preventLazyLoading(! app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
