<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\InvoiceOverdue;
use App\Notifications\JournalEntryPosted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function member(Organization $organization): User
    {
        $user = User::factory()->create();

        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/notifications')->assertRedirect('/login');
    }

    public function test_index_lists_only_the_recipients_own_notifications_in_the_active_organization(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->member($organization);
        $other = $this->member($organization);

        $user->notify(new JournalEntryPosted($organization->id, 'je-1', 'JE-001'));
        $other->notify(new JournalEntryPosted($organization->id, 'je-2', 'JE-002'));

        $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Notifications/Index')
                ->where('notifications.total', 1)
                ->where('notifications.data.0.data.entry_number', 'JE-001')
                ->where('notifications.data.0.is_read', false)
            );
    }

    public function test_index_hides_notifications_belonging_to_another_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $user = $this->member($organization);

        $user->notify(new JournalEntryPosted($otherOrganization->id, 'je-x', 'JE-999'));

        $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('notifications.total', 0)
            );
    }

    public function test_unread_count_shared_prop_excludes_other_organizations(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $user = $this->member($organization);

        $user->notify(new JournalEntryPosted($organization->id, 'je-1', 'JE-001'));
        $user->notify(new JournalEntryPosted($otherOrganization->id, 'je-2', 'JE-002'));

        $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('unreadNotificationsCount', 1)
            );
    }

    public function test_index_can_filter_to_unread_only(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->member($organization);

        $user->notify(new JournalEntryPosted($organization->id, 'je-1', 'JE-001'));
        $user->notify(new InvoiceOverdue($organization->id, 'inv-1', 'INV-001', now()->toDateString()));

        $read = $user->notifications()->first();
        $read->markAsRead();

        $this->actingAs($user)
            ->get('/notifications?filter=unread')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('notifications.total', 1)
                ->where('notifications.data.0.type', 'InvoiceOverdue')
            );
    }

    public function test_recipient_can_mark_a_notification_read_and_unread(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->member($organization);
        $user->notify(new JournalEntryPosted($organization->id, 'je-1', 'JE-001'));

        $notification = Notification::query()->sole();

        $this->actingAs($user)
            ->from('/notifications')
            ->patch("/notifications/{$notification->id}", ['read' => true])
            ->assertRedirect('/notifications');

        $this->assertTrue($notification->fresh()->isRead());

        $this->actingAs($user)
            ->from('/notifications')
            ->patch("/notifications/{$notification->id}", ['read' => false])
            ->assertRedirect('/notifications');

        $this->assertFalse($notification->fresh()->isRead());
    }

    public function test_marking_read_requires_a_boolean_read_value(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->member($organization);
        $user->notify(new JournalEntryPosted($organization->id, 'je-1', 'JE-001'));

        $notification = Notification::query()->sole();

        $this->actingAs($user)
            ->from('/notifications')
            ->patch("/notifications/{$notification->id}", [])
            ->assertSessionHasErrors('read');

        $this->assertFalse($notification->fresh()->isRead());
    }

    public function test_user_cannot_mark_another_users_notification_read(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->member($organization);
        $other = $this->member($organization);

        $other->notify(new JournalEntryPosted($organization->id, 'je-2', 'JE-002'));

        $notification = Notification::query()->sole();

        $this->actingAs($user)
            ->from('/notifications')
            ->patch("/notifications/{$notification->id}", ['read' => true])
            ->assertForbidden();

        $this->assertFalse($notification->fresh()->isRead());
    }

    public function test_user_cannot_touch_a_notification_from_another_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $user = $this->member($organization);
        $user->notify(new JournalEntryPosted($otherOrganization->id, 'je-2', 'JE-002'));

        $notification = Notification::query()->sole();

        $this->actingAs($user)
            ->from('/notifications')
            ->patch("/notifications/{$notification->id}", ['read' => true])
            ->assertForbidden();

        $this->assertFalse($notification->fresh()->isRead());
    }

    public function test_mark_all_read_only_touches_the_recipients_own_unread_rows(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $user = $this->member($organization);
        $other = $this->member($organization);

        $user->notify(new JournalEntryPosted($organization->id, 'je-1', 'JE-001'));
        $user->notify(new JournalEntryPosted($otherOrganization->id, 'je-2', 'JE-002'));
        $other->notify(new JournalEntryPosted($organization->id, 'je-3', 'JE-003'));

        $this->actingAs($user)
            ->from('/notifications')
            ->post('/notifications/read-all')
            ->assertRedirect('/notifications');

        $this->assertSame(1, Notification::query()->whereNotNull('read_at')->count());
        $this->assertSame(
            'je-1',
            Notification::query()->whereNotNull('read_at')->sole()->data['journal_entry_id']
        );
    }
}
