<div>
    @if (!$showForm)
        <button
            wire:click="toggleForm"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
        >
            New Journal Entry
        </button>
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="toggleForm"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[80vh] overflow-y-auto">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">New Journal Entry</h3>

                        <form wire:submit="createEntry">
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="journalId" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Journal *</label>
                                    <select wire:model="journalId" id="journalId"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Select journal...</option>
                                        @foreach ($this->journals as $journal)
                                            <option value="{{ $journal->id }}">{{ $journal->code }} - {{ $journal->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('journalId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="currencyId" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Currency *</label>
                                    <select wire:model="currencyId" id="currencyId"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Select currency...</option>
                                        @foreach ($this->currencies as $currency)
                                            <option value="{{ $currency->id }}">{{ $currency->code }} - {{ $currency->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('currencyId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="fiscalYearId" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fiscal Year *</label>
                                    <select wire:model="fiscalYearId" id="fiscalYearId"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Select fiscal year...</option>
                                        @foreach ($this->fiscalYears as $fy)
                                            <option value="{{ $fy->id }}">{{ $fy->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('fiscalYearId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="accountingPeriodId" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Accounting Period *</label>
                                    <select wire:model="accountingPeriodId" id="accountingPeriodId"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Select period...</option>
                                        @foreach ($this->periods as $period)
                                            <option value="{{ $period->id }}">{{ $period->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('accountingPeriodId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="entryDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Entry Date *</label>
                                    <input wire:model="entryDate" type="date" id="entryDate"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    @error('entryDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="reference" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reference</label>
                                    <input wire:model="reference" type="text" id="reference" placeholder="Optional reference"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                <textarea wire:model="description" id="description" rows="2"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                            </div>

                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Line Items *</h4>
                                    <button type="button" wire:click="addLine"
                                        class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-500">
                                        + Add Line
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    @foreach ($lines as $index => $line)
                                        <div class="grid grid-cols-12 gap-3 items-center">
                                            <div class="col-span-4">
                                                <select wire:model="lines.{{ $index }}.account_id"
                                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                    <option value="">Account...</option>
                                                    @foreach ($this->accounts as $account)
                                                        <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-span-2">
                                                <input wire:model="lines.{{ $index }}.debit" type="number" step="0.01" min="0" placeholder="Debit"
                                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            </div>
                                            <div class="col-span-2">
                                                <input wire:model="lines.{{ $index }}.credit" type="number" step="0.01" min="0" placeholder="Credit"
                                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            </div>
                                            <div class="col-span-3">
                                                <input wire:model="lines.{{ $index }}.description" type="text" placeholder="Description"
                                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            </div>
                                            <div class="col-span-1">
                                                @if (count($lines) > 2)
                                                    <button type="button" wire:click="removeLine({{ $index }})"
                                                        class="text-red-600 dark:text-red-400 hover:text-red-500 text-sm">
                                                        &times;
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @error('lines') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    <span>Debit: <strong class="text-gray-900 dark:text-gray-100">${{ number_format($this->totalDebit(), 2) }}</strong></span>
                                    <span class="mx-3">|</span>
                                    <span>Credit: <strong class="text-gray-900 dark:text-gray-100">${{ number_format($this->totalCredit(), 2) }}</strong></span>
                                    <span class="mx-3">|</span>
                                    @if ($this->isBalanced())
                                        <span class="text-green-600 dark:text-green-400 font-medium">Balanced</span>
                                    @else
                                        <span class="text-red-600 dark:text-red-400 font-medium">Unbalanced</span>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-6 flex items-center gap-3 justify-end">
                                <button type="button" wire:click="toggleForm"
                                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 bg-indigo-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition"
                                    wire:loading.attr="disabled"
                                    {{ $this->isBalanced() ? '' : 'disabled' }}>
                                    Create Entry
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
