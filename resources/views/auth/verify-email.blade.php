<x-layouts::guest>
    <div class="mb-4 text-sm text-on-surface-muted dark:text-on-surface-muted">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status'))
        <div class="font-medium text-sm text-success dark:text-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-on-surface-muted dark:text-on-surface-muted hover:text-on-surface dark:hover:text-on-surface rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary dark:focus:ring-offset-surface-lowest">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-layouts::guest>
