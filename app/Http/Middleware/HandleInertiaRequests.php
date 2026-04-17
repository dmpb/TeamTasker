<?php

namespace App\Http\Middleware;

use App\Services\TeamService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
                'undo' => fn (): ?array => $request->session()->get('undo'),
            ],
            'name' => config('app.name'),
            'auth' => [
                'user' => static function () use ($request): ?array {
                    $user = $request->user();

                    if ($user === null) {
                        return null;
                    }

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ];
                },
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'teamsForNav' => static function () use ($request): array {
                if ($request->user() === null) {
                    return [];
                }

                return app(TeamService::class)
                    ->getUserTeams($request->user())
                    ->take(8)
                    ->map(static fn ($team): array => [
                        'id' => $team->id,
                        'name' => $team->name,
                    ])
                    ->values()
                    ->all();
            },
        ];
    }
}
