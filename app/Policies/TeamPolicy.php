<?php

namespace App\Policies;

use App\Enums\TeamMemberRole;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine whether the user can view the team (owner or member).
     */
    public function view(User $user, Team $team): bool
    {
        return Team::query()
            ->whereKey($team->getKey())
            ->accessibleByUser($user)
            ->exists();
    }

    /**
     * Whether the user can add, update, or remove members (team owner or admin member).
     */
    public function manageMembers(User $user, Team $team): bool
    {
        if ($team->owner_id === $user->id) {
            return true;
        }

        $membership = $team->membershipFor($user);

        return $membership !== null && $membership->role === TeamMemberRole::Admin;
    }
}
