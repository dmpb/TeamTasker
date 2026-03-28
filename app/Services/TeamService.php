<?php

namespace App\Services;

use App\Enums\TeamMemberRole;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Repositories\TeamRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TeamService
{
    public function __construct(public TeamRepository $teamRepository) {}

    /**
     * Crea un equipo y agrega al owner como miembro con rol "owner".
     *
     * @param  array{ name: string }  $data
     */
    public function createTeam(User $owner, array $data): Team
    {
        return DB::transaction(function () use ($owner, $data): Team {
            $team = $this->teamRepository->createTeam($owner, $data['name']);

            $this->addUserToTeam($team, $owner, TeamMemberRole::Owner->value);

            return $team;
        });
    }

    public function getUserTeams(User $user): Collection
    {
        return $this->teamRepository->getTeamsByUser($user);
    }

    /**
     * Adds or updates a member with role admin or member (not owner; owner is set only at team creation).
     */
    public function addMemberToTeam(Team $team, User $user, string $role = 'member'): TeamMember
    {
        $this->assertManagerAssignableRole($role);

        return $this->teamRepository->attachUserToTeam($team, $user, $role);
    }

    /**
     * Updates a member's role to admin or member. The designated team owner cannot be reassigned via this method.
     */
    public function updateMemberRoleInTeam(Team $team, User $target, string $newRole): TeamMember
    {
        $this->assertManagerAssignableRole($newRole);

        if ($target->id === $team->owner_id) {
            throw new InvalidArgumentException('Cannot change the team owner role.');
        }

        $membership = $this->teamRepository->findMembership($team, $target);

        if ($membership === null) {
            throw new InvalidArgumentException('User is not a member of this team.');
        }

        return $this->teamRepository->attachUserToTeam($team, $target, $newRole);
    }

    /**
     * Removes a member from the team. The user referenced by {@see Team::$owner_id} cannot be removed.
     */
    public function removeMemberFromTeam(Team $team, User $target): void
    {
        if ($target->id === $team->owner_id) {
            throw new InvalidArgumentException('Cannot remove the team owner.');
        }

        $membership = $this->teamRepository->findMembership($team, $target);

        if ($membership === null) {
            throw new InvalidArgumentException('User is not a member of this team.');
        }

        $this->teamRepository->deleteMembership($membership);
    }

    /**
     * @internal Used when creating a team so the owner can receive the owner role.
     */
    public function addUserToTeam(Team $team, User $user, string $role = 'member'): TeamMember
    {
        $this->assertAllowedMemberRole($role);

        return $this->teamRepository->attachUserToTeam($team, $user, $role);
    }

    private function assertManagerAssignableRole(string $role): void
    {
        $allowed = [TeamMemberRole::Admin->value, TeamMemberRole::Member->value];

        if (! in_array($role, $allowed, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Role [%s] cannot be assigned via member management. Allowed: %s.',
                    $role,
                    implode(', ', $allowed),
                ),
            );
        }
    }

    private function assertAllowedMemberRole(string $role): void
    {
        if (TeamMemberRole::tryFrom($role) === null) {
            $allowed = implode(
                ', ',
                array_map(
                    static fn (TeamMemberRole $case): string => $case->value,
                    TeamMemberRole::cases(),
                ),
            );

            throw new InvalidArgumentException(
                sprintf(
                    'Invalid team member role [%s]. Allowed values: %s.',
                    $role,
                    $allowed,
                ),
            );
        }
    }
}
