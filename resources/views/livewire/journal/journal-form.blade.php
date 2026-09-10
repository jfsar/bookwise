<div>
    @if (!$showForm)
        <button
            wire:click="toggleForm"
            class="inline-flex items-center px-4 py-2 bg-primary dark:bg-primary rounded-lg font-semibold text-sm text-on-primary tracking-wide hover:bg-primary-light dark:hover:bg-primary-light focus:bg-primary-light dark:focus:bg-primary-light active:bg-primary dark:active:bg-primary focus:outline-none focus:ring-2 focus:ring-primary/30 focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
        >
            New Journal
        </button>
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-on-surface/40 backdrop-blur-sm transition-opacity" wire:click="toggleForm"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-surface-lowest dark:bg-surface-lowest rounded-xl text-left overflow-hidden shadow-ambient-lg transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-on-surface dark:text-on-surface mb-4 font-display">New Journal</h3>

                        <form wire:submit="createJournal">
                            <div class="space-y-4">
                                <div>
                                    <label for="code" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Code *</label>
                                    <x-ghost-input wire:model="code" type="text" id="code" placeholder="e.g. GL" class="mt-1" />
                                    @error('code') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="name" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Name *</label>
                                    <x-ghost-input wire:model="name" type="text" id="name" placeholder="e.g. General Ledger" class="mt-1" />
                                    @error('name') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="description" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Description</label>
                                    <textarea wire:model="description" id="description" rows="3"
                                        class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface placeholder-on-surface-muted/60 px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
                                    @error('description') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="mt-6 flex items-center gap-3 justify-end">
                                <x-secondary-button type="button" wire:click="toggleForm">
                                    Cancel
                                </x-secondary-button>
                                <x-primary-button wire:loading.attr="disabled">
                                    Create Journal
                                </x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
