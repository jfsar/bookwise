@props(['type' => 'submit'])

<button type="{{ $type }}" {{ $attributes->merge(['class' => 'inline-flex items-center px-4 py-2 bg-primary dark:bg-primary rounded-lg font-semibold text-sm text-on-primary tracking-wide hover:bg-primary-light dark:hover:bg-primary-light focus:bg-primary-light dark:focus:bg-primary-light active:bg-primary dark:active:bg-primary focus:outline-none focus:ring-2 focus:ring-primary/30 focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
