<?php

use App\Models\Team;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('shared inertia data includes teams for sidebar project shortcuts', function () {
    $user = User::factory()->create();
    $team = Team::factory()->forOwner($user)->create([
        'name' => 'Sidebar Team',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('teamsForNav', 1)
            ->where('teamsForNav.0.id', $team->id)
            ->where('teamsForNav.0.name', 'Sidebar Team'));
});

test('teams for nav is empty when the user has no teams', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('teamsForNav', []));
});
