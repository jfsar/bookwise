<div>
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-medium text-on-surface dark:text-on-surface font-display">Chart of Accounts</h3>
        <livewire:accounts.account-form />
    </div>

    <x-alert type="success" class="mb-4" />

    <div class="mb-4 flex flex-wrap gap-3">
        <x-ghost-input
            wire:model.live.debounce.300ms="search"
            type="text"
            placeholder="Search accounts..."
            class="sm:text-sm"
        />

        <select
            wire:model.live="filterType"
            class="block rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20"
        >
            <option value="">All Types</option>
            @foreach ($this->accountTypes as $type)
                <option value="{{ $type->id }}">{{ $type->name }}</option>
            @endforeach
        </select>

        <select
            wire:model.live="filterStatus"
            class="block rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20"
        >
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </div>

    <x-card :padding="false">
        <table class="min-w-full">
            <thead class="bg-surface-low dark:bg-surface-low">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Balance</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Parent</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->accounts as $account)
                    <tr wire:key="account-{{ $account->id }}" class="{{ !$account->is_active ? 'opacity-50' : '' }}">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-on-surface dark:text-on-surface font-display">
                            @if ($account->parent_id)
                                <span class="text-on-surface-muted/40">&nbsp;&nbsp;</span>
                            @endif
                            {{ $account->code }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-on-surface-muted dark:text-on-surface-muted">
                            {{ $account->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-on-surface-muted dark:text-on-surface-muted">
                            {{ $account->accountType?->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-display">
                            <x-data-chip :color="$account->normal_balance->value === 'debit' ? 'info' : 'primary'">
                                {{ ucfirst($account->normal_balance->value) }}
                            </x-data-chip>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-on-surface-muted dark:text-on-surface-muted">
                            {{ $account->parent?->code }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if ($account->is_posting_account)
                                <x-data-chip color="success">Posting</x-data-chip>
                            @else
                                <x-data-chip color="default">Header</x-data-chip>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if ($account->is_active)
                                <x-data-chip color="success">Active</x-data-chip>
                            @else
                                <x-data-chip color="error">Inactive</x-data-chip>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <livewire:accounts.account-form :accountId="$account->id" wire:key="edit-{{ $account->id }}" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                            No accounts found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-6 py-3">
            {{ $this->accounts->links() }}
        </div>
    </x-card>
</div>
