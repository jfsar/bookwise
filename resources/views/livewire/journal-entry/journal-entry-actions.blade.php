<div>
    @if ($entry->isDraft())
        <button wire:click="submitEntry" wire:loading.attr="disabled"
            class="inline-flex items-center px-3 py-1.5 bg-warning dark:bg-warning rounded-lg text-xs font-medium text-on-primary hover:bg-warning/80 transition">
            Submit
        </button>
        <button wire:click="$set('showCancelModal', true)" wire:loading.attr="disabled"
            class="inline-flex items-center px-3 py-1.5 bg-surface-container dark:bg-surface-container rounded-lg text-xs font-medium text-on-surface-muted dark:text-on-surface-muted hover:bg-surface-highest dark:hover:bg-surface-highest transition">
            Cancel
        </button>
    @endif

    @if ($entry->status->value === 'submitted')
        <button wire:click="approveEntry" wire:loading.attr="disabled"
            class="inline-flex items-center px-3 py-1.5 bg-success dark:bg-success rounded-lg text-xs font-medium text-on-primary hover:bg-success/80 transition">
            Approve
        </button>
        <button wire:click="$set('showCancelModal', true)" wire:loading.attr="disabled"
            class="inline-flex items-center px-3 py-1.5 bg-surface-container dark:bg-surface-container rounded-lg text-xs font-medium text-on-surface-muted dark:text-on-surface-muted hover:bg-surface-highest dark:hover:bg-surface-highest transition">
            Cancel
        </button>
    @endif

    @if ($entry->status->value === 'approved')
        <button wire:click="postEntry" wire:loading.attr="disabled"
            class="inline-flex items-center px-3 py-1.5 bg-primary dark:bg-primary rounded-lg text-xs font-medium text-on-primary hover:bg-primary-light dark:hover:bg-primary-light transition">
            Post
        </button>
    @endif

    @if ($entry->isPosted())
        <button wire:click="$set('showReverseModal', true)" wire:loading.attr="disabled"
            class="inline-flex items-center px-3 py-1.5 bg-error dark:bg-error rounded-lg text-xs font-medium text-on-primary hover:bg-error/80 transition">
            Reverse
        </button>
    @endif

    @if ($showReverseModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-on-surface/40 backdrop-blur-sm transition-opacity" wire:click="$set('showReverseModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-surface-lowest dark:bg-surface-lowest rounded-xl text-left overflow-hidden shadow-ambient-lg transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-on-surface dark:text-on-surface mb-4 font-display">Reverse Entry</h3>
                        <p class="text-sm text-on-surface-muted dark:text-on-surface-muted mb-4">
                            This will create a new reversing entry and mark the original as reversed.
                        </p>
                        <form wire:submit="reverseEntry">
                            <div>
                                <label for="reversalReason" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Reason *</label>
                                <textarea wire:model="reversalReason" id="reversalReason" rows="3"
                                    class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface placeholder-on-surface-muted/60 px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    placeholder="Why is this entry being reversed?"></textarea>
                                @error('reversalReason') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="mt-4 flex items-center gap-3 justify-end">
                                <x-secondary-button type="button" wire:click="$set('showReverseModal', false)">
                                    Cancel
                                </x-secondary-button>
                                <button type="submit" wire:loading.attr="disabled"
                                    class="px-4 py-2 bg-error dark:bg-error rounded-lg text-sm font-medium text-on-primary hover:bg-error/80 transition focus:outline-none focus:ring-2 focus:ring-error/30">
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
                <div class="fixed inset-0 bg-on-surface/40 backdrop-blur-sm transition-opacity" wire:click="$set('showCancelModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-surface-lowest dark:bg-surface-lowest rounded-xl text-left overflow-hidden shadow-ambient-lg transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-on-surface dark:text-on-surface mb-4 font-display">Cancel Entry</h3>
                        <p class="text-sm text-on-surface-muted dark:text-on-surface-muted mb-4">
                            This will cancel the entry. This action cannot be undone.
                        </p>
                        <form wire:submit="cancelEntry">
                            <div>
                                <label for="cancelReason" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Reason *</label>
                                <textarea wire:model="cancelReason" id="cancelReason" rows="3"
                                    class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface placeholder-on-surface-muted/60 px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    placeholder="Why is this entry being cancelled?"></textarea>
                                @error('cancelReason') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="mt-4 flex items-center gap-3 justify-end">
                                <x-secondary-button type="button" wire:click="$set('showCancelModal', false)">
                                    Back
                                </x-secondary-button>
                                <button type="submit" wire:loading.attr="disabled"
                                    class="px-4 py-2 bg-error dark:bg-error rounded-lg text-sm font-medium text-on-primary hover:bg-error/80 transition focus:outline-none focus:ring-2 focus:ring-error/30">
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
