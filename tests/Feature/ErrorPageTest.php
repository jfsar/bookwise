<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_inertia_404_renders_the_error_page_component(): void
    {
        $this->withHeader('X-Inertia', 'true')
            ->get('/this-route-does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('component', 'ErrorPage')
            ->assertJsonPath('props.status', 404);
    }

    public function test_guest_browser_404_renders_graceful_html_initial_load(): void
    {
        $response = $this->get('/this-route-does-not-exist')->assertNotFound();

        $page = $response->viewData('page');
        $page = is_string($page) ? json_decode($page, true) : $page;

        $this->assertSame('ErrorPage', $page['component']);
        $this->assertSame(404, $page['props']['status']);
    }

    public function test_authenticated_user_cannot_switch_to_a_foreign_organization_and_gets_error_page(): void
    {
        $user = User::factory()->create();
        $current = Organization::factory()->create();
        $foreign = Organization::factory()->create();
        $user->organizations()->attach($current->id, ['membership_status' => 'active']);

        $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->post("/organizations/switch/{$foreign->id}")
            ->assertForbidden()
            ->assertJsonPath('component', 'ErrorPage')
            ->assertJsonPath('props.status', 403);
    }
}
