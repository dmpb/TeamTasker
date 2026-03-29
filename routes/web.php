<?php

use App\Http\Controllers\ProjectController;
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

    Route::scopeBindings()->group(function () {
        Route::get('teams/{team}/projects', [ProjectController::class, 'index'])->name('teams.projects.index');
        Route::post('teams/{team}/projects', [ProjectController::class, 'store'])->name('teams.projects.store');
        Route::patch('teams/{team}/projects/{project}', [ProjectController::class, 'update'])->name('teams.projects.update');
        Route::post('teams/{team}/projects/{project}/archive', [ProjectController::class, 'archive'])->name('teams.projects.archive');
        Route::post('teams/{team}/projects/{project}/unarchive', [ProjectController::class, 'unarchive'])->name('teams.projects.unarchive');
        Route::delete('teams/{team}/projects/{project}', [ProjectController::class, 'destroy'])->name('teams.projects.destroy');
    });
});

require __DIR__.'/settings.php';
