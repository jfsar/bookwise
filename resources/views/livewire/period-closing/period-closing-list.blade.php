<div>
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-medium text-on-surface dark:text-on-surface font-display">Period Closing</h3>
    </div>

    <x-alert type="success" class="mb-4" />
    <x-alert type="error" class="mb-4" />

    <div class="mb-4">
        <label for="fiscalYear" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted mb-1">Filter by Fiscal Year</label>
        <select wire:model.live="selectedFiscalYearId" id="fiscalYear"
            class="block w-64 rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20">
            <option value="">All Fiscal Years</option>
            @foreach ($this->fiscalYears as $fy)
                <option value="{{ $fy->id }}">{{ $fy->name }}</option>
            @endforeach
        </select>
    </div>

    <x-card :padding="false">
        <table class="min-w-full">
            <thead class="bg-surface-low dark:bg-surface-low">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Period</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Fiscal Year</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Dates</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-on-surface-muted dark:text-on-surface-muted uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->periods as $period)
                    <tr wire:key="period-{{ $period->id }}">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-on-surface dark:text-on-surface font-display">
                            {{ $period->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-on-surface-muted dark:text-on-surface-muted">
                            {{ $period->fiscalYear->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-on-surface-muted dark:text-on-surface-muted">
                            {{ $period->start_date->format('M d') }} - {{ $period->end_date->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $periodStatusColors = [
                                    'open' => 'success',
                                    'closing' => 'warning',
                                    'closed' => 'error',
                                ];
                            @endphp
                            <x-data-chip :color="$periodStatusColors[$period->status->value] ?? 'default'">
                                {{ ucfirst($period->status->value) }}
                            </x-data-chip>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <livewire:period-closing.close-period-form :periodId="$period->id" :key="'close-'.$period->id" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                            No accounting periods found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</div>
