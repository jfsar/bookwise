<div>
    @if (!$accountId && !$showForm)
        <button
            wire:click="toggleForm"
            class="inline-flex items-center px-4 py-2 bg-primary dark:bg-primary rounded-lg font-semibold text-sm text-on-primary tracking-wide hover:bg-primary-light dark:hover:bg-primary-light focus:bg-primary-light dark:focus:bg-primary-light active:bg-primary dark:active:bg-primary focus:outline-none focus:ring-2 focus:ring-primary/30 focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
        >
            New Account
        </button>
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-on-surface/40 backdrop-blur-sm transition-opacity" wire:click="toggleForm"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-surface-lowest dark:bg-surface-lowest rounded-xl text-left overflow-hidden shadow-ambient-lg transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-on-surface dark:text-on-surface mb-4 font-display">
                            {{ $accountId ? 'Edit Account' : 'New Account' }}
                        </h3>

                        <form wire:submit="saveAccount">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="code" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Code *</label>
                                    <x-ghost-input wire:model="code" type="text" id="code" class="mt-1" />
                                    @error('code') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="name" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Name *</label>
                                    <x-ghost-input wire:model="name" type="text" id="name" class="mt-1" />
                                    @error('name') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                                </div>

                                <div class="col-span-2">
                                    <label for="description" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Description</label>
                                    <textarea wire:model="description" id="description" rows="2"
                                        class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface placeholder-on-surface-muted/60 px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
                                </div>

                                <div>
                                    <label for="account_type_id" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Account Type *</label>
                                    <select wire:model="account_type_id" id="account_type_id"
                                        class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20">
                                        <option value="">Select type...</option>
                                        @foreach ($this->accountTypes as $type)
                                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('account_type_id') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="normal_balance" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Normal Balance *</label>
                                    <select wire:model="normal_balance" id="normal_balance"
                                        class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20">
                                        <option value="debit">Debit</option>
                                        <option value="credit">Credit</option>
                                    </select>
                                    @error('normal_balance') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="parent_id" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Parent Account</label>
                                    <select wire:model="parent_id" id="parent_id"
                                        class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20">
                                        <option value="">None (Root)</option>
                                        @foreach ($this->parentAccounts as $parent)
                                            <option value="{{ $parent->id }}">{{ $parent->code }} - {{ $parent->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('parent_id') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                                </div>

                                <div class="col-span-2">
                                    <div class="flex items-center gap-6">
                                        <label class="flex items-center">
                                            <input type="checkbox" wire:model="is_posting_account" class="rounded border-outline-variant/30 text-primary shadow-sm focus:ring-primary/20 bg-surface-low dark:bg-surface-low" />
                                            <span class="ml-2 text-sm text-on-surface-muted dark:text-on-surface-muted">Posting Account</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" wire:model="is_active" class="rounded border-outline-variant/30 text-primary shadow-sm focus:ring-primary/20 bg-surface-low dark:bg-surface-low" />
                                            <span class="ml-2 text-sm text-on-surface-muted dark:text-on-surface-muted">Active</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" wire:model="allow_manual_posting" class="rounded border-outline-variant/30 text-primary shadow-sm focus:ring-primary/20 bg-surface-low dark:bg-surface-low" />
                                            <span class="ml-2 text-sm text-on-surface-muted dark:text-on-surface-muted">Allow Manual Posting</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 flex items-center gap-3 justify-end">
                                <x-secondary-button type="button" wire:click="toggleForm">
                                    Cancel
                                </x-secondary-button>
                                <x-primary-button wire:loading.attr="disabled">
                                    {{ $accountId ? 'Update Account' : 'Create Account' }}
                                </x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
