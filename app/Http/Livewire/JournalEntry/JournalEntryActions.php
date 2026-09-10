<?php

namespace App\Http\Livewire\JournalEntry;

use App\Actions\JournalEntry\ApproveJournalEntry;
use App\Actions\JournalEntry\CancelJournalEntry;
use App\Actions\JournalEntry\PostJournalEntry;
use App\Actions\JournalEntry\ReverseJournalEntry;
use App\Actions\JournalEntry\SubmitJournalEntry;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class JournalEntryActions extends Component
{
    public JournalEntry $entry;

    public string $reversalReason = '';

    public string $cancelReason = '';

    public bool $showReverseModal = false;

    public bool $showCancelModal = false;

    public function submitEntry(SubmitJournalEntry $action): void
    {
        $this->authorize('submit', $this->entry);

        try {
            $action->handle($this->entry, Auth::user());
            $this->dispatch('entry-updated');
            session()->flash('success', 'Entry submitted for approval.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function approveEntry(ApproveJournalEntry $action): void
    {
        $this->authorize('approve', $this->entry);

        try {
            $action->handle($this->entry, Auth::user());
            $this->dispatch('entry-updated');
            session()->flash('success', 'Entry approved.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function postEntry(PostJournalEntry $action): void
    {
        $this->authorize('post', $this->entry);

        try {
            $action->handle($this->entry, Auth::user());
            $this->dispatch('entry-updated');
            session()->flash('success', 'Entry posted to ledger.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function reverseEntry(ReverseJournalEntry $action): void
    {
        $this->authorize('reverse', $this->entry);

        $this->validate(['reversalReason' => ['required', 'string', 'max:500']]);

        try {
            $reversal = $action->handle($this->entry, Auth::user(), $this->reversalReason);
            $this->showReverseModal = false;
            $this->reversalReason = '';
            $this->dispatch('entry-updated');
            session()->flash('success', "Entry reversed. Reversal entry: {$reversal->entry_number}");
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelEntry(CancelJournalEntry $action): void
    {
        $this->authorize('cancel', $this->entry);

        $this->validate(['cancelReason' => ['required', 'string', 'max:500']]);

        try {
            $action->handle($this->entry, Auth::user(), $this->cancelReason);
            $this->showCancelModal = false;
            $this->cancelReason = '';
            $this->dispatch('entry-updated');
            session()->flash('success', 'Entry cancelled.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.journal-entry.journal-entry-actions');
    }
}
