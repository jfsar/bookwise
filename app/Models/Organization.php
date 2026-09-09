<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use App\Models\Concerns\HasUlidIdentifier;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property-read int $id */
/** @property-read string $ulid */
/** @property-read string $name */
/** @property-read string|null $display_name */
/** @property-read string $code */
/** @property-read string $country_code */
/** @property-read Currency $baseCurrency */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUlidIdentifier;

    protected $fillable = [
        'name',
        'display_name',
        'code',
        'registration_number',
        'tax_number',
        'email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country_code',
        'base_currency_id',
        'timezone',
        'fiscal_year_start_month',
        'date_format',
        'decimal_places',
        'status',
        'settings',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year_start_month' => 'integer',
            'decimal_places' => 'integer',
            'settings' => 'array',
            'status' => OrganizationStatus::class,
        ];
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->withPivot(['membership_status', 'joined_at', 'invited_at', 'accepted_at', 'revoked_at'])
            ->withTimestamps();
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function fiscalYears(): HasMany
    {
        return $this->hasMany(FiscalYear::class);
    }

    public function accountingPeriods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class);
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }
}
