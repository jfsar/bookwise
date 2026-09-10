@props(['type' => 'success'])

@php
    $colorMap = [
        'success' => 'bg-success-container text-success',
        'error' => 'bg-error-container text-error',
        'warning' => 'bg-warning-container text-warning',
        'info' => 'bg-info-container text-info',
    ];
    $classes = $colorMap[$type] ?? $colorMap['success'];
@endphp

@if (session($type) || $slot->isNotEmpty())
    <div {{ $attributes->merge(['class' => "rounded-lg px-4 py-3 text-sm font-medium {$classes}"]) }}>
        {{ session($type) ?: $slot }}
    </div>
@endif
