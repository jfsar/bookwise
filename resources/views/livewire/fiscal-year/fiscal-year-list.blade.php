<div>
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-medium text-on-surface dark:text-on-surface font-display">Fiscal Years</h3>
        <livewire:fiscal-year.fiscal-year-form />
    </div>

    <x-alert type="success" class="mb-4" />

    <div class="space-y-4">
        @forelse ($this->fiscalYears as $fiscalYear)
            <x-card wire:key="fy-{{ $fiscalYear->id }}">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-lg font-medium text-on-surface dark:text-on-surface font-display">
                            {{ $fiscalYear->name }}
                        </h4>
                        <p class="text-sm text-on-surface-muted dark:text-on-surface-muted">
                            {{ $fiscalYear->start_date->format('M d, Y') }} - {{ $fiscalYear->end_date->format('M d, Y') }}
                        </p>
                    </div>
                    <div>
                        @php
                            $fyStatusColors = [
                                'open' => 'success',
                                'closed' => 'error',
                            ];
                        @endphp
                        <x-data-chip :color="$fyStatusColors[$fiscalYear->status->value] ?? 'default'">
                            {{ ucfirst($fiscalYear->status->value) }}
                        </x-data-chip>
                    </div>
                </div>

                <div class="mt-6">
                    <h5 class="text-sm font-medium text-on-surface-muted dark:text-on-surface-muted mb-3">Accounting Periods</h5>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                        @foreach ($this->periods($fiscalYear) as $period)
                            <div wire:key="period-{{ $period->id }}" class="bg-surface-low dark:bg-surface-low rounded-lg p-3 {{ !$period->isOpen() ? 'opacity-50' : '' }}">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-medium text-on-surface dark:text-on-surface">
                                        {{ $period->name }}
                                    </span>
                                    @if ($period->isOpen())
                                        <span class="w-2 h-2 bg-primary-light rounded-full"></span>
                                    @else
                                        <span class="w-2 h-2 bg-on-surface-muted/30 rounded-full"></span>
                                    @endif
                                </div>
                                <p class="text-xs text-on-surface-muted dark:text-on-surface-muted">
                                    {{ $period->start_date->format('M d') }} - {{ $period->end_date->format('M d') }}
                                </p>
                                <p class="text-xs text-on-surface-muted/60 dark:text-on-surface-muted/60 mt-1">
                                    {{ ucfirst($period->status->value) }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-card>
        @empty
            <x-card>
                <p class="text-on-surface-muted dark:text-on-surface-muted text-sm text-center">No fiscal years found. Create one to get started.</p>
            </x-card>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $this->fiscalYears->links() }}
    </div>
</div>
