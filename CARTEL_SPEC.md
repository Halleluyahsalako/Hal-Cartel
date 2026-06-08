# Cartel — WordPress eCommerce Plugin Specification

> Paste this entire file into Claude, Kombai, or any coding agent. It is written as a self-contained build brief. The companion starter scaffold lives in `wp-plugin/cartel/` — extend it; do not start from scratch.

## 1. Vision & goal

Build **Cartel**: a free, lightweight, security-first WordPress eCommerce **core** plugin, plus an official **extension ecosystem** that together make it a complete, "godmode" alternative to WooCommerce *and* the entire paid-extension market around it (Shopify-grade capability, WordPress-native).

The trick that makes "lightweight" and "godmode" compatible: **capability lives in optional extensions, not in the core.** The core ships lean enough to pass WP.org review and feel instant; the ecosystem covers everything a serious store could ever need. See §3 for how this works architecturally.

It must:
- Run on stock WordPress (no required external services).
- Stay under ~250 KB of front-end JS/CSS total (compressed) for a typical store page — **core only**.
- Pass WordPress Plugin Check (PHPCS WordPress-Extra, no PHP notices on PHP 7.4–8.3) — required to be listed on WordPress.org.
- Import/export products in **WooCommerce CSV** format (column-compatible).
- Be intuitive enough that a non-technical store owner can launch in under 30 minutes — see §6 (Progressive disclosure).
- Give power users — developers, agencies, CMS experts — a flip-switch path to full control without ever feeling locked out.

## 2. Tech constraints

- PHP 7.4+ (use typed properties only where 7.4-safe).
- WordPress 6.2+. Use core APIs (`WP_Query`, `$wpdb`, REST API, Settings API, Cron, Transients, `wp_remote_*`).
- No Composer runtime dependencies for the core plugin (vendor only build-time).
- Front-end JS: vanilla ES2017, no framework, no jQuery.
- All AJAX via WP REST API (`/wp-json/hal/v1/...`) with nonces.
- All output escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- All input sanitized (`sanitize_text_field`, `sanitize_email`, `absint`, etc.) and validated.
- All DB writes via `$wpdb->prepare` or `$wpdb->insert/update` (no string concat SQL).
- CSRF: nonces on every state-changing request. Capability checks (`current_user_can`) on every admin action.
- All money in `DECIMAL(12,2)`; never floats in storage.
- i18n: every user-facing string wrapped in `__()` / `_e()` with text domain `cartel`.
- Block-editor support: ship native Gutenberg blocks (not just shortcodes) — see §20. WP.org reviewers and modern themes both expect this.

### 2.1 Naming convention — `Hal_Cartel` / `hal_cartel` / `HAL_CARTEL`

`Hal` is the developer's personal signature (Halleluyah) — it prefixes *every* identifier across *all* of their plugins, the same way Contact Form 7 stamps `WPCF7_` across its codebase. `Cartel` is this specific product's name. Together they form the reusable pattern **`Hal_<ProductName>_<Component>`** — so for this plugin (`<ProductName>` = `Cartel`) every technical identifier gets `Hal`/`hal`/`HAL`/`hal-` *prepended in front of* the existing `Cartel`/`cartel`/`CARTEL`/`cartel-` portion (both halves coexist; `hal` does not replace `cartel`).

Every PHP class, function, constant, hook (action/filter), database table, meta key, option key, cookie/transient key, REST namespace, shortcode tag, script/style handle, and CSS class follows this combined `hal_cartel` / `Hal_Cartel` / `HAL_CARTEL` / `hal-cartel` form:
`Hal_Cartel_Product`, `HAL_CARTEL_VERSION`, `hal_cartel_order_created`, `wp_hal_cartel_orders`, `_hal_cartel_sku`, `hal_cartel_currency`, `hal-cartel/v1`, `[hal_cartel_checkout]`, `.hal-cartel-product`, `HalCartelData` (JS global).

This is the **developer namespace** — it prevents collisions with other plugins (the `Hal` signature) and with other products by the same developer (the `Cartel` product name), and stays consistent if the `Hal_<ProductName>_<Component>` scaffold pattern is reused for future products (e.g. a future plugin "Foo" would use `Hal_Foo_*` / `hal_foo_*` / `HAL_FOO_*` / `hal-foo-*`).

**Exception — the plugin's public identity stays "Cartel"**: Plugin Name, plugin slug/folder (`cartel`), main bootstrap file (`cartel.php`), `@package` docblocks, admin menu *labels* (the text a store owner reads — "Cartel", "Orders", "Settings"...), readme.txt, and the **text domain** (`cartel`). Only the `Author:` field carries the developer's name (`Halleluyah`), not `Hal`/`Cartel`. The text domain in particular **must** keep matching the plugin slug — WordPress.org's translation platform (translate.wordpress.org) ties community translations to `slug === text-domain`; changing it breaks automatic-translation loading once listed.

Rule of thumb: if it's a *technical identifier* (even one a developer or store owner types or sees in a URL — menu slugs, shortcode tags, REST paths, nonce actions), it gets the full `hal-cartel` / `hal_cartel` treatment. If it's *displayed brand text* a shopper or store owner reads as the product name, it stays "Cartel". REST namespaces are the one technical identifier that's backend-queried rather than user-navigated, so they carry the full `hal-cartel/v1` signature without the "looks odd in a page URL" concern that applies to actual page slugs.

## 3. Architecture philosophy — "lean core, godmode ecosystem"

This is the central design decision; everything else follows from it.

**The tension**: "viable alternative to WooCommerce *and all its paid extensions*" sounds like it requires a huge, bloated plugin — which directly contradicts "free, lightweight, <250KB, launch in 30 minutes, not overwhelming for non-technical users."

**The resolution**: don't build one giant plugin. Build:
1. A **lean core** — catalog, cart, one-page checkout, orders, one payment gateway working end-to-end, basic shipping/tax, lightweight reports. This is what every store needs on day one, and it's what gets reviewed and listed on WP.org.
2. A documented **extension contract** (`Hal_Cartel_Extension` base class + the hooks/filters in §19) that lets capability bolt on cleanly: subscriptions, multi-vendor, abandoned-cart recovery, loyalty programs, affiliates, dynamic pricing, returns/RMA, bookings, POS, etc. — each its own small plugin sharing Cartel's admin shell, settings UI, and data model.
3. A **shared extension-admin shell**: one "Extensions" screen inside Cartel (not a separate WP menu) where official (and eventually third-party) extensions register themselves, expose their settings panels, and report their status — so the godmode experience feels like one cohesive product, not a pile of unrelated plugins.

This means: "godmode" = the *ecosystem* can do anything Shopify/WooCommerce+extensions can do. Any *individual install* stays exactly as light as the store owner needs it to be. It also gives you a sane long-term business model (free core, optional paid official extensions) without ever compromising the core's lightness or the WP.org listing.

**Practical rule for the build agent**: when a spec item below smells like a vertical (subscriptions, multi-vendor, loyalty, affiliates, bookings, POS, returns/RMA, abandoned-cart emails, wishlists...), it does **not** go in the core. It goes in §5 as a planned extension, and the only core-side work is making sure the relevant hook/filter/abstract exists so that extension can plug in without touching core code.

## 4. Progressive disclosure — "Simple" vs "Advanced" mode

Non-technical store owners and expert developers will use the *same* admin screens. Rather than maintaining two UIs, every screen reads one global toggle and shows/hides accordingly.

- **Setting**: `hal_cartel_ui_mode` — per-user (`user_meta`), values `simple` (default) or `advanced`. Switchable from the admin toolbar/profile screen at any time, no page architecture change.
- **Simple mode** shows only what's needed to run a basic store: title, images, price, short description, stock toggle, publish. Checkout shows contact/shipping/payment only.
- **Advanced mode** reveals everything else: SKU, tax class, shipping class, attributes/variations, downloadable files, cross-sells/upsells, meta boxes, raw hook/filter debug panel, REST request log, developer tools.
- **Implementation contract**: wrap any field/section that should be hidden in simple mode with `Hal_Cartel_UI::advanced( $callback )`. This renders the field but adds a `hal-cartel-advanced-field` class and a small `data-hal-cartel-advanced` attribute; a tiny core JS module toggles visibility client-side based on the user's stored mode (no reload, no duplicated markup, screen readers still get the content).
- This pattern is **mandatory** across: Product editor, Orders screen, Coupons, Shipping zones, Settings, Reports, and every official extension's settings panel (the shared shell in §3 enforces it).
- Inline help: every setting — in either mode — gets a one-sentence plain-language description. Advanced-only settings additionally get a "why would I change this" note.

## 5. Feature set — v1 core ship list

### 5.1 Catalog
- Product CPT `hal_cartel_product` (already scaffolded) with title, content, excerpt, featured image, gallery.
- Taxonomies: `hal_cartel_product_cat` (hierarchical), `hal_cartel_product_tag` (flat).
- Product types: **simple**, **variable** (with attributes + variations), **digital/downloadable**, **virtual**.
- Per-product fields: SKU, regular price, sale price (+ schedule), stock qty, manage-stock toggle, backorders, weight, dimensions, tax class, shipping class, downloadable files, cross-sells/upsells. (Advanced-mode fields per §4 — a newcomer publishing their first product sees ~6 fields, not 20.)
- "Add product" quick-start: a 3-field fast path (name, photo, price) that publishes a sellable simple product immediately; everything else is optional and editable later.

### 5.2 Cart & checkout
- Cookie-keyed cart (scaffolded). Persist 14 days. Merge on login.
- **One-page checkout** shortcode `[hal_cartel_checkout]` *and* a Gutenberg block equivalent: contact, shipping, billing, shipping-method, payment, review — all on one screen, AJAX-validated per field.
- **Same-page Buy Now** shortcode `[hal_cartel_buy_now id="123"]`: opens an inline drawer that performs add-to-cart + collects contact/shipping/payment + finalizes order without page reload.
- Coupons: percent / fixed cart / fixed product / free shipping; usage limits + expiry + product/category restrictions.
- Guest checkout (default on) + account creation toggle.
- Order-received page + customer order-history page.
- Cart/checkout fire `hal_cartel_cart_updated`, `hal_cartel_checkout_started`, `hal_cartel_checkout_abandoned` (idle timeout) — the hook surface that the (extension) abandoned-cart-recovery module needs; no recovery logic in core.

### 5.3 Orders & fulfilment
- Statuses: `pending`, `processing`, `on-hold`, `completed`, `cancelled`, `refunded`, `failed`. Status set is filterable (`hal_cartel_order_statuses`) so extensions like Returns/RMA can register `return-requested`, `returned`, etc.
- Admin order list (custom table, scaffolded) with filters by status/date/customer.
- Per-order edit screen: items, totals, notes, customer info, refund actions, shipping label trigger.
- Stock decremented on order create; restored on cancel/refund.
- Transactional emails (HTML + plain): new order (admin), order confirmation, processing, completed, refunded, customer invoice, password reset. Templates overridable from theme `/cartel/emails/`.
- Every status change, refund, and note-add writes one row to the audit log (§15) — this is core, not optional, because store owners need to know "who did what" from day one.

### 5.4 Payments (gateway interface)
Define `Hal_Cartel_Payment_Gateway` abstract: `id`, `title`, `supports`, `process_payment( $order_id )`, `webhook( $request )`.
Ship gateways:
- **Stripe** (Payment Intents + webhooks, 3DS, Apple/Google Pay via Stripe Elements).
- **PayPal** (Checkout REST v2 + webhooks).
- **Manual / Bank transfer**.
- **Cash on delivery**.

The abstract must expose enough surface (`supports( 'subscriptions' )`, `supports( 'refunds' )`, `supports( 'saved_cards' )`) that a future Subscriptions extension can ask "can this gateway do recurring charges?" without modifying core.

### 5.5 Shipping
Define `Hal_Cartel_Shipping_Method` abstract: `calculate( $package )`, `label_purchase( $order )`.
Ship methods:
- Flat rate (per item / per order / per weight).
- Free shipping (threshold + coupon-based).
- Local pickup.
- **API-driven**: EasyPost, Shippo, DHL Express, FedEx Web Services — rate quote at checkout + label purchase from order screen.

### 5.6 Taxes
- Manual rate tables (country/state/postcode) like WooCommerce.
- Inclusive/exclusive display.
- Per-product tax class.
- Pluggable rate provider interface (so TaxJar/Avalara can be added later as extensions).

### 5.7 Integrations
- **Google reCAPTCHA v3** on checkout + login + register + comment.
- **Mailchimp**, **Brevo (Sendinblue)**, **ConvertKit**, **MailerLite**: opt-in checkbox at checkout adds customer to a chosen list. Abstract `Hal_Cartel_Mailing_Provider`.
- **Webhooks out**: `order.created`, `order.updated`, `order.refunded` — HMAC signed.
- **Webhooks in**: payment gateway callbacks under `/wp-json/hal/v1/webhook/{gateway}`.
- Hook points only (no UI/logic in core) for: live-chat widgets, marketing-automation platforms, and review-syndication services — extensions attach here.
- **Currency display** — store-owner setting (`hal_cartel_currency_mode`), three options:
  - `off` (default) — single currency, no conversion.
  - `display` — shoppers see prices live-converted to their local currency (cached FX-rate API lookup, e.g. hourly transient cache); the store still **charges and settles in its base currency** — the gateway/bank performs actual conversion. No accounting/tax/refund complexity. Ships in core.
  - `charge` — actually charge and settle in the shopper's chosen currency. Gated behind `Hal_Cartel_Payment_Gateway::supports( 'multi_currency' )`, since it needs gateway-level multi-currency settlement plus currency-aware refunds, taxes, and reports. Far more complex than `display` — the setting UI shows it as available once the relevant extension/gateway is active; core ships the setting and the `off`/`display` modes only.

### 5.10 Editor & page-builder compatibility

A plugin this central to a site has to work wherever the site owner builds pages — not just for developers who can hand-code shortcodes.

- **Classic editor**: every customer-facing component ships as a shortcode (§5.2): `[hal_cartel_product]`, `[hal_cartel_buy_now]`, `[hal_cartel_checkout]`, plus cart/mini-cart equivalents.
- **Block editor**: native Gutenberg blocks mirroring the same set — see §12.
- **Elementor**: register a dedicated **"Cartel"** widget category in Elementor's panel (`elementor/elements/categories_registered`) containing widgets that mirror the shortcode/block set — Product, Product Grid, Buy Now, Add to Cart, Cart, Mini-Cart, Checkout — each with Elementor-native content/style/advanced tabs, so a non-technical owner can drag-and-drop a working store onto any page. Register and enqueue **only** when Elementor is active (`did_action( 'elementor/loaded' )`); never load Elementor integration code on sites that don't have it — the performance budget (§14) still applies.
- Where reasonably possible, extend the same parity to other major builders (Bricks, Beaver Builder, Divi) as later extensions — Elementor ships first because of install-base size.

### 5.8 Reports & analytics (lightweight)
- Sales by day/week/month, top products, low-stock list, refunds, **cart-abandonment rate**, **repeat-purchase rate / customer lifetime value**. Pure SQL aggregations — no chart library larger than 30 KB (use uPlot or hand-rolled SVG).
- A `hal_cartel_report_widgets` filter so extensions can add their own dashboard cards (e.g., a Loyalty extension adding "points redeemed this month") without forking the Reports screen.

### 5.9 WooCommerce compatibility layer
- **CSV product import/export** with identical column headers to WooCommerce's "Tools → Export Products" (scaffolded — extend with variations, attributes, gallery images, downloadable files, categories, tags).
- **One-shot WooCommerce migrator**: read `wp_posts` where `post_type = product`, copy to `hal_cartel_product`; migrate `wp_woocommerce_order_items*` into Cartel order tables; map statuses; map coupons; copy meta keys (`_regular_price`, `_sale_price`, `_sku`, `_stock`, `_manage_stock`, `_weight`, `_downloadable_files`).
- Optional **shortcode aliases**: `[products]`, `[add_to_cart]`, `[woocommerce_checkout]` map to Cartel equivalents so existing pages keep working.

## 6. Trust, compliance & control — the "godmode dashboard"

Godmode isn't just feature breadth — it's the store owner *seeing and controlling everything*. This is core, because trust has to exist before extensions matter.

- **Audit log**: `wp_hal_cartel_audit_log` table (actor, action, object type/id, before/after snapshot, timestamp, IP). Every order status change, refund, setting change, coupon edit, and user-role change writes a row. Viewer screen with filters; exportable to CSV.
- **Granular roles**: register capabilities beyond `manage_options` — e.g. `hal_cartel_manage_orders`, `hal_cartel_manage_products`, `hal_cartel_fulfill_orders`, `hal_cartel_view_reports` — and ship sensible role presets (Store Manager, Fulfillment Staff, Support Agent) so an owner can delegate without handing out admin access.
- **System Status screen** (mirrors WooCommerce's): PHP/WP/MySQL versions, active theme, conflicting plugins, REST API reachability, cron health, template-override list, recent fatal errors. This is what turns a 20-minute support ticket into a 2-minute one, and reviewers/users both expect it.
- **GDPR / privacy tools**: register Cartel's customer data with WordPress's core Personal Data Exporter/Eraser so "Tools → Export/Erase Personal Data" requests include order history, addresses, and saved payment references (never raw card data). Cookie-consent hook points (`hal_cartel_before_tracking_script`) so consent plugins can gate analytics/marketing scripts cleanly.

## 7. Extension roadmap — planned "godmode" modules

These are **not built in core v1**. They are designed for: each one only needs the hook/filter/abstract surface described to plug in cleanly as its own small official plugin later. Listing them here so the core's extension points are designed with them in mind from day one — this is what separates "godmode" from "feature creep."

| Extension | What it replaces (paid WC add-ons) | Plugs into |
|---|---|---|
| Abandoned-cart recovery | CartFlows, Abandoned Cart Pro | `hal_cartel_checkout_abandoned`, `Hal_Cartel_Mailing_Provider` |
| Wishlists | TI WooCommerce Wishlist | `hal_cartel_cart_item_price` filter family, customer-account screens |
| Reviews & Q&A+ (photos, verified-buyer badges) | Advanced Reviews, Photo Reviews | WP core comments + `hal_cartel_product_query_args` |
| Dynamic pricing / bulk & tiered discounts | Dynamic Pricing, Discount Rules | `hal_cartel_cart_item_price` filter |
| Multi-currency display | Currency Switcher | `hal_cartel_cart_item_price`, REST `currency` param |
| Loyalty points & rewards | Points and Rewards | `hal_cartel_order_created`, `hal_cartel_payment_complete` actions |
| Affiliate program | AffiliateWP | `hal_cartel_order_created` action, REST `hal/v1/affiliate/*` namespace |
| Subscriptions & recurring billing | WooCommerce Subscriptions | `Hal_Cartel_Payment_Gateway::supports('subscriptions')`, `hal_cartel_order_statuses` filter |
| Multi-vendor marketplace | Dokan, WC Vendors | `hal_cartel_product_query_args`, order-splitting hook on `hal_cartel_order_created` |
| Returns / RMA workflow | Return Refund and Exchange | `hal_cartel_order_statuses` filter, `hal_cartel_refund_processed` action |
| Bookings / appointments | WooCommerce Bookings | new CPT + `hal_cartel_cart_item_price` filter |
| POS | Point of Sale for WooCommerce | REST `hal/v1/pos/*` namespace, `hal_cartel_order_created` |

If a future feature isn't on this list and isn't core-critical, default to "designed as an extension," not "added to core."

## 8. Architecture — folder layout

```
cartel/
  cartel.php                 # bootstrap (define constants, requires, hooks)
  includes/
    class-hal-cartel.php     # main singleton
    class-hal-cartel-install.php    # activation/dbDelta
    class-hal-cartel-product.php
    class-hal-cartel-cart.php
    class-hal-cartel-order.php
    class-hal-cartel-rest.php
    class-hal-cartel-shortcodes.php
    class-hal-cartel-wc-compat.php
    class-hal-cartel-ui.php             # progressive-disclosure helpers (Hal_Cartel_UI::advanced(...))
    class-hal-cartel-audit-log.php
    class-hal-cartel-extensions.php     # extension registry + shared admin shell
    payments/                # gateways
    shipping/                # methods
    mailing/                 # providers
    emails/                  # mailers
    abstracts/               # base classes (Hal_Cartel_Payment_Gateway, Hal_Cartel_Shipping_Method,
                             #   Hal_Cartel_Mailing_Provider, Hal_Cartel_Extension)
  blocks/                    # Gutenberg blocks (build output + block.json per block)
    product-grid/
    cart/
    checkout/
  integrations/
    elementor/               # widget category + widgets, loaded only if Elementor is active
  admin/
    class-hal-cartel-admin.php      # menus + settings
    views/                   # admin templates
  public/
    assets/hal-cartel.css
    assets/hal-cartel.js
    assets/checkout.js       # one-page checkout
  templates/                 # theme-overridable
    checkout.php
    cart.php
    emails/*.php
  languages/
  readme.txt
```

## 9. Database (custom tables)

Already scaffolded: `wp_hal_cartel_orders`, `wp_hal_cartel_order_items`.
Add in v1: `wp_hal_cartel_order_notes`, `wp_hal_cartel_order_meta`, `wp_hal_cartel_coupons`, `wp_hal_cartel_coupon_usage`, `wp_hal_cartel_tax_rates`, `wp_hal_cartel_shipping_zones`, `wp_hal_cartel_downloads_log`, `wp_hal_cartel_audit_log`.
Products and variations stay as CPT + post meta for theme compatibility.

## 10. REST endpoints (namespace `hal/v1`)

Already scaffolded: `GET /cart`, `POST /cart/add`, `POST /cart/remove`, `POST /checkout`.
Add: `POST /cart/update`, `POST /coupon/apply`, `POST /shipping/quote`, `POST /tax/quote`, `POST /webhook/{gateway}`, admin-scoped: `GET /admin/orders`, `POST /admin/orders/{id}/refund`, `POST /admin/orders/{id}/note`, `POST /admin/orders/{id}/fulfill`, `GET /admin/audit-log`, `GET /admin/system-status`, `GET /admin/extensions`.

## 11. Hooks & extension contract (the godmode mechanism)

This is the single most important section for "godmode without bloat" — it's the seam the entire ecosystem hangs off.

**Actions**: `hal_cartel_order_created`, `hal_cartel_order_status_changed`, `hal_cartel_payment_complete`, `hal_cartel_refund_processed`, `hal_cartel_low_stock`, `hal_cartel_checkout_validation`, `hal_cartel_checkout_abandoned`, `hal_cartel_extension_activated`, `hal_cartel_extension_deactivated`.

**Filters**: `hal_cartel_cart_item_price`, `hal_cartel_shipping_methods`, `hal_cartel_payment_gateways`, `hal_cartel_email_recipients`, `hal_cartel_product_query_args`, `hal_cartel_order_statuses`, `hal_cartel_report_widgets`, `hal_cartel_settings_tabs`, `hal_cartel_admin_menu_items`.

**`Hal_Cartel_Extension` abstract** — every official (and third-party) extension registers one subclass:
```php
abstract class Hal_Cartel_Extension {
    abstract public function id(): string;
    abstract public function title(): string;
    abstract public function description(): string;
    public function settings_schema(): array { return array(); }   // rendered by the shared shell, respects §4 progressive disclosure
    public function on_activate(): void {}
    public function on_deactivate(): void {}
}
```
Registering via `Hal_Cartel_Extensions::register( new My_Extension() )` gets the extension: a card on the shared "Extensions" screen, an auto-generated settings panel (via `settings_schema()`), activation/deactivation lifecycle hooks, and an entry in the System Status report. This is what makes third-party and official extensions feel like *one product* instead of a pile of unrelated plugins — and it's the difference between "bloated core" and "godmode ecosystem."

Document this contract in full in `/docs/extensions.md` shipped with the plugin — third-party developers are part of the godmode story.

**Malleability rule** (applies everywhere, not just to extensions): nothing in Cartel should be a dead end. Every list, price calculation, query, template, email, and admin screen exposes a filter or action so behavior can be changed without editing core files or maintaining a fork. While building any feature, ask "how would a developer reasonably want to override this default?" — and expose exactly that as a hook. A feature that can only be used the way core ships it isn't godmode; it's just another opinionated plugin.

## 12. Gutenberg blocks

Ship native blocks (not just shortcode wrappers) for the highest-traffic surfaces:
- `hal/product-grid` — replaces `[products]`, with inspector controls for category/tag/count/columns.
- `hal/cart` and `hal/mini-cart`.
- `hal/checkout` — block version of `[hal_cartel_checkout]`.
- `hal/buy-now` — block version of `[hal_cartel_buy_now]`.

Each block ships a `block.json`, server-side render where it touches dynamic data (cart contents, prices), and respects the same ≤250 KB budget — lazy-register block assets only on pages that use them (`should_load_block_assets` pattern), don't enqueue globally.

## 13. Security checklist (must pass before "done")
- Every REST route checks nonce or signature.
- Every admin action calls `current_user_can()` against the appropriate Hal capability (§6), not a blanket `manage_options`.
- All `$_GET`/`$_POST`/`$_FILES` access is sanitized + validated.
- Webhooks verify HMAC / provider signature (Stripe `Stripe-Signature`, PayPal cert, etc.).
- Output escaped at the point of output.
- Downloadable files served via signed, time-limited URLs (not direct uploads paths).
- File uploads (CSV import) checked with `wp_check_filetype_and_ext` and stored outside `uploads/` or via WP's `wp_handle_upload`.
- Rate-limit checkout endpoint (transient counter per IP).
- No `eval`, no `extract`, no `unserialize` of user input.
- Honor `DISALLOW_FILE_MODS`. No remote code execution paths.
- Audit log itself is append-only at the application layer (no UI path to edit/delete entries, only export).

## 14. Performance budget
- Homepage with 12 products: ≤ 50 KB JS, ≤ 20 KB CSS over the wire.
- Checkout page: ≤ 80 KB JS total including gateway SDK loaders.
- No webfonts shipped by the plugin.
- Lazy-load gateway JS (Stripe.js, PayPal SDK) only on checkout.
- Lazy-register Gutenberg block assets only on pages that use them.
- Cache product queries with object cache (`wp_cache_*`) keyed by query hash.
- All admin list tables paginated (default 20).
- Extensions are independently enqueued — activating one must never inflate another page's payload.

## 15. Admin UX
- Single top-level "Cartel" menu (scaffolded). Submenus: Dashboard, Orders, Products (links to CPT), Coupons, Customers, Reports, Extensions, Import/Export, Settings, System Status.
- **Setup wizard** on first activation: store country, currency, weight/dimension units, payment gateway toggle, shipping zone, **one-click sample-store import** (demo products/categories/a coupon, so a new owner sees a working store before they've created anything — removable with one click).
- UI-mode switcher (Simple/Advanced, §4) always visible in the admin toolbar.
- Inline help — every setting has a one-sentence description; advanced-only settings get a "why would I change this" note.

## 16. Build/dev workflow for the agent
1. Read every file in `wp-plugin/cartel/` first — do not duplicate scaffolded code.
2. Implement in this order: progressive-disclosure helper (`Hal_Cartel_UI`) → variations → coupons → shipping zones → Stripe gateway → emails → audit log → reports → System Status screen → extension registry/shared shell (`Hal_Cartel_Extensions`) → Gutenberg blocks → Mailchimp → reCAPTCHA → WC migrator → variations in CSV → setup wizard + sample-store import.
   (Rationale: `Hal_Cartel_UI` and `Hal_Cartel_Extensions` are *infrastructure* that every later feature should be built on top of — build them early so nothing has to be retrofitted.)
3. After every feature add: run `phpcs --standard=WordPress-Extra` and fix all errors before moving on.
4. Write PHPUnit tests under `tests/` for: cart math, order creation, coupon application, stock decrement, CSV round-trip, audit-log writes, extension registration lifecycle.
5. Tag releases as `0.x.y` in `cartel.php` header AND `readme.txt` Stable tag.

## 17. Out of scope for v1 core (deliberately — see §7 for where they go)
- Subscriptions / recurring billing.
- Multi-vendor.
- Bookings / appointments.
- POS.
- Native mobile apps.

These are **not** "maybe later, unplanned" — they're the first wave of the official extension ecosystem (§7), and the core's abstracts/hooks are designed *now* so they plug in cleanly *then*. That's what makes the "godmode alternative to WooCommerce and all its extensions" promise true without making the core heavy.

## 18. Done definition
- Fresh WordPress install + activate Cartel + run setup wizard (incl. sample-store import) → publish a product in Simple mode (3-field fast path) → checkout end-to-end with Stripe test mode → order appears in admin with an audit-log entry → confirmation email sent → CSV export round-trips back through import with zero data loss → switching to Advanced mode reveals the full field set without losing data → registering a trivial sample `Hal_Cartel_Extension` makes it appear in the Extensions screen with a working settings panel.

If all of that works on a clean install, v1 is done.
