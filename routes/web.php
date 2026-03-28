<?php

use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::post('teams/{team}/members', [TeamController::class, 'storeMember'])->name('teams.members.store');
    Route::patch('teams/{team}/members/{user}', [TeamController::class, 'updateMember'])->name('teams.members.update');
    Route::delete('teams/{team}/members/{user}', [TeamController::class, 'destroyMember'])->name('teams.members.destroy');
});

require __DIR__.'/settings.php';
