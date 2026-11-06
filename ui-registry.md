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

### Admin flight dashboard

File: `views/admin/flights.php`
Last updated: 2026-11-06

| Property | Pattern |
|---|---|
| Page background | `--airline-canvas: #f3f6fa` |
| Surface | White Bootstrap cards with `shadow-sm` |
| Border | `--airline-border: #dce5ed` |
| Border radius | `1rem` panels; `0.65rem` form controls; pill count badge |
| Text — primary | `--airline-navy: #17324d` |
| Text — secondary | Bootstrap `text-secondary` |
| Accent | `--airline-blue: #176b87`; pale blue count badge |
| Spacing | Bootstrap spacing utilities; responsive `clamp()` section padding |
| Focus | Blue border and visible translucent focus ring |
| Shadow | Bootstrap `shadow-sm` on dashboard panels |
| Typography | Bootstrap system font stack; uppercase tracked eyebrow and table headers |

**Pattern notes:**
Use a responsive airport-summary and flight-table layout. Use Bootstrap `.modal` for airport and flight entry with `.modal-dialog-scrollable` for longer forms, labeled close buttons, and associated form labels. Keep flight actions visibly labeled and place the table in `.table-responsive` for narrow viewports. Use Bootstrap `.pagination`/`.page-link` with `aria-current="page"` and disabled boundary controls. Match the authentication palette and focus styling; use text labels for unset fares and statuses rather than relying on color alone.

## Public flight search and authentication

Files: `index.php`, `includes/header.php`, `includes/navbar.php`, `login.php`, `register.php`, `assets/css/flight-search.css`
Last updated: 2026-11-06

| Property | Pattern |
|---|---|
| Page background | Soft neutral `--flight-canvas: #f5f5f8` with a restrained violet wash |
| Navigation | Compact charcoal bar, white airline wordmark and violet brand mark |
| Surface | Rounded white search panel, flight cards, and filter panel with subtle shadows |
| Border | Light `--flight-line: #e7e8ef`; focus uses violet ring |
| Text — primary | `--flight-ink: #171820` |
| Text — secondary | `--flight-muted: #707482` |
| Accent | `--flight-purple: #6549e8` |
| Spacing | Bootstrap responsive grid; generous page spacing with compact card internals |
| Typography | Bootstrap system stack; tight bold hero and route labels; uppercase tracked eyebrow |
| Icons | Font Awesome with decorative icons hidden from assistive technology where adjacent text exists |
| Authentication | Responsive split card with a deep violet travel panel and existing form flow unchanged |

**Pattern notes:**
Use a responsive origin/destination/date search card, then show result cards beside a supported-filter panel at desktop widths. Stack results and filters on small screens. Preserve visible labels and keyboard focus; present fare values in Nigerian naira (`₦`). Use route, time, and fare data from the database only—never imply unsupported inventory or booking actions.

### Passenger booking panel and flight cards

Files: `book-flight.php`, `views/flight-card.php`, `assets/css/flight-search.css`
Last updated: 2026-11-06

| Property | Pattern |
|---|---|
| Background | White card on `--flight-canvas` |
| Border | `1px solid var(--flight-line)` |
| Border radius | `1rem` panels; `0.65rem` booking action |
| Text — primary | `--flight-ink` |
| Text — secondary | `--flight-muted` and Bootstrap `text-secondary` |
| Spacing | Responsive Bootstrap grid; `clamp()` panel padding |
| Hover state | Dark flight action transitions to `--flight-purple` |
| Shadow | Soft card shadow |
| Accent usage | Violet for primary/focus actions; Nigerian naira (`₦`) for all fares |

**Pattern notes:**
Use the shared flight card for both upcoming flights and search results. Keep itinerary details, seat availability, per-traveler fare, and the clearly labeled booking action together. Booking panels stack into one column on mobile. Show semantic, escaped feedback and explicitly identify reservations as pending; never suggest payment or ticket confirmation.

### Multi-step booking and reservation ticket

Files: `book-flight.php`, `ticket.php`, `assets/css/flight-search.css`
Last updated: 2026-11-06

| Property | Pattern |
|---|---|
| Background | White booking/ticket panels on `--flight-canvas` |
| Border | `1px solid var(--flight-line)`; dashed violet booking reference |
| Border radius | `1rem` panels; `0.45rem` seat controls |
| Text — primary | `--flight-ink` |
| Text — secondary | `--flight-muted` and Bootstrap `text-secondary` |
| Spacing | Bootstrap responsive grid; `clamp()` panel padding |
| Hover/focus state | Violet border and visible `rgb(101 73 232 / 17%)` focus ring |
| Shadow | Soft booking-panel shadow; no shadow in print |
| Accent usage | Violet for current/completed steps and selected seats; pending amber, confirmed green, cancelled red; unavailable seats use neutral fill plus disabled/text state |

**Pattern notes:**
Use a labeled three-step indicator, keyboard-operable seat controls with visible text states, and a concise itinerary summary. On the ticket, show departure above arrival with a vertical connector and airplane marker. Keep panels stacked on mobile. Print output hides navigation and actions and removes decorative shadows. Reservation status and the no-payment/no-boarding-pass disclaimer must remain explicit in both screen and print layouts; color alone must never communicate seat state.

### Passenger dashboard and My Bookings

Files: `my-bookings.php`, `assets/css/flight-search.css`
Last updated: 2026-11-06

| Property | Pattern |
|---|---|
| Background | `--flight-canvas` with a restrained violet radial wash |
| Surface | White booking cards and summary panels with soft shadows |
| Border | `1px solid var(--flight-line)` |
| Border radius | `1rem` panels and cards; pill reservation status |
| Text — primary | `--flight-ink` |
| Text — secondary | `--flight-muted` |
| Accent | `--flight-purple`; pending amber, confirmed green, and cancelled red status badges; deep red text for the destructive cancel action |
| Spacing | Responsive Bootstrap grid and `clamp()` heading/card padding |
| Focus | Bootstrap visible focus treatment; text labels remain explicit |
| Shadow | Soft `0 0.45rem 1.5rem rgb(28 27 47 / 4–5%)` |
| Typography | Bootstrap system stack; tracked uppercase eyebrow and compact bold route labels |

**Pattern notes:**
Use a responsive summary row and itinerary cards with route, schedule, status, booking reference, traveler/seat details, and clearly labeled ticket actions. Stack summary panels and action buttons on narrow screens. Pending, confirmed, and cancelled status colors are amber, green, and red respectively, always paired with visible status text. Keep cancellation visually secondary but clearly destructive, and require a server-validated POST with CSRF protection.
