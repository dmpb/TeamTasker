<?php

use App\Enums\TeamMemberRole;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Repositories\TeamRepository;

it('creates a team for an owner', function () {
    $owner = User::factory()->create();
    $repository = app(TeamRepository::class);

    $team = $repository->createTeam($owner, 'Core Team');

    expect($team)->toBeInstanceOf(Team::class)
        ->and($team->name)->toBe('Core Team')
        ->and($team->owner_id)->toBe($owner->id);
});

it('attaches users to a team and updates role for existing membership', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $repository = app(TeamRepository::class);
    $team = Team::factory()->forOwner($owner)->create([
        'name' => 'Product Team',
    ]);

    $membership = $repository->attachUserToTeam($team, $user);
    $updatedMembership = $repository->attachUserToTeam($team, $user, 'admin');

    expect($membership)->toBeInstanceOf(TeamMember::class)
        ->and($membership->role)->toBe(TeamMemberRole::Member)
        ->and($updatedMembership->id)->toBe($membership->id)
        ->and($updatedMembership->role)->toBe(TeamMemberRole::Admin);
});

it('gets teams by user ownership or membership', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $repository = app(TeamRepository::class);

    $ownedTeam = Team::factory()->forOwner($owner)->create([
        'name' => 'Owned Team',
    ]);

    $memberTeam = Team::factory()->forOwner($outsider)->create([
        'name' => 'Member Team',
    ]);

    $unrelatedTeam = Team::factory()->forOwner($outsider)->create([
        'name' => 'Unrelated Team',
    ]);

    TeamMember::factory()->forTeam($memberTeam)->forUser($owner)->create();

    TeamMember::factory()->forTeam($unrelatedTeam)->forUser($outsider)->create();

    $teams = $repository->getTeamsByUser($owner);

    expect($teams->pluck('id')->all())->toContain($ownedTeam->id, $memberTeam->id)
        ->and($teams->pluck('id')->all())->not->toContain($unrelatedTeam->id);
});

it('lists members for a team ordered by id', function () {
    $owner = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $repository = app(TeamRepository::class);
    $team = Team::factory()->forOwner($owner)->create();

    $first = TeamMember::factory()->forTeam($team)->forUser($alice)->create();
    $second = TeamMember::factory()->forTeam($team)->forUser($bob)->create();

    $members = $repository->getMembersForTeam($team);

    expect($members)->toHaveCount(2)
        ->and($members->first()->is($first))->toBeTrue()
        ->and($members->last()->is($second))->toBeTrue();
});

it('deletes a membership row', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $repository = app(TeamRepository::class);
    $team = Team::factory()->forOwner($owner)->create();
    $row = TeamMember::factory()->forTeam($team)->forUser($member)->create();

    $repository->deleteMembership($row);

    expect(TeamMember::query()->whereKey($row->id)->exists())->toBeFalse();
});

it('finds membership by team and user', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $repository = app(TeamRepository::class);
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($member)->admin()->create();

    $found = $repository->findMembership($team, $member);
    $missing = $repository->findMembership($team, User::factory()->create());

    expect($found)->not->toBeNull()
        ->and($found->role)->toBe(TeamMemberRole::Admin)
        ->and($missing)->toBeNull();
});
