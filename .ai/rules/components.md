---
paths:
  - 'resources/js/Components/**'
  - 'resources/js/Pages/**'
---

# Components

## shadcn/ui is the only UI component library
Use shadcn/ui components (installed in `resources/js/Components/ui`, registered in `components.json`) exclusively. Never create bespoke React primitives when a shadcn component covers the need, and never re-add old "Luminous Engine" custom components — they were replaced and removed.

## Luminous look via shadcn
The Luminous design is preserved by mapping shadcn CSS variables (`--background`, `--primary`, `--border`, `--radius`, etc.) to Luminous palette hex values in `resources/css/app.css`. Do not swap back to the default shadcn gray palette; keep the color-mix-based `--border`/`--input`, `--radius: 0.75rem`, and the green primary.

## Conventions
- Component files must be `.jsx` (Vite 8/rolldown does not transform JSX in `.js`).
- Import from `@/Components/ui/...` (capital `C` — the Vite alias is `resources/js`; lowercase paths break Linux builds). shadcn's generated `@/components/ui/...` internal imports must be rewritten to capital C.
- Use `cn` from `@/lib/utils` for conditional classes. Tailwind tokens live in `resources/css/app.css` `@theme`.
- `font-display` (Space Grotesk) for headlines and numerical data.

## Migration mappings (from the removed custom components)
- `Button` → `ui/button`: `variant="default"` (green CTA), `secondary` (cancel), `destructive` (danger), `outline` (warning/success, add `text-warning`/`text-success` className).
- `Chip` → `ui/badge` with container-class overrides, e.g. `bg-success-container text-success dark:bg-success-container dark:text-success border-transparent`, `bg-warning-container`, `bg-error-container`, `bg-info-container`, `bg-surface-container` (default).
- `Modal` → `ui/dialog` with `DialogHeader`/`DialogTitle`. Widths: default `sm:max-w-sm`, `xl` → `sm:max-w-4xl`.
- `Label value="..."` → `ui/label` with children.
- `InputError messages={errors.x}` → inline `<p className="mt-1 text-sm font-medium text-destructive">{errors.x}</p>`.
- `Select` → `ui/native-select` with `className="w-full"` (wrap in a fixed-width `div` for filters).
- `Card padded={false}` → `ui/card` inside `Table` wrappers; list tables use `ui/table` (`Table/TableHeader/TableBody/TableRow/TableHead/TableCell`) with `TableHead` rows `className="text-xs uppercase tracking-wider text-muted-foreground"`.
- Flash `Alert` → `ui/alert` with `bg-success-container text-success` / `bg-error-container text-error` className.

## Use shared StatusBadge for all data badges
All status/label badges on index pages must use the shared Components/StatusBadge.jsx (tone prop: primary/success/warning/error/info/default). Do not re-declare per-file badgeColors maps; the tone classes live in StatusBadge and are contrast-safe for both themes.
