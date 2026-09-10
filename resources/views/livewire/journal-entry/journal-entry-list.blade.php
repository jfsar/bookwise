<div>
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-medium text-on-surface dark:text-on-surface font-display">Journal Entries</h3>
        <livewire:journal-entry.journal-entry-form />
    </div>

    <x-alert type="success" class="mb-4" />

    <div class="flex gap-4 mb-4">
        <x-ghost-input
            wire:model.live.debounce.300ms="search"
            type="text"
            placeholder="Search entries..."
            class="sm:text-sm"
        />
        <select wire:model.live="status"
            class="block w-48 rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="submitted">Submitted</option>
            <option value="approved">Approved</option>
            <option value="posted">Posted</option>
            <option value="reversed">Reversed</option>
        </select>
    </div>

    <x-card :padding="false">
        <table class="min-w-full">
            <thead class="bg-surface-low dark:bg-surface-low">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Entry #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Journal</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Period</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->entries as $entry)
                    <tr wire:key="entry-{{ $entry->id }}">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-primary dark:text-primary font-display">
                            {{ $entry->entry_number }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-on-surface-muted dark:text-on-surface-muted">
                            {{ $entry->entry_date->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-on-surface-muted dark:text-on-surface-muted">
                            {{ $entry->journal->name }}
                        </td>
                        <td class="px-6 py-4 text-sm text-on-surface-muted dark:text-on-surface-muted max-w-xs truncate">
                            {{ $entry->description ?? '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $statusColors = [
                                    'draft' => 'default',
                                    'submitted' => 'warning',
                                    'approved' => 'info',
                                    'posted' => 'success',
                                    'reversed' => 'error',
                                    'cancelled' => 'default',
                                    'rejected' => 'error',
                                ];
                            @endphp
                            <x-data-chip :color="$statusColors[$entry->status->value] ?? 'default'">
                                {{ ucfirst($entry->status->value) }}
                            </x-data-chip>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-on-surface-muted dark:text-on-surface-muted">
                            {{ $entry->accountingPeriod->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <livewire:journal-entry.journal-entry-actions :entry="$entry" :key="'actions-'.$entry->id" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                            No journal entries found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">
        {{ $this->entries->links() }}
    </div>
</div>
