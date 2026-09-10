@props(['padding' => true])

<div {{ $attributes->merge(['class' => 'bg-surface-lowest dark:bg-surface-lowest rounded-xl shadow-ambient-sm ' . ($padding ? 'p-6' : '')]) }}>
    {{ $slot }}
</div>
