<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-on-surface dark:text-on-surface leading-tight font-display">
                {{ __('Dashboard') }}
            </h2>
            <div class="flex items-center gap-4">
                <livewire:organization.organization-switcher />
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-alert type="success" class="mb-4" />

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <a href="{{ route('accounts.index') }}" class="bg-surface-lowest dark:bg-surface-lowest overflow-hidden shadow-ambient-sm sm:rounded-xl p-6 hover:bg-surface-low dark:hover:bg-surface-low transition duration-150">
                    <h3 class="text-lg font-medium text-on-surface dark:text-on-surface font-display">Chart of Accounts</h3>
                    <p class="mt-2 text-sm text-on-surface-muted dark:text-on-surface-muted">Manage your accounts and hierarchy.</p>
                </a>

                <a href="{{ route('fiscal-years.index') }}" class="bg-surface-lowest dark:bg-surface-lowest overflow-hidden shadow-ambient-sm sm:rounded-xl p-6 hover:bg-surface-low dark:hover:bg-surface-low transition duration-150">
                    <h3 class="text-lg font-medium text-on-surface dark:text-on-surface font-display">Fiscal Years</h3>
                    <p class="mt-2 text-sm text-on-surface-muted dark:text-on-surface-muted">Manage fiscal years and accounting periods.</p>
                </a>

                <a href="{{ route('journals.index') }}" class="bg-surface-lowest dark:bg-surface-lowest overflow-hidden shadow-ambient-sm sm:rounded-xl p-6 hover:bg-surface-low dark:hover:bg-surface-low transition duration-150">
                    <h3 class="text-lg font-medium text-on-surface dark:text-on-surface font-display">Journals</h3>
                    <p class="mt-2 text-sm text-on-surface-muted dark:text-on-surface-muted">Manage accounting journals.</p>
                </a>

                <a href="{{ route('journal-entries.index') }}" class="bg-surface-lowest dark:bg-surface-lowest overflow-hidden shadow-ambient-sm sm:rounded-xl p-6 hover:bg-surface-low dark:hover:bg-surface-low transition duration-150">
                    <h3 class="text-lg font-medium text-on-surface dark:text-on-surface font-display">Journal Entries</h3>
                    <p class="mt-2 text-sm text-on-surface-muted dark:text-on-surface-muted">Create and manage journal entries.</p>
                </a>

                <a href="{{ route('period-closing.index') }}" class="bg-surface-lowest dark:bg-surface-lowest overflow-hidden shadow-ambient-sm sm:rounded-xl p-6 hover:bg-surface-low dark:hover:bg-surface-low transition duration-150">
                    <h3 class="text-lg font-medium text-on-surface dark:text-on-surface font-display">Period Closing</h3>
                    <p class="mt-2 text-sm text-on-surface-muted dark:text-on-surface-muted">Close accounting periods.</p>
                </a>
            </div>

            <x-card>
                <div class="text-on-surface dark:text-on-surface">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-medium font-display">Organizations</h3>
                        <livewire:organization.create-organization-form />
                    </div>

                    @php
                        $organizations = Auth::user()->organizations()->wherePivot('membership_status', 'active')->get();
                    @endphp

                    @if ($organizations->isEmpty())
                        <p class="text-on-surface-muted dark:text-on-surface-muted text-sm">
                            You don't belong to any organization yet. Create one to get started.
                        </p>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach ($organizations as $org)
                                <div wire:key="org-{{ $org->id }}" class="bg-surface-low dark:bg-surface-low rounded-lg p-4 {{ app('session')->get('current_organization_id') === $org->id ? 'ring-2 ring-primary dark:ring-primary' : '' }}">
                                    <h4 class="font-medium text-on-surface dark:text-on-surface">
                                        {{ $org->display_name ?? $org->name }}
                                    </h4>
                                    <p class="text-sm text-on-surface-muted dark:text-on-surface-muted mt-1">
                                        {{ $org->code }} &middot; {{ $org->country_code }}
                                    </p>
                                    <p class="text-sm text-on-surface-muted dark:text-on-surface-muted">
                                        {{ $org->baseCurrency->code }} &middot; {{ ucfirst($org->status->value) }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
