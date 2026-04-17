<?php

use App\Mail\TeamInvitationMail;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\TeamInvitationService;
use Illuminate\Support\Facades\Mail;

it('creates an invitation and queues mail', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();

    $service = app(TeamInvitationService::class);
    $invitation = $service->createInvitation($team, $owner, 'NewUser@Example.com', 'member');

    expect($invitation->email)->toBe('newuser@example.com')
        ->and(TeamInvitation::query()->count())->toBe(1);

    Mail::assertQueued(TeamInvitationMail::class, function (TeamInvitationMail $mail): bool {
        return $mail->invitation->email === 'newuser@example.com';
    });
});

it('rejects duplicate open invitations for the same email', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $service = app(TeamInvitationService::class);

    $service->createInvitation($team, $owner, 'dup@example.com', 'member', sendMail: false);

    $service->createInvitation($team, $owner, 'Dup@Example.com', 'admin', sendMail: false);
})->throws(InvalidArgumentException::class);

it('rejects invitation when user is already a member', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $member = User::factory()->create(['email' => 'member@example.com']);
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($member)->create();

    $service = app(TeamInvitationService::class);
    $service->createInvitation($team, $owner, 'member@example.com', 'member', sendMail: false);
})->throws(InvalidArgumentException::class);

it('accepts invitation and creates membership', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'join@example.com']);
    $team = Team::factory()->forOwner($owner)->create();

    $service = app(TeamInvitationService::class);
    $invitation = $service->createInvitation($team, $owner, 'join@example.com', 'admin', sendMail: false);

    $returnedTeam = $service->acceptInvitationForUser($invitation->token, $invitee);

    expect($returnedTeam->is($team))->toBeTrue()
        ->and($team->membershipFor($invitee))->not->toBeNull()
        ->and($team->membershipFor($invitee)->role->value)->toBe('admin')
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('rejects accept with wrong email user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create(['email' => 'other@example.com']);
    $team = Team::factory()->forOwner($owner)->create();

    $service = app(TeamInvitationService::class);
    $invitation = $service->createInvitation($team, $owner, 'target@example.com', 'member', sendMail: false);

    $service->acceptInvitationForUser($invitation->token, $other);
})->throws(InvalidArgumentException::class);

it('rejects expired invitation', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'late@example.com']);
    $team = Team::factory()->forOwner($owner)->create();

    $invitation = TeamInvitation::factory()
        ->forTeam($team)
        ->invitedBy($owner)
        ->expired()
        ->create(['email' => 'late@example.com']);

    $service = app(TeamInvitationService::class);
    $service->acceptInvitationForUser($invitation->token, $invitee);
})->throws(InvalidArgumentException::class);
