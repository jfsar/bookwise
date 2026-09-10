@props(['type' => 'text', 'value' => ''])

<input
    {{ $attributes->merge([
        'type' => $type,
        'value' => $value,
        'class' => 'block w-full rounded-lg bg-surface-low dark:bg-surface-low border border-outline-variant/20 dark:border-outline-variant/20 text-on-surface dark:text-on-surface placeholder-on-surface-muted/60 px-4 py-2.5 text-sm transition duration-150 focus:bg-surface-lowest dark:focus:bg-surface-lowest focus:border-primary dark:focus:border-primary focus:ring-2 focus:ring-primary/20',
    ]) }}
/>
