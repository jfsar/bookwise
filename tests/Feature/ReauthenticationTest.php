<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReauthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_store_requires_recent_password_confirmation(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/accounts')
            ->assertRedirect('/user/confirm-password');
    }

    public function test_account_store_passes_middleware_after_confirmation(): void
    {
        $this->actingAs(User::factory()->create());
        $this->confirmPassword();

        $response = $this->post('/accounts');

        $this->assertNotSame(url('/user/confirm-password'), $response->headers->get('Location'));
        $response->assertSessionHasErrors(['code', 'name', 'account_type_id', 'normal_balance']);
    }

    public function test_account_update_requires_recent_password_confirmation(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);
        $account = Account::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($user)
            ->put("/accounts/{$account->id}")
            ->assertRedirect('/user/confirm-password');
    }

    public function test_account_update_passes_middleware_after_confirmation(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);
        $account = Account::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($user);
        $this->confirmPassword();

        $response = $this->put("/accounts/{$account->id}");

        $this->assertNotSame(url('/user/confirm-password'), $response->headers->get('Location'));
        $response->assertSessionHasErrors(['code', 'name', 'account_type_id', 'normal_balance']);
    }

    public function test_fiscal_year_store_requires_recent_password_confirmation(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/fiscal-years')
            ->assertRedirect('/user/confirm-password');
    }

    public function test_fiscal_year_store_passes_middleware_after_confirmation(): void
    {
        $this->actingAs(User::factory()->create());
        $this->confirmPassword();

        $response = $this->post('/fiscal-years');

        $this->assertNotSame(url('/user/confirm-password'), $response->headers->get('Location'));
        $response->assertSessionHasErrors(['name', 'start_date', 'end_date']);
    }

    public function test_period_close_requires_recent_password_confirmation(): void
    {
        $period = AccountingPeriod::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post("/accounting-periods/{$period->id}/close")
            ->assertRedirect('/user/confirm-password');
    }

    public function test_period_reopen_requires_recent_password_confirmation(): void
    {
        $period = AccountingPeriod::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post("/accounting-periods/{$period->id}/reopen")
            ->assertRedirect('/user/confirm-password');
    }

    public function test_user_store_requires_recent_password_confirmation(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/settings/users')
            ->assertRedirect('/user/confirm-password');
    }

    public function test_user_update_requires_recent_password_confirmation(): void
    {
        $target = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put("/settings/users/{$target->id}")
            ->assertRedirect('/user/confirm-password');
    }

    public function test_role_store_requires_recent_password_confirmation(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/settings/roles')
            ->assertRedirect('/user/confirm-password');
    }

    public function test_role_update_requires_recent_password_confirmation(): void
    {
        $role = Role::create([
            'name' => 'Tester',
            'slug' => 'tester-'.uniqid(),
            'is_global' => false,
        ]);

        $this->actingAs(User::factory()->create())
            ->put("/settings/roles/{$role->id}")
            ->assertRedirect('/user/confirm-password');
    }

    public function test_role_destroy_requires_recent_password_confirmation(): void
    {
        $role = Role::create([
            'name' => 'Tester',
            'slug' => 'tester-'.uniqid(),
            'is_global' => false,
        ]);

        $this->actingAs(User::factory()->create())
            ->delete("/settings/roles/{$role->id}")
            ->assertRedirect('/user/confirm-password');
    }

    public function test_organization_update_requires_recent_password_confirmation(): void
    {
        $org = Organization::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put("/organizations/{$org->id}")
            ->assertRedirect('/user/confirm-password');
    }

    public function test_reports_export_requires_recent_password_confirmation(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/reports/export')
            ->assertRedirect('/user/confirm-password');
    }

    public function test_reports_download_requires_recent_password_confirmation(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $user->organizations()->attach($org->id, ['membership_status' => 'active']);

        $export = ReportExport::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'report_type' => 'trial-balance',
            'filters' => [],
            'request_hash' => str_repeat('c', 64),
            'status' => 'completed',
            'file_name' => 'trial-balance.csv',
            'file_path' => 'reports/3.csv',
            'file_size' => 5,
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/reports/exports/{$export->id}")
            ->assertRedirect('/user/confirm-password');
    }

    public function test_expired_password_confirmation_is_not_respected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time() - 11000])
            ->post('/accounts')
            ->assertRedirect('/user/confirm-password');
    }
}
