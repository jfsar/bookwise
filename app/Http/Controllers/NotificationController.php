<?php

namespace App\Http\Controllers;

use App\Actions\Organization\SwitchOrganization;
use App\Models\Notification;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        $notifications = $organization
            ? $this->query($request)->paginate(20)->withQueryString()
                ->through(fn (Notification $notification) => $this->serialize($notification))
                ->toArray()
            : null;

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filters' => $request->only(['filter']),
        ]);
    }

    public function update(Request $request, Notification $notification): RedirectResponse
    {
        $user = $request->user();
        $organization = $this->resolveOrganization($request);

        $this->authorize('update', [$notification, $organization]);

        $validated = $request->validate([
            'read' => ['required', 'boolean'],
        ]);

        $validated['read']
            ? $notification->markAsRead()
            : $notification->markAsUnread();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        $organization = $this->resolveOrganization($request);

        $this->authorize('updateAny', [Notification::class, $organization]);

        $count = $this->query($request)->unread()->update(['read_at' => now()]);

        return back()->with('success', $count === 1 ? '1 notification marked as read.' : "{$count} notifications marked as read.");
    }

    private function query(Request $request): Builder
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        return Notification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->when($organization, fn ($query) => $query->where('organization_id', $organization->id))
            ->when($request->input('filter') === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function resolveOrganization(Request $request): Organization
    {
        $organization = SwitchOrganization::current($request->user());

        if (! $organization) {
            abort(403, 'No organization selected.');
        }

        return $organization;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => class_basename($notification->type),
            'message' => $notification->data['message'] ?? class_basename($notification->type),
            'data' => $notification->data,
            'is_read' => $notification->isRead(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
