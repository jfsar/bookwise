<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\JournalEntryPosted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_receive_database_notifications(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['created_by' => $user->id]);
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);

        $user->notify(new JournalEntryPosted('je-123', 'JE-001'));

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'type' => JournalEntryPosted::class,
        ]);

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertFalse($notification->isRead());
        $this->assertEquals('JE-001', $notification->data['entry_number']);
    }

    public function test_notification_can_be_marked_read_unread(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['created_by' => $user->id]);
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);

        $user->notify(new JournalEntryPosted('je-123', 'JE-001'));
        $notification = $user->notifications()->first();

        $notification->markAsRead();
        $this->assertTrue($notification->fresh()->isRead());

        $notification->markAsUnread();
        $this->assertFalse($notification->fresh()->isRead());
    }
}
