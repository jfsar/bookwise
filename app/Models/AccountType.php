<?php

namespace App\Models;

use App\Enums\AccountCategory;
use App\Enums\NormalBalance;
use Database\Factories\AccountTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property-read string $code
 * @property-read string $name
 * @property-read AccountCategory $category
 * @property-read NormalBalance $normal_balance
 * @property-read string|null $description
 * @property-read int $sort_order
 */
class AccountType extends Model
{
    /** @use HasFactory<AccountTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'category',
        'normal_balance',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category' => AccountCategory::class,
            'normal_balance' => NormalBalance::class,
            'sort_order' => 'integer',
        ];
    }
}
