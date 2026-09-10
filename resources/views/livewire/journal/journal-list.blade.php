<div>
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-medium text-on-surface dark:text-on-surface font-display">Journals</h3>
        <livewire:journal.journal-form />
    </div>

    <x-alert type="success" class="mb-4" />

    <div class="mb-4">
        <x-ghost-input
            wire:model.live.debounce.300ms="search"
            type="text"
            placeholder="Search journals..."
            class="sm:text-sm"
        />
    </div>

    <x-card :padding="false">
        <table class="min-w-full">
            <thead class="bg-surface-low dark:bg-surface-low">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Type</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->journals as $journal)
                    <tr wire:key="journal-{{ $journal->id }}">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-on-surface dark:text-on-surface font-display">
                            {{ $journal->code }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-on-surface-muted dark:text-on-surface-muted">
                            {{ $journal->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if ($journal->isActive())
                                <x-data-chip color="success">Active</x-data-chip>
                            @else
                                <x-data-chip color="default">Inactive</x-data-chip>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <x-data-chip color="info">{{ $journal->type }}</x-data-chip>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                            No journals found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">
        {{ $this->journals->links() }}
    </div>
</div>
