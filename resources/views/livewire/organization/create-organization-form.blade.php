<div>
    <button
        wire:click="toggleForm"
        class="inline-flex items-center px-4 py-2 bg-surface-container dark:bg-surface-container rounded-lg font-semibold text-sm text-on-surface dark:text-on-surface hover:bg-surface-highest dark:hover:bg-surface-highest focus:bg-surface-highest dark:focus:bg-surface-highest active:bg-surface-container dark:active:bg-surface-container focus:outline-none focus:ring-2 focus:ring-primary/20 transition ease-in-out duration-150"
    >
        {{ $showForm ? 'Cancel' : 'New Organization' }}
    </button>

    @if ($showForm)
        <div class="mt-4 p-6 bg-surface-lowest dark:bg-surface-lowest rounded-xl shadow-ambient-sm">
            <form wire:submit="createOrganization">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Name *</label>
                        <x-ghost-input wire:model="name" type="text" id="name" class="mt-1" />
                        @error('name') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="display_name" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Display Name</label>
                        <x-ghost-input wire:model="display_name" type="text" id="display_name" class="mt-1" />
                    </div>

                    <div>
                        <label for="code" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Code *</label>
                        <x-ghost-input wire:model="code" type="text" id="code" class="mt-1" />
                        @error('code') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="country_code" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Country Code *</label>
                        <x-ghost-input wire:model="country_code" type="text" id="country_code" maxlength="2" class="mt-1" />
                        @error('country_code') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="base_currency_id" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Base Currency *</label>
                        <select
                            wire:model="base_currency_id"
                            id="base_currency_id"
                            class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20"
                        >
                            <option value="">Select currency...</option>
                            @foreach ($this->currencies() as $currency)
                                <option value="{{ $currency->id }}">{{ $currency->code }} - {{ $currency->name }}</option>
                            @endforeach
                        </select>
                        @error('base_currency_id') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="timezone" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Timezone *</label>
                        <select
                            wire:model="timezone"
                            id="timezone"
                            class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20"
                        >
                            <option value="UTC">UTC</option>
                            <option value="America/New_York">Eastern Time</option>
                            <option value="America/Chicago">Central Time</option>
                            <option value="America/Denver">Mountain Time</option>
                            <option value="America/Los_Angeles">Pacific Time</option>
                            <option value="Asia/Manila">Manila (PHT)</option>
                            <option value="Asia/Tokyo">Tokyo (JST)</option>
                            <option value="Europe/London">London (GMT)</option>
                        </select>
                        @error('timezone') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="fiscal_year_start_month" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Fiscal Year Start Month *</label>
                        <select
                            wire:model="fiscal_year_start_month"
                            id="fiscal_year_start_month"
                            class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20"
                        >
                            @foreach (range(1, 12) as $month)
                                <option value="{{ $month }}">{{ \Carbon\Carbon::create()->month($month)->format('F') }}</option>
                            @endforeach
                        </select>
                        @error('fiscal_year_start_month') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="decimal_places" class="block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted">Decimal Places</label>
                        <select
                            wire:model="decimal_places"
                            id="decimal_places"
                            class="mt-1 block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20"
                        >
                            <option value="0">0</option>
                            <option value="2" selected>2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-4">
                    <x-primary-button wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="createOrganization">Create Organization</span>
                        <span wire:loading wire:target="createOrganization">Creating...</span>
                    </x-primary-button>
                </div>
            </form>
        </div>
    @endif
</div>
