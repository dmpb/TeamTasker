<?php

use App\Models\User;
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
