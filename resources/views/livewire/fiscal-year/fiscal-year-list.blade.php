<div>
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Fiscal Years</h3>
        <livewire:fiscal-year.fiscal-year-form />
    </div>

    @if (session('success'))
        <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md text-green-700 dark:text-green-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="space-y-4">
        @forelse ($this->fiscalYears() as $fiscalYear)
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                {{ $fiscalYear->name }}
                            </h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $fiscalYear->start_date->format('M d, Y') }} - {{ $fiscalYear->end_date->format('M d, Y') }}
                            </p>
                        </div>
                        <div>
                            @if ($fiscalYear->status->value === 'open')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Open</span>
                            @elseif ($fiscalYear->status->value === 'closed')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Closed</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Archived</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4">
                    <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Accounting Periods</h5>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                        @foreach ($this->periods($fiscalYear) as $period)
                            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-3 {{ !$period->isOpen() ? 'opacity-50' : '' }}">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $period->name }}
                                    </span>
                                    @if ($period->isOpen())
                                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                    @else
                                        <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $period->start_date->format('M d') }} - {{ $period->end_date->format('M d') }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                    {{ ucfirst($period->status->value) }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-center">
                <p class="text-gray-500 dark:text-gray-400 text-sm">No fiscal years found. Create one to get started.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $this->fiscalYears()->links() }}
    </div>
</div>
