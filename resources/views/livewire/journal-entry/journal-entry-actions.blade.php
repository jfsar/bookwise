<div>
    @if ($entry->isDraft())
        <button wire:click="submitEntry"
            class="inline-flex items-center px-3 py-1.5 bg-yellow-600 border border-transparent rounded-md text-xs font-medium text-white hover:bg-yellow-500 transition">
            Submit
        </button>
        <button wire:click="$set('showCancelModal', true)"
            class="inline-flex items-center px-3 py-1.5 bg-gray-600 border border-transparent rounded-md text-xs font-medium text-white hover:bg-gray-500 transition">
            Cancel
        </button>
    @endif

    @if ($entry->status->value === 'submitted')
        <button wire:click="approveEntry"
            class="inline-flex items-center px-3 py-1.5 bg-green-600 border border-transparent rounded-md text-xs font-medium text-white hover:bg-green-500 transition">
            Approve
        </button>
        <button wire:click="$set('showCancelModal', true)"
            class="inline-flex items-center px-3 py-1.5 bg-gray-600 border border-transparent rounded-md text-xs font-medium text-white hover:bg-gray-500 transition">
            Cancel
        </button>
    @endif

    @if ($entry->status->value === 'approved')
        <button wire:click="postEntry"
            class="inline-flex items-center px-3 py-1.5 bg-blue-600 border border-transparent rounded-md text-xs font-medium text-white hover:bg-blue-500 transition">
            Post
        </button>
    @endif

    @if ($entry->isPosted())
        <button wire:click="$set('showReverseModal', true)"
            class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md text-xs font-medium text-white hover:bg-red-500 transition">
            Reverse
        </button>
    @endif

    @if ($showReverseModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="$set('showReverseModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Reverse Entry</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            This will create a new reversing entry and mark the original as reversed.
                        </p>
                        <form wire:submit="reverseEntry">
                            <div>
                                <label for="reversalReason" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason *</label>
                                <textarea wire:model="reversalReason" id="reversalReason" rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    placeholder="Why is this entry being reversed?"></textarea>
                                @error('reversalReason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="mt-4 flex items-center gap-3 justify-end">
                                <button type="button" wire:click="$set('showReverseModal', false)"
                                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-500 transition">
                                    Confirm Reverse
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showCancelModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="$set('showCancelModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Cancel Entry</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            This will cancel the entry. This action cannot be undone.
                        </p>
                        <form wire:submit="cancelEntry">
                            <div>
                                <label for="cancelReason" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason *</label>
                                <textarea wire:model="cancelReason" id="cancelReason" rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    placeholder="Why is this entry being cancelled?"></textarea>
                                @error('cancelReason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="mt-4 flex items-center gap-3 justify-end">
                                <button type="button" wire:click="$set('showCancelModal', false)"
                                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    Back
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-500 transition">
                                    Confirm Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
