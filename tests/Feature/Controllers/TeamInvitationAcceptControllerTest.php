<?php

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Tests\TestCase;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('shows invitation landing page for guests', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $invitation = TeamInvitation::factory()
        ->forTeam($team)
        ->invitedBy($owner)
        ->create(['email' => 'guest@example.com']);

    /** @var TestCase $this */
    $this->get(route('team-invitations.show', $invitation->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('team-invitations/show')
            ->where('invitation.state', 'open')
            ->where('invitation.email', 'guest@example.com'));
});

it('returns 404 for unknown token', function () {
    /** @var TestCase $this */
    $this->get(route('team-invitations.show', 'not-a-real-token-at-all-xxxxxxxxxx'))
        ->assertNotFound();
});

it('redirects guests from accept action', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $invitation = TeamInvitation::factory()
        ->forTeam($team)
        ->invitedBy($owner)
        ->create(['email' => 'who@example.com']);

    /** @var TestCase $this */
    $this->post(route('team-invitations.accept', $invitation->token))
        ->assertRedirect(route('login'));
});

it('accepts invitation for matching user', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'joiner@example.com']);
    $team = Team::factory()->forOwner($owner)->create();
    $invitation = TeamInvitation::factory()
        ->forTeam($team)
        ->invitedBy($owner)
        ->create(['email' => 'joiner@example.com']);

    /** @var TestCase $this */
    $this->actingAs($invitee)
        ->from(route('team-invitations.show', $invitation->token))
        ->post(route('team-invitations.accept', $invitation->token))
        ->assertRedirect(route('teams.show', $team));

    expect($team->membershipFor($invitee))->not->toBeNull();
});

it('rejects accept with validation flash when email mismatches', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'wrong@example.com']);
    $team = Team::factory()->forOwner($owner)->create();
    $invitation = TeamInvitation::factory()
        ->forTeam($team)
        ->invitedBy($owner)
        ->create(['email' => 'right@example.com']);

    /** @var TestCase $this */
    $this->actingAs($invitee)
        ->from(route('team-invitations.show', $invitation->token))
        ->post(route('team-invitations.accept', $invitation->token))
        ->assertRedirect(route('team-invitations.show', $invitation->token))
        ->assertSessionHasErrors('accept');
});
