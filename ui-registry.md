## Baseline — Authentication UI

### Passenger registration and sign-in

File: `register.php`, `login.php`, `includes/header.php`, `includes/navbar.php`, `includes/footer.php`
Last updated: 2026-11-06

| Property | Pattern |
|---|---|
| Page background | `--airline-canvas: #f3f6fa` |
| Surface | White Bootstrap navbar, card, and footer |
| Border | `--airline-border: #dce5ed` |
| Border radius | `1rem` card; `0.65rem` form controls; pill role badge |
| Text — primary | `--airline-navy: #17324d` |
| Text — secondary | Bootstrap `text-secondary` |
| Accent | `--airline-blue: #176b87` |
| Spacing | Bootstrap spacing utilities; responsive `clamp()` vertical section padding |
| Focus | Blue border and visible translucent focus ring |
| Shadow | Bootstrap `shadow-sm` on authentication cards |
| Typography | Bootstrap system font stack; bold compact brand and uppercase tracked eyebrow |

**Pattern notes:**
Use the navy/blue/white airline palette, restrained borders, rounded form controls, and visible keyboard focus for future account and reservation pages. Keep authentication content in a centered responsive card. Color is supplementary: labels, role text, validation copy, and semantic markup must remain understandable without color.
