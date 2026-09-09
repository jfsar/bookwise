<?php

namespace Tests\Feature;

use App\Actions\Organization\CreateOrganization;
use App\Actions\Organization\SwitchOrganization;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_organization(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->create(['code' => 'USD']);

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
}
