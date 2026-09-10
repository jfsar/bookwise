@props(['color' => 'default'])

@php
    $colorMap = [
        'default' => 'bg-surface-container dark:bg-surface-container text-on-surface-muted dark:text-on-surface-muted border-outline-variant/30',
        'primary' => 'bg-primary-container dark:bg-primary-container text-primary dark:text-primary border-primary/20',
        'success' => 'bg-success-container dark:bg-success-container text-success dark:text-success border-success/20',
        'warning' => 'bg-warning-container dark:bg-warning-container text-warning dark:text-warning border-warning/20',
        'error' => 'bg-error-container dark:bg-error-container text-error dark:text-error border-error/20',
        'info' => 'bg-info-container dark:bg-info-container text-info dark:text-info border-info/20',
    ];
    $classes = $colorMap[$color] ?? $colorMap['default'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {$classes}"]) }}>
    {{ $slot }}
</span>
