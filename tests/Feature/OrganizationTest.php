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

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/organizations/{$org->id}", ['name' => 'Renamed Org'])
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

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/organizations/{$org->id}", [
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

    public function test_settings_update_rejects_lowercase_country_code(): void
    {
        $org = Organization::factory()->create(['created_by' => null]);
        $currency = Currency::factory()->create();
        $user = $this->authorizedSettingsUser($org);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/organizations/{$org->id}", [
            'name' => 'Renamed Org',
            'country_code' => 'us',
            'base_currency_id' => $currency->id,
            'timezone' => 'America/New_York',
            'fiscal_year_start_month' => 1,
        ])->assertSessionHasErrors('country_code');
    }

    public function test_settings_update_rejects_invalid_timezone(): void
    {
        $org = Organization::factory()->create(['created_by' => null]);
        $currency = Currency::factory()->create();
        $user = $this->authorizedSettingsUser($org);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/organizations/{$org->id}", [
            'name' => 'Renamed Org',
            'country_code' => 'US',
            'base_currency_id' => $currency->id,
            'timezone' => 'Not/AZone',
            'fiscal_year_start_month' => 1,
        ])->assertSessionHasErrors('timezone');
    }

    public function test_settings_update_rejects_inactive_base_currency(): void
    {
        $org = Organization::factory()->create(['created_by' => null]);
        $currency = Currency::factory()->create(['is_active' => false]);
        $user = $this->authorizedSettingsUser($org);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/organizations/{$org->id}", [
            'name' => 'Renamed Org',
            'country_code' => 'US',
            'base_currency_id' => $currency->id,
            'timezone' => 'America/New_York',
            'fiscal_year_start_month' => 1,
        ])->assertSessionHasErrors('base_currency_id');
    }

    public function test_settings_update_defaults_date_format_and_decimal_places(): void
    {
        $org = Organization::factory()->create([
            'created_by' => null,
            'date_format' => 'd/m/Y',
            'decimal_places' => 4,
        ]);
        $currency = Currency::factory()->create();
        $user = $this->authorizedSettingsUser($org);

        $this->actingAs($user);
        $this->confirmPassword();

        $this->put("/organizations/{$org->id}", [
            'name' => 'Renamed Org',
            'country_code' => 'US',
            'base_currency_id' => $currency->id,
            'timezone' => 'America/New_York',
            'fiscal_year_start_month' => 1,
            'date_format' => null,
            'decimal_places' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('organizations', [
            'id' => $org->id,
            'date_format' => 'Y-m-d',
            'decimal_places' => 2,
        ]);
    }

    public function test_user_cannot_create_organization_with_lowercase_country_code(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create();

        $this->actingAs($user);

        $this->post('/organizations', [
            'name' => 'Test Org',
            'code' => 'TEST',
            'country_code' => 'us',
            'base_currency_id' => $currency->id,
            'timezone' => 'UTC',
            'fiscal_year_start_month' => 1,
        ])->assertSessionHasErrors('country_code');
    }

    private function authorizedSettingsUser(Organization $org): User
    {
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $role = Role::create([
            'name' => 'Settings Tester',
            'slug' => 'settings-tester-'.uniqid(),
            'is_global' => false,
        ]);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'manage-settings'])->id);
        $user->roles()->attach($role->id, ['organization_id' => $org->id]);

        return $user;
    }
}
