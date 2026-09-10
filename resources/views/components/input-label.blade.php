@props(['for', 'value' => null])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-on-surface-muted dark:text-on-surface-muted']) }}>
    {{ $value ?? $slot }}
</label>
