<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\TaxRateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $organization_id
 * @property-read int $tax_code_id
 * @property-read string $rate
 * @property-read string $rate_type
 * @property-read Carbon $effective_from
 * @property-read Carbon|null $effective_to
 * @property-read TaxCode $taxCode
 */
class TaxRate extends Model
{
    /** @use HasFactory<TaxRateFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'tax_code_id',
        'rate',
        'rate_type',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }
}
