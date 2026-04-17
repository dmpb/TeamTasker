<?php

namespace App\Repositories;

use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Database\Eloquent\Collection;

class TeamInvitationRepository
{
    public function findByToken(string $token): ?TeamInvitation
    {
        return TeamInvitation::query()
            ->where('token', $token)
            ->with(['team', 'inviter'])
            ->first();
    }

    public function findForTeam(Team $team, int $invitationId): ?TeamInvitation
    {
        return TeamInvitation::query()
            ->where('team_id', $team->id)
            ->whereKey($invitationId)
            ->first();
    }

    /**
     * @return Collection<int, TeamInvitation>
     */
    public function listOpenForTeam(Team $team): Collection
    {
        return TeamInvitation::query()
            ->where('team_id', $team->id)
            ->whereNull('accepted_at')
            ->whereNull('cancelled_at')
            ->with('inviter')
            ->orderByDesc('created_at')
            ->get();
    }

    public function findOpenPendingForTeamAndEmail(Team $team, string $normalizedEmail): ?TeamInvitation
    {
        return TeamInvitation::query()
            ->where('team_id', $team->id)
            ->where('email', $normalizedEmail)
            ->whereNull('accepted_at')
            ->whereNull('cancelled_at')
            ->first();
    }

    public function save(TeamInvitation $invitation): void
    {
        $invitation->save();
    }
}
