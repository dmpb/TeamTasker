<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamMemberRequest;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\TeamInvitationService;
use App\Services\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TeamController extends Controller
{
    public function __construct(
        protected TeamService $teamService,
        protected TeamInvitationService $teamInvitationService,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $perPage = max(1, min((int) $request->query('per_page', 12), 50));

        $teamsPaginator = $this->teamService
            ->paginateUserTeams($user, $perPage)
            ->withQueryString();

        $teamsPaginator->setCollection(
            $teamsPaginator->getCollection()->map(function (Team $team) use ($user): array {
                return [
                    'id' => $team->uuid,
                    'name' => $team->name,
                    'description' => $team->description,
                    'projects_count' => (int) $team->projects_count,
                    'members_count' => (int) $team->members_count,
                    'is_owner' => $team->owner_id === $user->id,
                ];
            }),
        );

        return Inertia::render('teams/index', [
            'teams' => $teamsPaginator,
        ]);
    }

    public function show(Request $request, Team $team): Response
    {
        $this->authorize('view', $team);

        $team->load(['members.user']);

        /** @var User $user */
        $user = $request->user();
        $canManageMembers = $user->can('manageMembers', $team);

        $memberSuggestions = [];

        if ($canManageMembers) {
            $memberSearchQuery = trim((string) $request->query('user_q', ''));

            if (strlen($memberSearchQuery) >= 2) {
                $memberSuggestions = $this->teamService->searchUsersNotInTeam($team, $memberSearchQuery, 12);
            }
        }

        $invitations = [];

        if ($canManageMembers) {
            $invitations = $this->teamInvitationService
                ->listOpenInvitationsForTeam($team)
                ->map(static function (TeamInvitation $invitation): array {
                    return [
                        'id' => $invitation->uuid,
                        'email' => $invitation->email,
                        'role' => $invitation->role,
                        'expires_at' => $invitation->expires_at->toIso8601String(),
                        'is_expired' => $invitation->isExpired(),
                        'invited_by_name' => $invitation->inviter->name,
                        'accept_url' => route('team-invitations.show', ['token' => $invitation->token], absolute: true),
                    ];
                })->values()->all();
        }

        return Inertia::render('teams/show', [
            'team' => [
                'id' => $team->uuid,
                'name' => $team->name,
                'description' => $team->description,
                'owner_id' => $team->owner_id,
            ],
            'members' => $team->members->map(function ($member) use ($canManageMembers, $team): array {
                $memberUserId = $member->user->id;

                return [
                    'id' => $member->uuid,
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
            'invitations' => $invitations,
            'memberSuggestions' => $memberSuggestions,
        ]);
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{name: string, description?: string|null} $validated */
        $validated = $request->validated();

        $team = $this->teamService->createTeam($user, $validated);

        return redirect()
            ->route('teams.show', $team)
            ->with('success', __('Team created successfully.'));
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
