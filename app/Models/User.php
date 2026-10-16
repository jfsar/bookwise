<?php

namespace App\Models;

use App\Models\Concerns\HasUlidIdentifier;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlidIdentifier, Notifiable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->withPivot(['membership_status', 'joined_at', 'invited_at', 'accepted_at', 'revoked_at'])
            ->withTimestamps();
    }

    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'created_by');
    }

    /**
     * Overrides the Notifiable trait's relation so notifications are hydrated as
     * App\Models\Notification (organization-scoped) instead of Laravel's
     * DatabaseNotification.
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
    }

    /**
     * @return MorphMany<Notification, $this>
     */
    public function unreadNotifications(): MorphMany
    {
        return $this->notifications()->whereNull('read_at');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot(['organization_id'])
            ->withTimestamps();
    }

    /**
     * Check if the user belongs to the given organization.
     */
    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->organizations()
            ->where('organizations.id', $organization->id)
            ->wherePivot('membership_status', 'active')
            ->exists();
    }

    /**
     * Check if the user has the given permission within the given organization.
     */
    public function hasPermissionTo(string $permissionSlug, Organization $organization): bool
    {
        return $this->roles()
            ->where('role_user.organization_id', $organization->id)
            ->whereHas('permissions', fn ($q) => $q->where('name', $permissionSlug))
            ->exists();
    }

    /**
     * Check if the user has the given role within the given organization.
     */
    public function hasRole(string $roleSlug, Organization $organization): bool
    {
        return $this->roles()
            ->where('role_user.organization_id', $organization->id)
            ->where('slug', $roleSlug)
            ->exists();
    }

    /**
     * Scope to active members of the organization.
     *
     * Inside whereHas() the pivot is joined as organization_user, so the
     * membership status has to be constrained on the joined table rather than
     * through wherePivot().
     */
    public function scopeActiveMembersOf(Builder $query, Organization $organization): Builder
    {
        return $query->whereHas('organizations', fn (Builder $query) => $query
            ->where('organizations.id', $organization->id)
            ->where('organization_user.membership_status', 'active'));
    }

    /**
     * Active members of the organization who hold the given permission.
     */
    public function scopeWithOrganizationPermission(Builder $query, Organization $organization, string $permissionSlug): Builder
    {
        return $query
            ->activeMembersOf($organization)
            ->whereHas('roles', fn (Builder $query) => $query
                ->where('role_user.organization_id', $organization->id)
                ->whereHas('permissions', fn (Builder $query) => $query->where('name', $permissionSlug)));
    }

    /**
     * Active members of the organization who hold the given permission.
     *
     * @return Collection<int, User>
     */
    public static function membersWithPermission(Organization $organization, string $permissionSlug): Collection
    {
        return static::query()
            ->withOrganizationPermission($organization, $permissionSlug)
            ->orderBy('name')
            ->get();
    }
}
