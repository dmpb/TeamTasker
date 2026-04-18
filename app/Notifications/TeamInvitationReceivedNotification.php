<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TeamInvitationReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TeamInvitation $invitation,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $this->invitation->loadMissing(['team', 'inviter']);

        $team = $this->invitation->team;
        $inviter = $this->invitation->inviter;

        return [
            'kind' => 'team.invitation',
            'title' => __('Invitación a un team'),
            'body' => __(':inviter te invitó a unirte a :team como :role.', [
                'inviter' => $inviter->name,
                'team' => $team->name,
                'role' => $this->invitation->role,
            ]),
            'url' => route('team-invitations.show', ['token' => $this->invitation->token], absolute: true),
        ];
    }
}
