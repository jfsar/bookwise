<x-layouts::guest>
    <div class="mb-4 text-sm text-on-surface-muted dark:text-on-surface-muted">
        {{ __('Please confirm access to your account by entering one of your authenticator app codes.') }}
    </div>

    @if (session('status'))
        <div class="font-medium text-sm text-success dark:text-success">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('two-factor.login.store') }}">
        @csrf

        <div class="mt-4">
            <x-input-label for="code" :value="__('Code')" />
            <x-text-input id="code" class="block mt-1 w-full" type="text" name="code" autofocus autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]*" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-layouts::guest>
