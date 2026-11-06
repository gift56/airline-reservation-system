# Reservation Status Colors and Vertical Ticket Itinerary

## Goal

Improve reservation status clarity with consistent colors for every booking status and redesign the printable ticket itinerary as a vertical origin-to-destination timeline.

## Skills read

- `.agents/skills/bootstrap-components` — accessible status badges and responsive UI patterns.
- `.agents/skills/architect` — preserve existing page responsibilities and avoid unrelated changes.

## Existing code inspected

- `AGENTS.md` — prompt approval workflow, UI requirements, security and verification expectations.
- `prompts/13-user-dashboard.md` — passenger booking dashboard, ticket actions, reservation status context.
- `my-bookings.php` and `assets/css/flight-search.css` — existing pending/confirmed dashboard status badge styles and responsive card layout.
- `book-flight.php` — reservation confirmation status presentation and itinerary summary.
- `ticket.php` — printable ticket status and horizontal origin/destination route structure.
- `ui-registry.md` — existing reservation and dashboard visual patterns.
- User screenshots showing the reservation confirmation, ticket page, and My Bookings page.

## Decisions and assumptions

- Apply colors to every existing booking status: `pending` (amber), `confirmed` (green), and `cancelled` (red). Keep the visible status text so meaning does not depend on color.
- Reuse the same status color mapping on the My Bookings page, booking confirmation, and printable ticket.
- Interpret “vertical lining” as a vertical itinerary timeline on the ticket, with departure above arrival and a visible connecting line/airplane marker between them. The timeline is part of the itinerary, not a perforation dividing ticket sections.
- Keep ticket data, wording, route, schedule, fare, owner authorization, and print/PDF behavior unchanged. No database or booking-flow logic changes are needed.

## Files likely to change

- `assets/css/flight-search.css` — complete status variants and vertical ticket itinerary styles.
- `my-bookings.php` — safe status class rendering for the existing booking states, if required for consistent status presentation.
- `book-flight.php` — apply the pending status badge treatment on the confirmation page.
- `ticket.php` — apply the shared booking status class and adjust itinerary markup for a vertical timeline.
- `ui-registry.md` — document the full status palette and vertical ticket route pattern.

## Implementation requirements

### Reservation status colors

- Define consistent text-plus-color styles for all booking status enum values:
  - `pending`: amber background/border with dark amber text.
  - `confirmed`: green background/border with dark green text.
  - `cancelled`: red background/border with dark red text.
- Apply status styles to the booking card badge, booking confirmation status, and ticket status block. Ensure unknown values do not produce unsafe CSS class names; use a constrained mapping or known enum branches.
- Preserve visible status labels, sufficient contrast, and existing status wording. Do not imply payment or boarding authorization for any status.

### Vertical ticket itinerary

- Change the itinerary in `ticket.php` from a horizontal route row to a vertical timeline: departure airport and schedule first, a vertical connector with an airplane marker, then arrival airport and schedule.
- Use semantic markup and keep the flight number visible. Decorative line/icon elements must be hidden from assistive technology where appropriate.
- Keep the vertical route readable on desktop, mobile, and print without horizontal overflow. Preserve the existing ticket sections, ticket summary, and print rules.
- Do not alter the separate My Bookings card’s horizontal route presentation unless needed to preserve styling consistency; the vertical layout request applies to the printable ticket.

### Visual and accessibility requirements

- Reuse `--flight-ink`, `--flight-muted`, `--flight-line`, and `--flight-purple` where suitable; use restrained status colors consistent across all three surfaces.
- Retain the existing rounded white ticket surface and print-friendly no-shadow layout.
- Status must remain understandable in monochrome and through screen readers via visible text.
- Maintain responsive Bootstrap-compatible spacing, readable route hierarchy, and visible keyboard focus for any affected controls.

## Security requirements

- Do not change authorization, session handling, booking ownership, cancellation, or ticket data access.
- Continue escaping dynamic ticket and status output with `app_escape()`.
- Do not place raw database status values into CSS class names without mapping them to the known supported values.

## Acceptance criteria

- Pending, confirmed, and cancelled reservations have consistent, distinct, readable status colors on the dashboard, confirmation page, and ticket page.
- Status wording remains visible and understandable without color.
- The printable ticket shows the origin and departure information above the destination and arrival information, connected by a vertical itinerary line and airplane marker.
- The ticket remains readable on screen and in print and does not introduce horizontal overflow.
- Existing reservation content, ticket access protections, and payment/boarding disclaimers remain unchanged.
- No schema, model, booking-flow, or dependency changes are introduced.
- `ui-registry.md` reflects the status colors and vertical ticket route.

## Checks to run

- Run `php -l` on all changed PHP files and `git diff --check`.
- Review all status output locations to verify the shared mapping and no unsafe dynamic class rendering.
- Use the browser or local XAMPP to view pending/confirmed/cancelled status samples on the dashboard, confirmation, and ticket.
- Check desktop/mobile ticket layout and print preview to confirm vertical route alignment, readable styles, and no clipped content.
- Check available Apache/PHP logs for new warnings or undefined variables.

## Manual test steps

1. Open a passenger dashboard containing a pending reservation and verify the amber status badge and unchanged visible text.
2. View a confirmed booking, if available, and verify its green status styling.
3. Open a canceled reservation ticket, if available, and verify its red status styling and canceled wording.
4. Complete a reservation through the existing flow and verify the pending color appears on the confirmation page.
5. Open the printable ticket and verify departure appears above arrival with the vertical connector and airplane marker.
6. Check the ticket at desktop and mobile widths, then open print preview and confirm the vertical timeline remains clear and the navbar/actions remain hidden.
