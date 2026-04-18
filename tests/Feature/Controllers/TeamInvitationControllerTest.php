<?php

use App\Mail\TeamInvitationMail;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\TeamInvitationReceivedNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('redirects guests from team invitation management routes', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $invitation = TeamInvitation::factory()->forTeam($team)->invitedBy($owner)->create();

    /** @var TestCase $this */
    $this->post(route('teams.invitations.store', $team), [
        'email' => 'x@example.com',
        'role' => 'member',
    ])->assertRedirect(route('login'));

    $this->delete(route('teams.invitations.destroy', [$team, $invitation]))
        ->assertRedirect(route('login'));

    $this->post(route('teams.invitations.resend', [$team, $invitation]))
        ->assertRedirect(route('login'));
});

it('allows owners to create invitations and queues mail', function () {
    Mail::fake();
    Notification::fake();

    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $team = Team::factory()->forOwner($owner)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.show', $team))
        ->post(route('teams.invitations.store', $team), [
            'email' => 'Invitee@Example.com',
            'role' => 'admin',
        ])
        ->assertRedirect(route('teams.show', $team));

    expect(TeamInvitation::query()->where('team_id', $team->id)->count())->toBe(1);

    Mail::assertQueued(TeamInvitationMail::class);

    Notification::assertSentTo($invitee, TeamInvitationReceivedNotification::class);
});

it('rejects invitations for emails without a registered account', function () {
    Mail::fake();
    Notification::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.show', $team))
        ->post(route('teams.invitations.store', $team), [
            'email' => 'no-user@example.com',
            'role' => 'member',
        ])
        ->assertRedirect(route('teams.show', $team))
        ->assertSessionHasErrors('email');

    expect(TeamInvitation::query()->where('team_id', $team->id)->count())->toBe(0);

    Mail::assertNothingOutgoing();
    Notification::assertNothingSent();
});

it('forbids plain members from creating invitations', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($member)->create();

    /** @var TestCase $this */
    $this->actingAs($member)
        ->from(route('teams.show', $team))
        ->post(route('teams.invitations.store', $team), [
            'email' => 'x@example.com',
            'role' => 'member',
        ])
        ->assertForbidden();

    Mail::assertNothingSent();
});

it('allows owners to cancel and resend invitations', function () {
    Mail::fake();
    Notification::fake();

    $owner = User::factory()->create();
    User::factory()->create(['email' => 'keep@example.com']);
    $team = Team::factory()->forOwner($owner)->create();
    $invitation = TeamInvitation::factory()
        ->forTeam($team)
        ->invitedBy($owner)
        ->create(['email' => 'keep@example.com']);

    $oldToken = $invitation->token;

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.show', $team))
        ->post(route('teams.invitations.resend', [$team, $invitation]))
        ->assertRedirect(route('teams.show', $team));

    expect($invitation->fresh()->token)->not->toBe($oldToken);

    Mail::assertQueued(TeamInvitationMail::class);

    $this->actingAs($owner)
        ->from(route('teams.show', $team))
        ->delete(route('teams.invitations.destroy', [$team, $invitation]))
        ->assertRedirect(route('teams.show', $team));

    expect($invitation->fresh()->cancelled_at)->not->toBeNull();
});

it('returns 404 when invitation id does not belong to the team route', function () {
    $owner = User::factory()->create();
    $teamA = Team::factory()->forOwner($owner)->create();
    $teamB = Team::factory()->forOwner($owner)->create();
    $invitationOnB = TeamInvitation::factory()->forTeam($teamB)->invitedBy($owner)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->delete(route('teams.invitations.destroy', [$teamA, $invitationOnB]))
        ->assertNotFound();
});
