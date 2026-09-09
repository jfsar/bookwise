<div class="relative" x-data="{ open: false }">
    <button
        @click="open = !open"
        class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition"
    >
        @if ($this->currentOrganization())
            <span class="truncate max-w-[200px]">{{ $this->currentOrganization()->display_name ?? $this->currentOrganization()->name }}</span>
        @else
            <span>Select Organization</span>
        @endif
        <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
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
        class="absolute right-0 z-50 mt-2 w-64 origin-top-right rounded-md bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
    >
        <div class="py-1">
            @forelse ($this->organizations() as $organization)
                <button
                    wire:click="switchTo({{ $organization->id }})"
                    @click="open = false"
                    class="w-full text-left px-4 py-2 text-sm {{ $this->currentOrganization()?->id === $organization->id ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-300' }} hover:bg-gray-100 dark:hover:bg-gray-700"
                >
                    <div class="font-medium">{{ $organization->display_name ?? $organization->name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $organization->code }}</div>
                </button>
            @empty
                <div class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">No organizations found.</div>
            @endforelse
        </div>
    </div>
</div>
