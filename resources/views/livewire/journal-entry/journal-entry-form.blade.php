<div>
    @if (!$showForm)
        <button
            wire:click="toggleForm"
            class="inline-flex items-center px-4 py-2 bg-primary dark:bg-primary rounded-lg font-semibold text-sm text-on-primary tracking-wide hover:bg-primary-light dark:hover:bg-primary-light focus:bg-primary-light dark:focus:bg-primary-light active:bg-primary dark:active:bg-primary focus:outline-none focus:ring-2 focus:ring-primary/30 focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
        >
            New Journal Entry
        </button>
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-on-surface/40 backdrop-blur-sm transition-opacity" wire:click="toggleForm"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-surface-lowest dark:bg-surface-lowest rounded-xl text-left overflow-hidden shadow-ambient-lg transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[80vh] overflow-y-auto">
                        <h3 class="text-lg font-medium text-on-surface dark:text-on-surface mb-4 font-display">New Journal Entry</h3>

                        <form wire:submit="createEntry">
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="journalId" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Journal *</label>
                                    <select wire:model="journalId" id="journalId"
                                        class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20">
                                        <option value="">Select journal...</option>
                                        @foreach ($this->journals as $journal)
                                            <option value="{{ $journal->id }}">{{ $journal->code }} - {{ $journal->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('journalId') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="currencyId" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Currency *</label>
                                    <select wire:model="currencyId" id="currencyId"
                                        class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20">
                                        <option value="">Select currency...</option>
                                        @foreach ($this->currencies as $currency)
                                            <option value="{{ $currency->id }}">{{ $currency->code }} - {{ $currency->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('currencyId') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="fiscalYearId" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Fiscal Year *</label>
                                    <select wire:model="fiscalYearId" id="fiscalYearId"
                                        class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20">
                                        <option value="">Select fiscal year...</option>
                                        @foreach ($this->fiscalYears as $fy)
                                            <option value="{{ $fy->id }}">{{ $fy->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('fiscalYearId') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="accountingPeriodId" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Accounting Period *</label>
                                    <select wire:model="accountingPeriodId" id="accountingPeriodId"
                                        class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20">
                                        <option value="">Select period...</option>
                                        @foreach ($this->periods as $period)
                                            <option value="{{ $period->id }}">{{ $period->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('accountingPeriodId') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="entryDate" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Entry Date *</label>
                                    <x-ghost-input wire:model="entryDate" type="date" id="entryDate" class="mt-1" />
                                    @error('entryDate') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="reference" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Reference</label>
                                    <x-ghost-input wire:model="reference" type="text" id="reference" placeholder="Optional reference" class="mt-1" />
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="description" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Description</label>
                                <textarea wire:model="description" id="description" rows="2"
                                    class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface placeholder-on-surface-muted/60 px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
                            </div>

                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Line Items *</h4>
                                    <button type="button" wire:click="addLine"
                                        class="text-sm text-primary dark:text-primary hover:text-primary-light">
                                        + Add Line
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    @foreach ($lines as $index => $line)
                                        <div class="grid grid-cols-12 gap-3 items-center">
                                            <div class="col-span-4">
                                                <select wire:model="lines.{{ $index }}.account_id"
                                                    class="block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20">
                                                    <option value="">Account...</option>
                                                    @foreach ($this->accounts as $account)
                                                        <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-span-2">
                                                <x-ghost-input wire:model="lines.{{ $index }}.debit" type="number" step="0.01" min="0" placeholder="Debit" class="font-display" />
                                            </div>
                                            <div class="col-span-2">
                                                <x-ghost-input wire:model="lines.{{ $index }}.credit" type="number" step="0.01" min="0" placeholder="Credit" class="font-display" />
                                            </div>
                                            <div class="col-span-3">
                                                <x-ghost-input wire:model="lines.{{ $index }}.description" type="text" placeholder="Description" />
                                            </div>
                                            <div class="col-span-1">
                                                @if (count($lines) > 2)
                                                    <button type="button" wire:click="removeLine({{ $index }})"
                                                        class="text-error dark:text-error hover:text-error/80 text-sm">
                                                        &times;
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @error('lines') <p class="mt-2 text-sm text-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="flex items-center justify-between p-4 bg-surface-low dark:bg-surface-low rounded-lg">
                                <div class="text-sm text-on-surface-muted dark:text-on-surface-muted font-display">
                                    <span>Debit: <strong class="text-on-surface dark:text-on-surface">${{ number_format($this->totalDebit(), 2) }}</strong></span>
                                    <span class="mx-3 text-on-surface-muted/30">|</span>
                                    <span>Credit: <strong class="text-on-surface dark:text-on-surface">${{ number_format($this->totalCredit(), 2) }}</strong></span>
                                    <span class="mx-3 text-on-surface-muted/30">|</span>
                                    @if ($this->isBalanced())
                                        <span class="text-success dark:text-success font-medium">Balanced</span>
                                    @else
                                        <span class="text-error dark:text-error font-medium">Unbalanced</span>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-6 flex items-center gap-3 justify-end">
                                <x-secondary-button type="button" wire:click="toggleForm">
                                    Cancel
                                </x-secondary-button>
                                <x-primary-button
                                    wire:loading.attr="disabled"
                                    {{ $this->isBalanced() ? '' : 'disabled' }}>
                                    Create Entry
                                </x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
