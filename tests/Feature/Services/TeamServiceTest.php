<?php

use App\Enums\TeamMemberRole;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\TeamService;

it('creates a team with owner membership', function () {
    $owner = User::factory()->create();
    $service = app(TeamService::class);

    $team = $service->createTeam($owner, ['name' => 'Engineering']);

    expect($team)->toBeInstanceOf(Team::class)
        ->and($team->owner_id)->toBe($owner->id)
        ->and($team->name)->toBe('Engineering');

    $ownerMembership = TeamMember::query()
        ->where('team_id', $team->id)
        ->where('user_id', $owner->id)
        ->first();

    expect($ownerMembership)->not->toBeNull()
        ->and($ownerMembership?->role)->toBe(TeamMemberRole::Owner);
});

it('gets the teams where the user is owner or member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $service = app(TeamService::class);

    $ownedTeam = Team::factory()->forOwner($owner)->create([
        'name' => 'Owned Team',
    ]);

    $memberTeam = Team::factory()->forOwner($outsider)->create([
        'name' => 'Member Team',
    ]);

    $otherTeam = Team::factory()->forOwner($outsider)->create([
        'name' => 'Other Team',
    ]);

    TeamMember::factory()->forTeam($memberTeam)->forUser($owner)->create();

    TeamMember::factory()->forTeam($otherTeam)->forUser($member)->create();

    $teams = $service->getUserTeams($owner);

    expect($teams->pluck('id')->all())->toContain($ownedTeam->id, $memberTeam->id)
        ->and($teams->pluck('id')->all())->not->toContain($otherTeam->id);
});

it('adds a user to a team and updates existing membership role', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $service = app(TeamService::class);
    $team = Team::factory()->forOwner($owner)->create([
        'name' => 'Platform Team',
    ]);

    $membership = $service->addUserToTeam($team, $user);

    expect($membership)->toBeInstanceOf(TeamMember::class)
        ->and($membership->role)->toBe(TeamMemberRole::Member);

    $updatedMembership = $service->addUserToTeam($team, $user, 'admin');

    expect($updatedMembership->id)->toBe($membership->id)
        ->and($updatedMembership->role)->toBe(TeamMemberRole::Admin);
});

it('rejects invalid member roles when adding a user to a team', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $service = app(TeamService::class);
    $team = Team::factory()->forOwner($owner)->create();

    $service->addUserToTeam($team, $user, 'invalid-role');
})->throws(InvalidArgumentException::class);

it('rejects owner role when adding a member via management API', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $service = app(TeamService::class);
    $team = Team::factory()->forOwner($owner)->create();

    $service->addMemberToTeam($team, $user, TeamMemberRole::Owner->value);
})->throws(InvalidArgumentException::class);

it('removes a member and blocks removing the designated owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $service = app(TeamService::class);
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($owner)->owner()->create();
    TeamMember::factory()->forTeam($team)->forUser($member)->create();

    $service->removeMemberFromTeam($team, $member);

    expect(TeamMember::query()
        ->where('team_id', $team->id)
        ->where('user_id', $member->id)
        ->exists())->toBeFalse();

    $service->removeMemberFromTeam($team, $owner);
})->throws(InvalidArgumentException::class);

it('blocks changing role for the team owner via management rules', function () {
    $owner = User::factory()->create();
    $service = app(TeamService::class);
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($owner)->owner()->create();

    $service->updateMemberRoleInTeam($team, $owner, TeamMemberRole::Member->value);
})->throws(InvalidArgumentException::class);
