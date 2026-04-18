<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = $user->notifications()->latest()->limit(100)->get()->map(static function (DatabaseNotification $n): array {
            /** @var array<string, mixed> $data */
            $data = $n->data;

            return [
                'id' => (string) $n->id,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
                'title' => (string) ($data['title'] ?? ''),
                'body' => (string) ($data['body'] ?? ''),
                'url' => (string) ($data['url'] ?? ''),
            ];
        });

        return Inertia::render('notifications/index', [
            'notifications' => $rows->values()->all(),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $user->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->route('notifications.index')->with('success', __('Todas las notificaciones se marcaron como leídas.'));
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        /** @var DatabaseNotification|null $row */
        $row = $user->notifications()->whereKey($notification)->first();

        abort_if($row === null, 404);

        if ($row->read_at === null) {
            $row->markAsRead();
        }

        return redirect()->route('notifications.index')->with('success', __('Notificación marcada como leída.'));
    }
}
