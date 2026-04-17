<?php

namespace App\Policies;

use App\Models\TeamInvitation;
use App\Models\User;

class TeamInvitationPolicy
{
    public function delete(User $user, TeamInvitation $invitation): bool
    {
        return $user->can('manageMembers', $invitation->team);
    }

    public function resend(User $user, TeamInvitation $invitation): bool
    {
        return $user->can('manageMembers', $invitation->team);
    }
}
