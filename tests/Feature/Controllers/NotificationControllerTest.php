<?php

use App\Models\Team;
use App\Models\User;
use App\Services\TeamInvitationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('redirects guests from notifications', function () {
    /** @var TestCase $this */
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
});

it('shows notifications for authenticated users', function () {
    $user = User::factory()->create();

    /** @var TestCase $this */
    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('notifications/index')
            ->has('notifications'));
});

it('includes team invitation notifications for the invitee', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee-notify@example.com']);
    $team = Team::factory()->forOwner($owner)->create(['name' => 'Team Notify']);

    app(TeamInvitationService::class)->createInvitation(
        $team,
        $owner,
        'invitee-notify@example.com',
        'member',
        sendMail: false,
    );

    /** @var TestCase $this */
    $this->actingAs($invitee)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('notifications/index')
            ->has('notifications', 1)
            ->where('notifications.0.url', fn ($url) => is_string($url) && str_contains($url, '/invitations/')));
});
