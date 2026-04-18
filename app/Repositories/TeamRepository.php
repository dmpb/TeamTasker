<?php

namespace App\Repositories;

use App\Enums\TeamMemberRole;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class TeamRepository
{
    /**
     * @param  array{name: string, description?: string|null}  $attributes
     */
    public function createTeam(User $owner, array $attributes): Team
    {
        return Team::query()->create([
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'owner_id' => $owner->id,
        ]);
    }

    public function attachUserToTeam(Team $team, User $user, TeamMemberRole|string $role = TeamMemberRole::Member): TeamMember
    {
        $roleValue = $role instanceof TeamMemberRole ? $role->value : $role;

        return TeamMember::query()->updateOrCreate(
            [
                'team_id' => $team->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $roleValue,
            ],
        );
    }

    /**
     * @return Collection<int, TeamMember>
     */
    public function getMembersForTeam(Team $team): Collection
    {
        return TeamMember::query()
            ->where('team_id', $team->id)
            ->orderBy('id')
            ->get();
    }

    public function findMembership(Team $team, User $user): ?TeamMember
    {
        return TeamMember::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function deleteMembership(TeamMember $membership): void
    {
        $membership->delete();
    }

    public function getTeamsByUser(User $user): Collection
    {
        return Team::query()
            ->accessibleByUser($user)
            ->withCount(['projects', 'members'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return LengthAwarePaginator<int, Team>
     */
    public function paginateTeamsByUser(User $user, int $perPage): LengthAwarePaginator
    {
        return Team::query()
            ->accessibleByUser($user)
            ->withCount(['projects', 'members'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
