<div class="relative" x-data="{ open: false }">
    <button
        @click="open = !open"
        class="inline-flex items-center gap-2 px-4 py-2 bg-surface-lowest/80 dark:bg-surface-lowest/80 backdrop-blur-xl rounded-lg shadow-ambient-sm text-sm font-medium text-on-surface dark:text-on-surface hover:bg-surface-low dark:hover:bg-surface-low focus:outline-none focus:ring-2 focus:ring-primary/20 transition"
    >
        @if ($this->currentOrganization)
            <span class="truncate max-w-[200px]">{{ $this->currentOrganization->display_name ?? $this->currentOrganization->name }}</span>
        @else
            <span>Select Organization</span>
        @endif
        <svg class="h-4 w-4 text-on-surface-muted" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </button>

    <div
        x-show="open"
        @click.away="open = false"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 z-50 mt-2 w-64 origin-top-right rounded-xl bg-surface-lowest/90 dark:bg-surface-lowest/90 backdrop-blur-xl shadow-ambient-md focus:outline-none"
    >
        <div class="py-1">
            @forelse ($this->organizations as $organization)
                <button
                    wire:key="org-{{ $organization->id }}"
                    wire:click="switchTo({{ $organization->id }})"
                    @click="open = false"
                    class="w-full text-left px-4 py-2 text-sm {{ $this->currentOrganization?->id === $organization->id ? 'bg-primary-container dark:bg-primary-container text-primary dark:text-primary' : 'text-on-surface dark:text-on-surface' }} hover:bg-surface-low dark:hover:bg-surface-low transition"
                >
                    <div class="font-medium">{{ $organization->display_name ?? $organization->name }}</div>
                    <div class="text-xs text-on-surface-muted dark:text-on-surface-muted">{{ $organization->code }}</div>
                </button>
            @empty
                <div class="px-4 py-2 text-sm text-on-surface-muted dark:text-on-surface-muted">No organizations found.</div>
            @endforelse
        </div>
    </div>
</div>
