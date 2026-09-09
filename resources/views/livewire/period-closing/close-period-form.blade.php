<div>
    @if ($period = \App\Models\AccountingPeriod::find($periodId))
        @if ($period->isOpen())
            <button wire:click="toggleForm"
                class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md text-xs font-medium text-white hover:bg-red-500 transition">
                Close
            </button>
        @elseif ($period->status->value === 'closed')
            <button wire:click="toggleForm"
                class="inline-flex items-center px-3 py-1.5 bg-yellow-600 border border-transparent rounded-md text-xs font-medium text-white hover:bg-yellow-500 transition">
                Reopen
            </button>
        @endif
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="toggleForm"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            {{ $period->isOpen() ? 'Close Period' : 'Reopen Period' }}
                        </h3>

                        @if ($period->isOpen())
                            <form wire:submit="closePeriod">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                    Closing this period will prevent any new entries from being posted to it. A closing entry will be automatically generated.
                                </p>
                                <div>
                                    <label for="reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason (optional)</label>
                                    <textarea wire:model="reason" id="reason" rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        placeholder="Why is this period being closed?"></textarea>
                                </div>
                                <div class="mt-4 flex items-center gap-3 justify-end">
                                    <button type="button" wire:click="toggleForm"
                                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                        class="px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-500 transition">
                                        Close Period
                                    </button>
                                </div>
                            </form>
                        @else
                            <form wire:submit="reopenPeriod">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                    Reopening this period will allow new entries to be posted. The next period must be open.
                                </p>
                                <div>
                                    <label for="reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason *</label>
                                    <textarea wire:model="reason" id="reason" rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        placeholder="Why is this period being reopened?"></textarea>
                                    @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="mt-4 flex items-center gap-3 justify-end">
                                    <button type="button" wire:click="toggleForm"
                                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                        class="px-4 py-2 bg-yellow-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-yellow-500 transition">
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
