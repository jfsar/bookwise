---
paths:
  - 'resources/views/**'
---

# Views

## Frontend uses Blade + Livewire with Tailwind CSS
Use Blade templates with Livewire components for the UI. Tailwind CSS for styling. Alpine.js where useful for client-side interactivity. Do not use Inertia, Vue, or React.

## Use Luminous Engine Blade components
Use these Blade components instead of inline Tailwind classes: <x-alert type="success|error|warning|info"> for flash messages, <x-card> for surface-layered containers, <x-ghost-input> for form inputs (surface-low bg, ghost border, bg-shift focus), <x-data-chip color="success|warning|error|info|default|primary"> for status badges, <x-primary-button> for CTAs (green), <x-secondary-button> for cancel/tertiary actions. Always use font-display class for headlines and numerical data.
