<?php

use App\Enums\TeamMemberRole;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

it('defines team relationships', function () {
    $team = new Team;

    expect($team->owner())->toBeInstanceOf(BelongsTo::class)
        ->and($team->members())->toBeInstanceOf(HasMany::class);
});

it('defines team member relationships', function () {
    $teamMember = new TeamMember;

    expect($teamMember->team())->toBeInstanceOf(BelongsTo::class)
        ->and($teamMember->user())->toBeInstanceOf(BelongsTo::class);
});

it('defines user team relationships', function () {
    $user = new User;

    expect($user->ownedTeams())->toBeInstanceOf(HasMany::class)
        ->and($user->teamMemberships())->toBeInstanceOf(HasMany::class);
});

it('casts team member role to backed enum', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();

    $member = TeamMember::factory()->forTeam($team)->forUser($owner)->owner()->create();
    $member->refresh();

    expect($member->role)->toBe(TeamMemberRole::Owner);
});

it('scopes teams accessible by owner or membership', function () {
    $user = User::factory()->create();
    $outsider = User::factory()->create();

    $ownedTeam = Team::factory()->forOwner($user)->create();
    $memberTeam = Team::factory()->forOwner($outsider)->create();
    $excludedTeam = Team::factory()->forOwner($outsider)->create();

    TeamMember::factory()->forTeam($memberTeam)->forUser($user)->create();
    TeamMember::factory()->forTeam($excludedTeam)->forUser($outsider)->create();

    $ids = Team::query()->accessibleByUser($user)->pluck('id')->all();

    expect($ids)->toContain($ownedTeam->id, $memberTeam->id)
        ->not->toContain($excludedTeam->id);
});
