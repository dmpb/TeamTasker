<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamInvitationRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Services\TeamInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TeamInvitationController extends Controller
{
    public function __construct(protected TeamInvitationService $invitationService) {}

    public function store(StoreTeamInvitationRequest $request, Team $team): RedirectResponse
    {
        /** @var array{ email: string, role: string } $validated */
        $validated = $request->validated();

        try {
            $this->invitationService->createInvitation(
                $team,
                $request->user(),
                $validated['email'],
                $validated['role'],
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        return back()->with('success', __('Invitation sent.'));
    }

    public function destroy(Request $request, Team $team, TeamInvitation $invitation): RedirectResponse
    {
        $this->authorize('delete', $invitation);

        try {
            $this->invitationService->cancelInvitation($team, $invitation);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invitation' => $e->getMessage()]);
        }

        return back()->with('success', __('Invitation cancelled.'));
    }

    public function resend(Request $request, Team $team, TeamInvitation $invitation): RedirectResponse
    {
        $this->authorize('resend', $invitation);

        try {
            $this->invitationService->resendInvitation($team, $invitation);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invitation' => $e->getMessage()]);
        }

        return back()->with('success', __('Invitation email resent.'));
    }
}
