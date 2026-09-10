@props(['type' => 'button'])

<button
    type="{{ $type }}"
    {{ $attributes->merge([
        'class' => 'inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-on-surface-muted dark:text-on-surface-muted bg-surface-container dark:bg-surface-container hover:bg-surface-highest dark:hover:bg-surface-highest transition duration-150 focus:outline-none focus:ring-2 focus:ring-primary/20',
    ]) }}
>
    {{ $slot }}
</button>
