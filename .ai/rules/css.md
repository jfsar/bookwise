---
paths:
  - 'resources/css/**'
---

# Css

## Luminous Engine design system tokens
This project uses the "Luminous Engine" design system defined in DESIGN.md. All theme tokens are in resources/css/app.css under @theme. Key rules: (1) No 1px solid borders on containers — use surface bg-shifts for separation. (2) Use shadow-ambient-* for shadows (tinted, diffused), never standard shadow-sm/shadow-md. (3) Primary = green (#006d32 light / #34d67a dark). (4) Font-display = Space Grotesk for headlines/numbers. (5) Dark mode uses class-based variant via @custom-variant at top of app.css.

## shadcn/ui variable mapping
shadcn CSS variables (:root/.dark, --background, --foreground, --primary, --primary-foreground, --border, --input, --muted, --accent, --radius, etc.) are mapped to the Luminous palette in app.css — never revert them to shadcn defaults. --border/--input use color-mix of --color-outline-variant (opacity 45%/35%). New shadcn surface utilities may be used for states and inputs, but keep Luminous bg-shift and ambient-shadow conventions for page layout.
