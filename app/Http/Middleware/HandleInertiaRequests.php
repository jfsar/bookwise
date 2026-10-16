<?php

namespace App\Http\Middleware;

use App\Actions\Organization\SwitchOrganization;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $currentOrganization = $user ? SwitchOrganization::current($user) : null;

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'status' => session('status'),
            ],
            'currentOrganization' => $currentOrganization ? [
                'id' => $currentOrganization->id,
                'name' => $currentOrganization->name,
                'display_name' => $currentOrganization->display_name,
                'code' => $currentOrganization->code,
            ] : null,
            'organizations' => $user
                ? $user->organizations()
                    ->wherePivot('membership_status', 'active')
                    ->with('baseCurrency')
                    ->orderBy('name')->get()
                    ->map(fn ($organization) => [
                        'id' => $organization->id,
                        'name' => $organization->name,
                        'display_name' => $organization->display_name,
                        'code' => $organization->code,
                        'country_code' => $organization->country_code,
                        'status' => $organization->status->value,
                        'base_currency' => $organization->baseCurrency ? ['code' => $organization->baseCurrency->code] : null,
                    ])->values()->all()
                : [],
            'unreadNotificationsCount' => $this->unreadNotificationsCount($user, $currentOrganization),
        ];
    }

    /**
     * The bell badge is scoped to the recipient *and* the active organization so
     * a notification from another tenant can never surface a count here.
     */
    private function unreadNotificationsCount(?User $user, ?Organization $organization): int
    {
        if (! $user || ! $organization) {
            return 0;
        }

        return Notification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->where('organization_id', $organization->id)
            ->whereNull('read_at')
            ->count();
    }
}
