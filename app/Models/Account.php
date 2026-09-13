<?php

namespace App\Models;

use App\Enums\CashFlowSection;
use App\Enums\NormalBalance;
use App\Models\Concerns\HasUlidIdentifier;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read string $ulid
 * @property-read int $organization_id
 * @property-read int $account_type_id
 * @property-read int|null $parent_id
 * @property-read string $code
 * @property-read string $name
 * @property-read NormalBalance $normal_balance
 * @property-read bool $is_posting_account
 * @property-read bool $is_system_account
 * @property-read bool $is_active
 * @property-read bool $allow_manual_posting
 * @property-read Organization $organization
 * @property-read AccountType $accountType
 * @property-read Account|null $parent
 * @property-read Collection<Account> $children
 */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory, HasUlidIdentifier;

    protected $fillable = [
        'organization_id',
        'account_type_id',
        'parent_id',
        'code',
        'name',
        'description',
        'normal_balance',
        'account_category',
        'cash_flow_section',
        'is_cash_account',
        'is_posting_account',
        'is_system_account',
        'is_active',
        'allow_manual_posting',
        'currency_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'normal_balance' => NormalBalance::class,
            'cash_flow_section' => CashFlowSection::class,
            'is_cash_account' => 'boolean',
            'is_posting_account' => 'boolean',
            'is_system_account' => 'boolean',
            'is_active' => 'boolean',
            'allow_manual_posting' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePosting(Builder $query): Builder
    {
        return $query->where('is_posting_account', true);
    }

    public function scopeRootAccounts(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Check if this account can receive journal lines.
     */
    public function canReceiveJournalLines(): bool
    {
        return $this->is_posting_account && $this->is_active;
    }

    /**
     * Check if this account has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }
}
