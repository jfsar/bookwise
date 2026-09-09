<?php

namespace Tests\Feature;

use App\Enums\AccountCategory;
use App\Enums\NormalBalance;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_has_hierarchy(): void
    {
        $org = Organization::factory()->create();
        $type = AccountType::factory()->create();
        $parent = Account::factory()->create([
            'organization_id' => $org->id,
            'account_type_id' => $type->id,
        ]);
        $child = Account::factory()->create([
            'organization_id' => $org->id,
            'account_type_id' => $type->id,
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals($parent->id, $child->parent_id);
        $this->assertTrue($parent->children()->where('id', $child->id)->exists());
    }

    public function test_account_normal_balance_category_consistency(): void
    {
        $org = Organization::factory()->create();
        $type = AccountType::factory()->create([
            'category' => AccountCategory::Asset,
        ]);
        $account = Account::factory()->create([
            'organization_id' => $org->id,
            'account_type_id' => $type->id,
            'normal_balance' => NormalBalance::Debit,
        ]);

        $this->assertEquals(AccountCategory::Asset, $account->accountType->category);
        $this->assertEquals(NormalBalance::Debit, $account->normal_balance);
    }

    public function test_account_scoped_to_organization(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();
        $type = AccountType::factory()->create();
        $account1 = Account::factory()->create(['organization_id' => $org1->id, 'account_type_id' => $type->id]);
        $account2 = Account::factory()->create(['organization_id' => $org2->id, 'account_type_id' => $type->id]);

        $this->assertEquals(1, Account::forOrganization($org1)->count());
        $this->assertTrue(Account::forOrganization($org1)->where('id', $account1->id)->exists());
        $this->assertFalse(Account::forOrganization($org1)->where('id', $account2->id)->exists());
    }
}
