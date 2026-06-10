# Frontend/Backend handoff — Cartel storefront UI

Shared scratchpad so Kombai (visual/design) and Claude Code (logic/data/backend)
can work on the same plugin without the user relaying every detail.
Both agents: read this before starting, append to the **Handoff log** when you
change something the other agent needs to know about.

## Division of work

- **Kombai owns (visual/markup)**:
  - `public/assets/hal-cartel.css`
  - `public/assets/hal-cartel.js` — visual-only bits (DOM structure it builds,
    classes it adds/removes, layout). Do not change `fetch`/`api()` calls,
    REST payload shapes, or business logic.
  - `templates/*.php` — markup structure and CSS classes only. Do **not**
    touch PHP logic: `<?php ... ?>` blocks that compute values, `esc_*()`
    calls, conditionals (`if ($cart_needs_shipping)`, `foreach`), shortcode
    registration, or variable names passed in from PHP. If a redesign needs a
    new wrapper `<div>` or extra markup around an existing `<?php echo ... ?>`,
    that's fine — just don't change what's inside the PHP tags.

- **Claude Code owns (logic/data)**:
  - `includes/`, `admin/`, REST endpoints (`class-hal-cartel-rest.php`), DB
    schema, gateways, emails, downloads, smoke tests.
  - Anything that changes what *data* a template receives, or what shape JSON
    responses have.

## Live preview (LocalWP sandbox)

Base URL: `http://hal-cartel-plugin.local/`

- `/checkout/` — one-page checkout (`templates/checkout.php`)
- `/hal-product-trial/` — simple product page
- `/product/hal-variable-products/` — variable product (attribute pickers)
- `/my-orders/` — order history / guest lookup (`templates/my-orders.php`,
  `[hal_cartel_my_orders]` shortcode). Logged-out shows the "Look up your
  order" form; logged-in shows the order list with status badges.
- Admin: Cartel → Orders (`admin.php?page=hal-cartel-orders`) and the
  per-order detail view (`...&action=view&order={id}`, numeric DB id, not
  the order number).

Edits to files in this repo apply immediately (the plugin is symlinked into
the sandbox) — just refresh the browser. If CSS/JS changes don't seem to
apply, hard-refresh (Ctrl+Shift+R) — the assets are cache-busted via
`HAL_CARTEL_VERSION` in `cartel.php` (currently `0.2.3`). If you change
`hal-cartel.css`/`hal-cartel.js` again, bump that version string so the
browser doesn't serve a stale cached copy.

## Existing design system — build on it, don't replace it

`public/assets/hal-cartel.css` defines CSS custom properties at `:root`
(`--hal-cartel-accent`, `--hal-cartel-surface`, `--hal-cartel-border`,
`--hal-cartel-radius`, etc.) — an "Indigo & Slate" palette shared with
`admin/assets/hal-cartel-admin.css`. Reuse these tokens (or add new ones to
`:root` if a new color/spacing value is needed) rather than hardcoding new
colors, so admin and storefront stay visually consistent.

## Existing class naming convention

BEM-ish: `.hal-cartel-<component>__<part>--<modifier>`, e.g.
`.hal-cartel-checkout__shipping-rate`, `.hal-cartel-badge--success`.

`hal-cartel.js` selects elements by these classes/attributes (e.g.
`[data-hal-cartel-payment-methods]`, `.hal-cartel-checkout__shipping-rate`,
`.hal-cartel-checkout__card-element`). **If you rename/restructure a class
that JS depends on, list it in the Handoff log below** so Claude Code can
update `hal-cartel.js` to match.

## Handoff log

(Most recent first. One line per change that affects the other agent.)

- 2026-06-10 (Claude Code): Full Playwright visual QA pass over every
  storefront/admin screen (checkout, product pages, confirmation, admin
  order list/detail, `/my-orders/`). Found and fixed two real rendering
  bugs in `hal-cartel.css`: (1) the checkout's 3-column grid was using a
  viewport `@media` query, but Hello Elementor constrains
  `.hal-cartel-checkout` to ~600px regardless of viewport — replaced with a
  **container query** (`container-type: inline-size` on
  `.hal-cartel-checkout`, `@container hal-cartel-checkout (max-width: ...)`).
  (2) "Add to cart", "Buy now", "Buy now (inline)", and "Place order"/"Find
  my order" buttons were rendering in the *theme's* default crimson/outline
  button style, not the design system's solid indigo/amber — root cause:
  Hello Elementor's `reset.css` selector `[type="button"], [type="submit"],
  button { ... }` has the **same specificity** (0,1,0) as a single class
  selector and loads after `hal-cartel.css`, so it won the cascade tie on
  source order. Fixed by scoping each button selector to its containing
  component (`.hal-cartel-product .hal-cartel-add-to-cart`,
  `.hal-cartel-checkout .hal-cartel-place-order`,
  `.hal-cartel-order-lookup .hal-cartel-place-order`, etc. — specificity
  (0,2,0)) instead of using `!important`. **Lesson for future CSS here**:
  any bare `.hal-cartel-*` class applied to a `<button>` or
  `input[type="button"|"submit"]` needs at least two selector components to
  reliably beat theme resets.
  Also fixed (in `includes/class-hal-cartel-order.php`, not CSS): the admin
  order-detail "Billing" section always showed "—" because checkout only
  collects one address; `Order::create()` now defaults `billing` to the
  shipping address when no separate billing address is supplied.
  Created `/my-orders/` page (see Live preview list above). Bumped version
  to `0.2.3`.
  **Known non-blocking issues, left for Kombai's pass**: plain text
  inputs (email/shipping fields, order-lookup fields) still render with the
  theme's default `1px solid #666` / `3px` radius instead of the design
  system's `#E2E8F0` / `8px` — needs the same specificity treatment as the
  buttons above, scoped per input rather than globally.
  **Data issue (not a template/CSS bug)**: shipping method titles in the DB
  are truncated (e.g. "Weight Based Shippin", "Frshipping", "Flat Rate
  Shi") — likely bad seed data in Cartel → Shipping zones; needs manual
  cleanup in wp-admin, not a code fix.

- 2026-06-10 (Claude Code): Rewrote `hal-cartel.css` with the design-system
  tokens above; refactored `renderConfirmation()` in `hal-cartel.js` to use
  CSS classes instead of inline styles (`.hal-cartel-confirmation__*`); added
  `.hal-cartel-badge--*` classes (used by `templates/my-orders.php`). Bumped
  version to 0.2.1. Pending: real visual QA — over to Kombai/the user.
