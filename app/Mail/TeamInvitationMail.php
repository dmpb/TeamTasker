<?php

namespace App\Mail;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public TeamInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You have been invited to join :team on TeamTasker', [
                'team' => $this->invitation->team->name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.team-invitation',
            with: [
                'teamName' => $this->invitation->team->name,
                'role' => $this->invitation->role,
                'inviterName' => $this->invitation->inviter->name,
                'acceptUrl' => route('team-invitations.show', ['token' => $this->invitation->token], absolute: true),
            ],
        );
    }
}
