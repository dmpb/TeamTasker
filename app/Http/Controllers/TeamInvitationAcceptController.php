<?php

namespace App\Http\Controllers;

use App\Services\TeamInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TeamInvitationAcceptController extends Controller
{
    public function __construct(protected TeamInvitationService $invitationService) {}

    public function show(Request $request, string $token): Response
    {
        $invitation = $this->invitationService->findInvitationByTokenForDisplay($token);

        if ($invitation === null) {
            abort(404);
        }

        $state = 'open';

        if ($invitation->isCancelled()) {
            $state = 'cancelled';
        } elseif ($invitation->isAccepted()) {
            $state = 'accepted';
        } elseif ($invitation->isExpired()) {
            $state = 'expired';
        }

        return Inertia::render('team-invitations/show', [
            'token' => $token,
            'invitation' => [
                'team_name' => $invitation->team->name,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'state' => $state,
            ],
            'authEmail' => $request->user()?->email,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        try {
            $team = $this->invitationService->acceptInvitationForUser($token, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['accept' => $e->getMessage()]);
        }

        return redirect()
            ->route('teams.show', $team)
            ->with('success', __('You have joined the team.'));
    }
}
