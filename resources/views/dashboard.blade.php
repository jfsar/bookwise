<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Dashboard') }}
            </h2>
            <div class="flex items-center gap-4">
                <livewire:organization.organization-switcher />
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md text-green-700 dark:text-green-300 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
                <a href="{{ route('accounts.index') }}" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 hover:shadow-md transition">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Chart of Accounts</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Manage your accounts and hierarchy.</p>
                </a>

                <a href="{{ route('fiscal-years.index') }}" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 hover:shadow-md transition">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Fiscal Years</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Manage fiscal years and accounting periods.</p>
                </a>

                <a href="{{ route('journals.index') }}" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 hover:shadow-md transition">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Journals</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Manage accounting journals.</p>
                </a>

                <a href="{{ route('journal-entries.index') }}" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 hover:shadow-md transition">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Journal Entries</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Create and manage journal entries.</p>
                </a>

                <a href="{{ route('period-closing.index') }}" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 hover:shadow-md transition">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Period Closing</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Close accounting periods.</p>
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-medium">Organizations</h3>
                        <livewire:organization.create-organization-form />
                    </div>

                    @php
                        $organizations = Auth::user()->organizations()->wherePivot('membership_status', 'active')->get();
                    @endphp

                    @if ($organizations->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400 text-sm">
                            You don't belong to any organization yet. Create one to get started.
                        </p>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach ($organizations as $org)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 {{ app('session')->get('current_organization_id') === $org->id ? 'ring-2 ring-indigo-500' : '' }}">
                                    <h4 class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $org->display_name ?? $org->name }}
                                    </h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $org->code }} &middot; {{ $org->country_code }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $org->baseCurrency->code }} &middot; {{ ucfirst($org->status->value) }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
