<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function createSession(string $id, User $user, string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36'): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => $userAgent,
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    public function test_login_page_can_be_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
    }

    public function test_guest_is_redirected_to_login_for_protected_routes(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/settings/sessions')->assertRedirect('/login');
    }

    public function test_users_can_authenticate_with_correct_credentials(): void
    {
        $user = User::factory()->create(['email' => 'alice@example.com']);

        $this->post('/login', [
            'email' => 'alice@example.com',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_cannot_authenticate_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'bob@example.com']);

        $this->post('/login', [
            'email' => 'bob@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_users_cannot_authenticate(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'status' => 'inactive',
        ]);

        $this->post('/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registration_normalizes_email_to_lowercase_and_login_is_consistent(): void
    {
        $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'Jane.Doe@Example.COM',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $user = User::where('email', 'jane.doe@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('jane.doe@example.com', $user->email);

        $this->post('/logout');

        $this->post('/login', [
            'email' => 'jane.doe@example.com',
            'password' => 'password123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_remember_me_sets_a_persistent_login_cookie(): void
    {
        $user = User::factory()->create(['email' => 'carol@example.com']);

        $response = $this->post('/login', [
            'email' => 'carol@example.com',
            'password' => 'password',
            'remember' => true,
        ])->assertRedirect('/dashboard');

        $rememberCookies = collect($response->headers->getCookies())
            ->filter(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

        $this->assertTrue(
            $rememberCookies->isNotEmpty(),
            'Expected a persistent "remember me" cookie to be set on login.'
        );
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_unverified_users_are_redirected_to_email_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/email/verify');
    }

    public function test_password_reset_link_is_sent_for_known_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'dave@example.com']);

        $this->post('/forgot-password', ['email' => 'dave@example.com'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_users_can_reset_password_with_a_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'erin@example.com']);

        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'erin@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect('/login')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertFalse(Hash::check('password', $user->fresh()->password));

        $this->post('/login', [
            'email' => 'erin@example.com',
            'password' => 'new-password-123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_password_reset_invalidates_existing_sessions(): void
    {
        $user = User::factory()->create(['email' => 'frank@example.com']);
        $this->createSession('sess-reset-a', $user);
        $this->createSession('sess-reset-b', $user);

        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'frank@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect('/login');

        $this->assertDatabaseMissing('sessions', ['id' => 'sess-reset-a']);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-reset-b']);
    }

    public function test_sessions_page_lists_the_users_sessions(): void
    {
        $user = User::factory()->create();
        $this->createSession('sess-list-a', $user, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36');
        $this->createSession('sess-list-b', $user, 'Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0');

        $this->actingAs($user)
            ->get('/settings/sessions')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Sessions')
                ->has('sessions', 2));
    }

    public function test_current_session_cannot_be_signed_out(): void
    {
        $user = User::factory()->create();
        $currentSessionId = $this->app['session.store']->getId();

        $this->actingAs($user)
            ->delete('/settings/sessions/'.base64_encode($currentSessionId))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_user_can_sign_out_another_session(): void
    {
        $user = User::factory()->create();
        $this->createSession('sess-other', $user);

        $this->actingAs($user)
            ->delete('/settings/sessions/'.base64_encode('sess-other'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('sessions', ['id' => 'sess-other']);
    }

    public function test_user_cannot_sign_out_another_users_session(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->createSession('sess-b', $userB);

        $this->actingAs($userA)
            ->delete('/settings/sessions/'.base64_encode('sess-b'))
            ->assertRedirect();

        $this->assertDatabaseHas('sessions', ['id' => 'sess-b']);
    }

    public function test_sign_out_all_other_sessions_removes_only_own_sessions(): void
    {
        $user = User::factory()->create();
        $this->createSession('sess-a', $user);
        $this->createSession('sess-c', $user);

        $other = User::factory()->create();
        $this->createSession('sess-other-user', $other);

        $this->actingAs($user)
            ->delete('/settings/sessions')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('sessions', ['id' => 'sess-a']);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-c']);
        $this->assertDatabaseHas('sessions', ['id' => 'sess-other-user']);
    }
}
