<?php

namespace App\Models;

use App\Models\Concerns\HasUlidIdentifier;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
}
