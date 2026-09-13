<?php

namespace Tests\Feature;

use App\Actions\Organization\CreateOrganization;
use App\Actions\Organization\SwitchOrganization;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_organization(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();

        $organization = (new CreateOrganization)->handle([
            'name' => 'Test Org',
            'code' => 'TEST',
            'country_code' => 'US',
            'base_currency_id' => $currency->id,
        ], $user);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Test Org',
            'code' => 'TEST',
        ]);

        $this->assertTrue($user->organizations()->where('organization_id', $organization->id)->exists());
    }

    public function test_user_can_switch_organization(): void
    {
        $user = User::factory()->create();
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $user->organizations()->attach($org1->id, ['membership_status' => 'active']);
        $user->organizations()->attach($org2->id, ['membership_status' => 'active']);

        (new SwitchOrganization)->handle($user, $org2);

        $this->assertEquals($org2->id, session('current_organization_id'));
        $this->assertEquals($org2->id, SwitchOrganization::current($user)->id);
    }

    public function test_user_can_get_current_organization(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        (new SwitchOrganization)->handle($user, $org);

        $this->assertNotNull(SwitchOrganization::current($user));
        $this->assertEquals($org->id, SwitchOrganization::current($user)->id);
    }

    public function test_settings_update_forbids_user_without_permission(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $this->actingAs($user)
            ->put("/organizations/{$org->id}", ['name' => 'Renamed Org'])
            ->assertForbidden();
    }

    public function test_settings_update_allows_user_with_permission(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['created_by' => null]);
        $currency = Currency::factory()->create();

        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $role = Role::create([
            'name' => 'Settings Tester',
            'slug' => 'settings-tester-'.uniqid(),
            'is_global' => false,
        ]);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'manage-settings'])->id);
        $user->roles()->attach($role->id, ['organization_id' => $org->id]);

        $this->actingAs($user)
            ->put("/organizations/{$org->id}", [
                'name' => 'Renamed Org',
                'country_code' => 'US',
                'registration_number' => 'REG-123',
                'base_currency_id' => $currency->id,
                'timezone' => 'America/New_York',
                'fiscal_year_start_month' => 7,
                'decimal_places' => 4,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('organizations', [
            'id' => $org->id,
            'name' => 'Renamed Org',
            'registration_number' => 'REG-123',
            'base_currency_id' => $currency->id,
            'timezone' => 'America/New_York',
            'fiscal_year_start_month' => 7,
            'decimal_places' => 4,
        ]);
    }
}
