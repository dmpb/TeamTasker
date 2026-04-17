<?php

namespace App\Services;

use App\Mail\TeamInvitationMail;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Repositories\TeamInvitationRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TeamInvitationService
{
    private const TOKEN_LENGTH = 48;

    public function __construct(
        protected TeamInvitationRepository $invitationRepository,
        protected TeamService $teamService,
    ) {}

    /**
     * @return Collection<int, TeamInvitation>
     */
    public function listOpenInvitationsForTeam(Team $team): Collection
    {
        return $this->invitationRepository->listOpenForTeam($team);
    }

    public function findInvitationByTokenForDisplay(string $token): ?TeamInvitation
    {
        return $this->invitationRepository->findByToken($token);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function createInvitation(Team $team, User $inviter, string $email, string $role, bool $sendMail = true): TeamInvitation
    {
        $normalizedEmail = $this->normalizeEmail($email);

        $existingMember = User::query()->where('email', $normalizedEmail)->first();

        if ($existingMember !== null && $this->teamService->getMembership($team, $existingMember) !== null) {
            throw new InvalidArgumentException(__('This user is already a member of the team.'));
        }

        if ($this->invitationRepository->findOpenPendingForTeamAndEmail($team, $normalizedEmail) !== null) {
            throw new InvalidArgumentException(__('An open invitation already exists for this email.'));
        }

        $invitation = new TeamInvitation([
            'team_id' => $team->id,
            'email' => $normalizedEmail,
            'role' => $role,
            'invited_by' => $inviter->id,
            'token' => $this->generateUniqueToken(),
            'expires_at' => now()->addDays(7),
        ]);

        $this->invitationRepository->save($invitation);
        $invitation->load(['team', 'inviter']);

        if ($sendMail) {
            $this->queueInvitationMail($invitation);
        }

        return $invitation;
    }

    public function cancelInvitation(Team $team, TeamInvitation $invitation): void
    {
        $this->assertInvitationBelongsToTeam($team, $invitation);

        if ($invitation->isAccepted()) {
            throw new InvalidArgumentException(__('This invitation was already accepted.'));
        }

        if ($invitation->isCancelled()) {
            return;
        }

        $invitation->cancelled_at = now();
        $this->invitationRepository->save($invitation);
    }

    public function resendInvitation(Team $team, TeamInvitation $invitation): TeamInvitation
    {
        $this->assertInvitationBelongsToTeam($team, $invitation);

        if ($invitation->isAccepted()) {
            throw new InvalidArgumentException(__('This invitation was already accepted.'));
        }

        if ($invitation->isCancelled()) {
            throw new InvalidArgumentException(__('This invitation was cancelled.'));
        }

        $invitation->token = $this->generateUniqueToken();
        $invitation->expires_at = now()->addDays(7);
        $this->invitationRepository->save($invitation);
        $invitation->load(['team', 'inviter']);

        $this->queueInvitationMail($invitation);

        return $invitation;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function acceptInvitationForUser(string $token, User $user): Team
    {
        $normalizedEmail = $this->normalizeEmail($user->email);

        return DB::transaction(function () use ($token, $user, $normalizedEmail): Team {
            /** @var TeamInvitation|null $invitation */
            $invitation = TeamInvitation::query()
                ->where('token', $token)
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw new InvalidArgumentException(__('This invitation link is not valid.'));
            }

            if ($invitation->isCancelled()) {
                throw new InvalidArgumentException(__('This invitation was cancelled.'));
            }

            if ($invitation->isAccepted()) {
                throw new InvalidArgumentException(__('This invitation was already accepted.'));
            }

            if ($invitation->isExpired()) {
                throw new InvalidArgumentException(__('This invitation has expired.'));
            }

            if ($this->normalizeEmail($invitation->email) !== $normalizedEmail) {
                throw new InvalidArgumentException(__('You must sign in with the invited email address to accept.'));
            }

            $team = $invitation->team;

            if ($this->teamService->getMembership($team, $user) !== null) {
                $invitation->accepted_at = now();
                $this->invitationRepository->save($invitation);

                return $team;
            }

            $this->teamService->addMemberToTeam($team, $user, $invitation->role);

            $invitation->accepted_at = now();
            $this->invitationRepository->save($invitation);

            return $team;
        });
    }

    private function assertInvitationBelongsToTeam(Team $team, TeamInvitation $invitation): void
    {
        if ((int) $invitation->team_id !== (int) $team->id) {
            throw new InvalidArgumentException(__('Invitation does not belong to this team.'));
        }
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function generateUniqueToken(): string
    {
        for ($i = 0; $i < 8; $i++) {
            $token = Str::random(self::TOKEN_LENGTH);
            if (TeamInvitation::query()->where('token', $token)->doesntExist()) {
                return $token;
            }
        }

        throw new InvalidArgumentException(__('Could not generate a unique invitation token.'));
    }

    private function queueInvitationMail(TeamInvitation $invitation): void
    {
        Mail::to($invitation->email)->queue(new TeamInvitationMail($invitation));
    }
}
