<?php

namespace App\Models;

use Database\Factories\TaxCodeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read string $code
 * @property-read string $name
 * @property-read string $type
 * @property-read string $jurisdiction
 * @property-read int $tax_account_id
 * @property-read bool $is_active
 * @property-read Account $taxAccount
 */
class TaxCode extends Model
{
    /** @use HasFactory<TaxCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'type',
        'jurisdiction',
        'tax_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function taxAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'tax_account_id');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
