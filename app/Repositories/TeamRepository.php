<?php

namespace App\Repositories;

use App\Enums\TeamMemberRole;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TeamRepository
{
    public function createTeam(User $owner, string $name): Team
    {
        return Team::query()->create([
            'name' => $name,
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
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return list<array{id: int, name: string, email: string}>
     */
    public function searchUsersNotInTeam(Team $team, string $query, int $limit = 12): array
    {
        $trimmed = trim($query);

        if (strlen($trimmed) < 2) {
            return [];
        }

        $escaped = addcslashes($trimmed, '%_\\');

        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return User::query()
            ->where(function ($inner) use ($escaped, $likeOperator): void {
                $inner->where('email', $likeOperator, '%'.$escaped.'%')
                    ->orWhere('name', $likeOperator, '%'.$escaped.'%');
            })
            ->whereDoesntHave('teamMemberships', function ($members) use ($team): void {
                $members->where('team_id', $team->id);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->all();
    }
}
