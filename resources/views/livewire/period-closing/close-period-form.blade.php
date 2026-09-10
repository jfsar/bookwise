<div>
    @if ($period)
        @if ($period->isOpen())
            <button wire:click="toggleForm"
                class="inline-flex items-center px-3 py-1.5 bg-error dark:bg-error rounded-lg text-xs font-medium text-on-primary hover:bg-error/80 transition">
                Close
            </button>
        @elseif ($period->status->value === 'closed')
            <button wire:click="toggleForm"
                class="inline-flex items-center px-3 py-1.5 bg-warning dark:bg-warning rounded-lg text-xs font-medium text-on-primary hover:bg-warning/80 transition">
                Reopen
            </button>
        @endif
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-on-surface/40 backdrop-blur-sm transition-opacity" wire:click="toggleForm"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-surface-lowest dark:bg-surface-lowest rounded-xl text-left overflow-hidden shadow-ambient-lg transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-on-surface dark:text-on-surface mb-4 font-display">
                            {{ $period->isOpen() ? 'Close Period' : 'Reopen Period' }}
                        </h3>

                        @if ($period->isOpen())
                            <form wire:submit="closePeriod">
                                <p class="text-sm text-on-surface-muted dark:text-on-surface-muted mb-4">
                                    Closing this period will prevent any new entries from being posted to it. A closing entry will be automatically generated.
                                </p>
                                <div>
                                    <label for="reason" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Reason (optional)</label>
                                    <textarea wire:model="reason" id="reason" rows="3"
                                        class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface placeholder-on-surface-muted/60 px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20"
                                        placeholder="Why is this period being closed?"></textarea>
                                </div>
                                <div class="mt-4 flex items-center gap-3 justify-end">
                                    <x-secondary-button type="button" wire:click="toggleForm">
                                        Cancel
                                    </x-secondary-button>
                                    <button type="submit" wire:loading.attr="disabled"
                                        class="px-4 py-2 bg-error dark:bg-error rounded-lg text-sm font-medium text-on-primary hover:bg-error/80 transition focus:outline-none focus:ring-2 focus:ring-error/30">
                                        Close Period
                                    </button>
                                </div>
                            </form>
                        @else
                            <form wire:submit="reopenPeriod">
                                <p class="text-sm text-on-surface-muted dark:text-on-surface-muted mb-4">
                                    Reopening this period will allow new entries to be posted. The next period must be open.
                                </p>
                                <div>
                                    <label for="reason" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Reason *</label>
                                    <textarea wire:model="reason" id="reason" rows="3"
                                        class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface placeholder-on-surface-muted/60 px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20"
                                        placeholder="Why is this period being reopened?"></textarea>
                                    @error('reason') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="mt-4 flex items-center gap-3 justify-end">
                                    <x-secondary-button type="button" wire:click="toggleForm">
                                        Cancel
                                    </x-secondary-button>
                                    <button type="submit" wire:loading.attr="disabled"
                                        class="px-4 py-2 bg-warning dark:bg-warning rounded-lg text-sm font-medium text-on-primary hover:bg-warning/80 transition focus:outline-none focus:ring-2 focus:ring-warning/30">
                                        Reopen Period
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
