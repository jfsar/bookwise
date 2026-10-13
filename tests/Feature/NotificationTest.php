<?php

namespace Tests\Feature;

use App\Actions\JournalEntry\ApproveJournalEntry;
use App\Actions\JournalEntry\CreateJournalEntry;
use App\Actions\JournalEntry\PostJournalEntry;
use App\Actions\JournalEntry\SubmitJournalEntry;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Notification;
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

        $user->notify(new JournalEntryPosted($organization->id, 'je-123', 'JE-001'));

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'type' => JournalEntryPosted::class,
            'organization_id' => $organization->id,
        ]);

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertFalse($notification->isRead());
        $this->assertEquals('JE-001', $notification->data['entry_number']);
    }

    public function test_notification_channel_persists_organization_id(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['created_by' => $user->id]);
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);

        $user->notify(new JournalEntryPosted($organization->id, 'je-123', 'JE-001'));

        $this->assertSame(
            $organization->id,
            Notification::query()->sole()->organization_id
        );
    }

    public function test_notification_can_be_marked_read_unread(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['created_by' => $user->id]);
        $user->organizations()->attach($organization->id, ['membership_status' => 'active']);

        $user->notify(new JournalEntryPosted($organization->id, 'je-123', 'JE-001'));
        $notification = $user->notifications()->first();

        $notification->markAsRead();
        $this->assertTrue($notification->fresh()->isRead());

        $notification->markAsUnread();
        $this->assertFalse($notification->fresh()->isRead());
    }

    public function test_posting_an_entry_notifies_only_the_creator_once(): void
    {
        $organization = Organization::factory()->create();
        $creator = User::factory()->create();

        $this->postEntry($organization, $creator);

        $notifications = $creator->notifications()->where('type', JournalEntryPosted::class)->get();

        $this->assertCount(1, $notifications);
        $this->assertSame($organization->id, $notifications->sole()->organization_id);
    }

    public function test_posting_does_not_re_notify_creators_of_earlier_entries(): void
    {
        $organization = Organization::factory()->create();
        $firstCreator = User::factory()->create();
        $secondCreator = User::factory()->create();

        $this->postEntry($organization, $firstCreator);
        $this->postEntry($organization, $secondCreator);

        $this->assertSame(1, $firstCreator->notifications()->where('type', JournalEntryPosted::class)->count());
        $this->assertSame(1, $secondCreator->notifications()->where('type', JournalEntryPosted::class)->count());
    }

    private function postEntry(Organization $organization, User $creator): JournalEntry
    {
        $entry = $this->draftEntry($organization, $creator);

        (new SubmitJournalEntry)->handle($entry, $creator);

        $approver = User::factory()->create();
        (new ApproveJournalEntry)->handle($entry->fresh(), $approver);

        $poster = User::factory()->create();

        return (new PostJournalEntry)->handle($entry->fresh(), $poster);
    }

    private function draftEntry(Organization $organization, User $creator): JournalEntry
    {
        $journal = Journal::factory()->create(['organization_id' => $organization->id]);
        $currency = Currency::factory()->create();
        $fiscalYear = FiscalYear::factory()->create(['organization_id' => $organization->id]);
        $period = AccountingPeriod::factory()->create([
            'organization_id' => $organization->id,
            'fiscal_year_id' => $fiscalYear->id,
        ]);

        $debit = Account::factory()->create(['organization_id' => $organization->id]);
        $credit = Account::factory()->create(['organization_id' => $organization->id]);

        return (new CreateJournalEntry)->handle([
            'journal_id' => $journal->id,
            'currency_id' => $currency->id,
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Test entry',
            'lines' => [
                ['account_id' => $debit->id, 'debit' => 500, 'credit' => 0, 'description' => null],
                ['account_id' => $credit->id, 'debit' => 0, 'credit' => 500, 'description' => null],
            ],
        ], $organization, $creator);
    }
}
