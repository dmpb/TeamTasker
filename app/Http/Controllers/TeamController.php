<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamMemberRequest;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TeamController extends Controller
{
    public function __construct(protected TeamService $teamService) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $teams = $this->teamService->getUserTeams($user);

        return Inertia::render('teams/index', [
            'teams' => $teams,
        ]);
    }

    public function show(Request $request, Team $team): Response
    {
        $this->authorize('view', $team);

        $team->load(['members.user']);

        /** @var User $user */
        $user = $request->user();
        $canManageMembers = $user->can('manageMembers', $team);

        return Inertia::render('teams/show', [
            'team' => $team->only(['id', 'name', 'owner_id']),
            'members' => $team->members->map(function ($member) use ($canManageMembers, $team): array {
                $memberUserId = $member->user->id;

                return [
                    'id' => $member->id,
                    'role' => $member->role->value,
                    'user' => [
                        'id' => $memberUserId,
                        'name' => $member->user->name,
                        'email' => $member->user->email,
                    ],
                    'can_update_role' => $canManageMembers && $memberUserId !== $team->owner_id,
                    'can_remove' => $canManageMembers && $memberUserId !== $team->owner_id,
                ];
            })->values()->all(),
            'can' => [
                'manageMembers' => $canManageMembers,
            ],
        ]);
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{ name: string } $validated */
        $validated = $request->validated();

        $this->teamService->createTeam($user, $validated);

        return back()->with('success', __('Team created.'));
    }

    public function storeMember(StoreTeamMemberRequest $request, Team $team): RedirectResponse
    {
        /** @var array{ user_id: int, role: string } $validated */
        $validated = $request->validated();

        try {
            $newUser = User::query()->findOrFail($validated['user_id']);
            $this->teamService->addMemberToTeam($team, $newUser, $validated['role']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['user_id' => $e->getMessage()]);
        }

        return back()->with('success', __('Member added.'));
    }

    public function updateMember(UpdateTeamMemberRequest $request, Team $team, TeamMember $member): RedirectResponse
    {
        /** @var array{ role: string } $validated */
        $validated = $request->validated();

        try {
            $this->teamService->updateMemberRoleInTeam($team, $member->user, $validated['role']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['role' => $e->getMessage()]);
        }

        return back()->with('success', __('Role updated.'));
    }

    public function destroyMember(Request $request, Team $team, TeamMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $team);

        try {
            $this->teamService->removeMemberFromTeam($team, $member->user);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['user' => $e->getMessage()]);
        }

        return back()->with('success', __('Member removed.'));
    }
}
