---
paths:
  - 'resources/css/**'
---

# Css

## Luminous Engine design system tokens
This project uses the "Luminous Engine" design system defined in DESIGN.md. All theme tokens are in resources/css/app.css under @theme. Key rules: (1) No 1px solid borders on containers — use surface bg-shifts for separation. (2) Use shadow-ambient-* for shadows (tinted, diffused), never standard shadow-sm/shadow-md. (3) Primary = green (#006d32 light / #34d67a dark). (4) Font-display = Space Grotesk for headlines/numbers. (5) Dark mode uses class-based variant via @custom-variant at top of app.css.
