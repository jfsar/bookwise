---
paths:
  - 'resources/views/**'
  - 'resources/js/**'
  - resources/views/app.blade.php
---

# Views

## Frontend is Inertia (Laravel) + React, not Blade + Livewire
Pages live in `resources/js/Pages`, rendered server-side with `Inertia::render()`. Do not use Livewire, Alpine, or Blade page templates. Only `resources/views/app.blade.php` remains (root `<div id="app">` + `@vite(...)`).

## React page patterns
- Entry point: `resources/js/app.jsx` registers pages via `import.meta.glob('./Pages/**/*.jsx', { eager: true })` with `resolve: (name) => pages['./Pages/' + name + '.jsx']`.
- Pages opt into the shell with a static export: `Page.layout = (page) => <AppLayout>{page}</AppLayout>` (auth pages use `GuestLayout`).
- Files must be `.jsx` — Vite 8 (rolldown) does not transform JSX in `.js` files.

## Use shadcn/ui components
Pages and components use shadcn/ui primitives from `resources/js/Components/ui` (installed via `npx shadcn@latest add`, registered in `components.json`). See `.ai/rules/components.md` for conventions and the mapping from the removed Luminous primitives. Inline Luminous token classes (e.g. `bg-surface-low`, `shadow-ambient-sm`, `text-on-surface-muted`, `font-display`) remain valid for page layout.

## Filtering patterns
Server-rendered filters use query params (`search`, `type`, `status`, `fiscal_year_id`) with `->withQueryString()`. Client-side navigation to a filtered view uses `router.get(url, { ...filters }, { preserveState: true, replace: true })`.

## Inertia + React stack replaces Livewire + Blade
Livewire, Alpine, and Blade page templates are removed. Pages are React components in resources/js/Pages rendered via Inertia::render(). Files must be .jsx (Vite 8/rolldown won't transform JSX in .js). Pages opt into the app shell via `Page.layout = (page) => <AppLayout>{page}</AppLayout>`; auth pages use GuestLayout. Entry app.jsx resolves pages with eager import.meta.glob.

## Use shadcn/ui exclusively; keep Luminous tokens
All UI must use shadcn/ui components from resources/js/Components/ui (install missing ones with `npx shadcn@latest add`). The custom Luminous primitives (Alert, Button, Card, Chip, Input, InputError, Label, Modal, Select, Textarea) were deleted — do not recreate them. Imports must be `@/Components/ui/...` (capital C). Luminous palette is preserved via shadcn CSS variables in resources/css/app.css; keep Luminous bg-shift/ambient-shadow/font-display classes in page layout. Mapping from old components: see .ai/rules/components.md.

## React apps need @viteReactRefresh before @vite
This is an Inertia + React + Vite app. The root view must call @viteReactRefresh before @vite in the <head>. Without it, SSR-rendered pages lack the React refresh preamble and the browser throws "@vitejs/plugin-react can't detect preamble", breaking HMR. Do not remove it.

## Always inject @fonts in the root view
The app customizes fonts via vite.config.js (bunny Inter + Space Grotesk). The root view MUST call @fonts (before @vite, after @viteReactRefresh) so Laravel injects the @font-face rules and preload links from the fonts manifest. Without it, --font-sans/--font-display silently fall back to system-ui even though the font files are built fine.
