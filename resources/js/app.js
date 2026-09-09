import persist from '@alpinejs/persist'
import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm'

Alpine.plugin(persist)

Livewire.start()