<?php

use App\Enums\TeamMemberRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\TeamService;
use Tests\TestCase;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('renders the teams page with authenticated user teams', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $outsider = User::factory()->create();
    $excludedTeam = Team::factory()->forOwner($outsider)->create([
        'name' => 'Excluded',
    ]);

    $ownedTeam = Team::factory()->forOwner($user)->create([
        'name' => 'Owned',
    ]);

    $memberTeam = Team::factory()->forOwner($outsider)->create([
        'name' => 'Member Team',
    ]);

    TeamMember::factory()->forTeam($memberTeam)->forUser($user)->create();

    /** @var TestCase $this */
    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertInertia(function ($page) use ($ownedTeam, $memberTeam, $excludedTeam) {
            $page->component('teams/index')
                ->has('teams')
                ->where('teams', function ($teams) use ($ownedTeam, $memberTeam, $excludedTeam) {
                    $ids = collect($teams)->pluck('id')->all();

                    expect($ids)->toContain($ownedTeam->uuid, $memberTeam->uuid)
                        ->not->toContain($excludedTeam->uuid);

                    return true;
                });
        });
});

it('stores a new team for the authenticated user', function () {
    /** @var User $user */
    $user = User::factory()->create();

    /** @var TestCase $this */
    $this->actingAs($user)
        ->from(route('teams.index'))
        ->post(route('teams.store'), [
            'name' => 'New Team',
        ])
        ->assertRedirect(route('teams.index'));

    $team = Team::query()->where('name', 'New Team')->first();

    expect($team)->not->toBeNull();

    $membership = TeamMember::query()
        ->where('team_id', $team->id)
        ->where('user_id', $user->id)
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->role)->toBe(TeamMemberRole::Owner);
});

it('validates team name', function () {
    /** @var User $user */
    $user = User::factory()->create();

    /** @var TestCase $this */
    $this->actingAs($user)
        ->from(route('teams.index'))
        ->post(route('teams.store'), [
            'name' => '',
        ])
        ->assertRedirect(route('teams.index'))
        ->assertSessionHasErrors('name');
});

it('redirects guests from teams routes to login', function () {
    /** @var TestCase $this */
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $member = TeamMember::factory()->forTeam($team)->forUser($user)->create();

    $this->get(route('teams.index'))->assertRedirect(route('login'));
    $this->post(route('teams.store'), ['name' => 'X'])->assertRedirect(route('login'));
    $this->get(route('teams.show', $team))->assertRedirect(route('login'));
    $this->post(route('teams.members.store', $team), [
        'user_id' => $user->id,
        'role' => 'member',
    ])->assertRedirect(route('login'));
    $this->patch(route('teams.members.update', [$team, $member]), [
        'role' => 'admin',
    ])->assertRedirect(route('login'));
    $this->delete(route('teams.members.destroy', [$team, $member]))->assertRedirect(route('login'));

    $invitation = TeamInvitation::factory()->forTeam($team)->invitedBy($user)->create();

    $this->post(route('teams.invitations.store', $team), [
        'email' => 'inv@example.com',
        'role' => 'member',
    ])->assertRedirect(route('login'));

    $this->delete(route('teams.invitations.destroy', [$team, $invitation]))->assertRedirect(route('login'));

    $this->post(route('teams.invitations.resend', [$team, $invitation]))->assertRedirect(route('login'));
});

it('forbids viewing a team the user does not belong to', function () {
    $outsider = User::factory()->create();
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();

    /** @var TestCase $this */
    $this->actingAs($outsider)
        ->get(route('teams.show', $team))
        ->assertForbidden();
});

it('shows a team to members and owners with inertia', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create([
        'name' => 'Visible Team',
    ]);
    TeamMember::factory()->forTeam($team)->forUser($member)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->get(route('teams.show', $team))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/show')
            ->where('team.name', 'Visible Team')
            ->where('can.manageMembers', true)
            ->has('invitations')
            ->has('memberSuggestions'));

    $this->actingAs($member)
        ->get(route('teams.show', $team))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/show')
            ->where('can.manageMembers', false)
            ->has('invitations', 0)
            ->has('memberSuggestions', 0));
});

it('returns member suggestions for managers when user_q is set', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create([
        'name' => 'UniqueSearchNameXyz',
        'email' => 'unique-search-xyz@example.com',
    ]);
    $team = Team::factory()->forOwner($owner)->create();

    $url = route('teams.show', $team).'?'.http_build_query(['user_q' => 'UniqueSearch']);

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('memberSuggestions', 1)
            ->where('memberSuggestions.0.email', $outsider->email));
});

it('forbids plain members from managing team membership via http', function () {
    $owner = User::factory()->create();
    $plainMember = User::factory()->create();
    $candidate = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($plainMember)->create();

    /** @var TestCase $this */
    $this->actingAs($plainMember)
        ->from(route('teams.show', $team))
        ->post(route('teams.members.store', $team), [
            'user_id' => $candidate->id,
            'role' => 'member',
        ])
        ->assertForbidden();

    $this->actingAs($plainMember)
        ->from(route('teams.show', $team))
        ->patch(route('teams.members.update', [$team, $team->membershipFor($plainMember)]), [
            'role' => 'admin',
        ])
        ->assertForbidden();

    $this->actingAs($plainMember)
        ->from(route('teams.show', $team))
        ->delete(route('teams.members.destroy', [$team, $team->membershipFor($plainMember)]))
        ->assertForbidden();
});

it('allows admins to add members and remove non-owners', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $joining = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($admin)->admin()->create();

    /** @var TestCase $this */
    $this->actingAs($admin)
        ->from(route('teams.show', $team))
        ->post(route('teams.members.store', $team), [
            'user_id' => $joining->id,
            'role' => 'member',
        ])
        ->assertRedirect(route('teams.show', $team));

    expect(TeamMember::query()
        ->where('team_id', $team->id)
        ->where('user_id', $joining->id)
        ->where('role', TeamMemberRole::Member)
        ->exists())->toBeTrue();

    $this->actingAs($admin)
        ->from(route('teams.show', $team))
        ->delete(route('teams.members.destroy', [$team, $team->membershipFor($joining)]))
        ->assertRedirect(route('teams.show', $team));

    expect(TeamMember::query()
        ->where('team_id', $team->id)
        ->where('user_id', $joining->id)
        ->exists())->toBeFalse();
});

it('allows admins to update another member role', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $target = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($admin)->admin()->create();
    TeamMember::factory()->forTeam($team)->forUser($target)->create();

    /** @var TestCase $this */
    $this->actingAs($admin)
        ->from(route('teams.show', $team))
        ->patch(route('teams.members.update', [$team, $team->membershipFor($target)]), [
            'role' => 'admin',
        ])
        ->assertRedirect(route('teams.show', $team));

    expect(TeamMember::query()
        ->where('team_id', $team->id)
        ->where('user_id', $target->id)
        ->where('role', TeamMemberRole::Admin)
        ->exists())->toBeTrue();
});

it('rejects removing the team owner via member management', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($admin)->admin()->create();
    TeamMember::factory()->forTeam($team)->forUser($owner)->owner()->create();

    /** @var TestCase $this */
    $this->actingAs($admin)
        ->from(route('teams.show', $team))
        ->delete(route('teams.members.destroy', [$team, $team->membershipFor($owner)]))
        ->assertRedirect(route('teams.show', $team))
        ->assertSessionHasErrors('user');
});

it('rejects changing the team owner role via member management', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($admin)->admin()->create();
    TeamMember::factory()->forTeam($team)->forUser($owner)->owner()->create();

    /** @var TestCase $this */
    $this->actingAs($admin)
        ->from(route('teams.show', $team))
        ->patch(route('teams.members.update', [$team, $team->membershipFor($owner)]), [
            'role' => 'member',
        ])
        ->assertRedirect(route('teams.show', $team))
        ->assertSessionHasErrors('role');
});

it('validates add member fields', function () {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createTeam($owner, ['name' => 'Validated']);
    $admin = User::factory()->create();
    TeamMember::factory()->forTeam($team)->forUser($admin)->admin()->create();

    /** @var TestCase $this */
    $this->actingAs($admin)
        ->from(route('teams.show', $team))
        ->post(route('teams.members.store', $team), [])
        ->assertRedirect(route('teams.show', $team))
        ->assertSessionHasErrors(['user_id', 'role']);

    $this->actingAs($admin)
        ->from(route('teams.show', $team))
        ->post(route('teams.members.store', $team), [
            'user_id' => User::factory()->create()->id,
            'role' => 'owner',
        ])
        ->assertRedirect(route('teams.show', $team))
        ->assertSessionHasErrors('role');
});

it('exposes owner row without remove or role change on team show', function () {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createTeam($owner, ['name' => 'Owner Row']);

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->get(route('teams.show', $team))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/show')
            ->has('members', 1)
            ->where('members.0.can_remove', false)
            ->where('members.0.can_update_role', false));
});

it('returns 404 when trying to manage a member from another team', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $otherOwner = User::factory()->create();
    $otherUser = User::factory()->create();

    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($admin)->admin()->create();

    $otherTeam = Team::factory()->forOwner($otherOwner)->create();
    $otherMembership = TeamMember::factory()->forTeam($otherTeam)->forUser($otherUser)->create();

    /** @var TestCase $this */
    $this->actingAs($admin)
        ->patch(route('teams.members.update', [$team, $otherMembership]), [
            'role' => 'member',
        ])
        ->assertNotFound();
});
