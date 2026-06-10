=== Cartel ===
Contributors: cartel
Tags: ecommerce, woocommerce alternative, shop, checkout, one-page checkout, buy now
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight, security-conscious eCommerce for WordPress. A free alternative to WooCommerce and its paid extensions.

== Description ==
Cartel is a fast, no-bloat commerce plugin: one-page checkout, instant Buy Now, payment processing (Stripe + Manual/Offline, with a pluggable gateway architecture), order status workflow, digital-download fulfillment with signed expiring links, order-confirmation and status-update emails, multi-currency pricing, per-zone shipping, customer accounts with order history (plus guest order lookup), reCAPTCHA + Mailchimp integrations, stock + order management, and WooCommerce-compatible CSV import/export.

== Installation ==
1. Upload the `cartel` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu.
3. Visit Cartel → Payment methods to activate and configure a payment gateway (Manual/Offline works out of the box; add your Stripe keys for card payments).
4. Visit Cartel → Settings to configure currency, shipping zones, emails, captcha, and mailing-list integrations.

== Changelog ==
= 0.2.3 =
* Storefront UI: fixed the checkout layout squeezing into 184px columns under themes that constrain the checkout to a narrow content area, by switching the 3-column grid to a container query keyed to the checkout's own rendered width. Fixed "Add to cart", "Buy now", and "Place order" buttons rendering with the host theme's default crimson/outline button style instead of the design system's solid indigo/amber, by scoping those button selectors to their containing component for higher specificity than the theme's `[type="button"]`/`[type="submit"]` resets.
* Orders: the admin order-detail "Billing" section now falls back to the checkout's shipping address when no separate billing address was collected, instead of always showing "—".

= 0.2.1 =
* Storefront UI: redesigned `public/assets/hal-cartel.css` with a card-based layout and a colour system aligned with the admin's design language — product cards, buttons, checkout sections, shipping/payment selection rows, the Stripe card field, the order-confirmation screen, and order-history all restyled. Order-status badges (`[hal_cartel_my_orders]`) now share the same badge styling as the admin order list.

= 0.2.0 =
* Payments: pluggable gateway architecture (`Hal_Cartel_Gateway`/`Hal_Cartel_Gateways`), a Manual/Offline gateway active by default, and a Stripe gateway with PaymentIntents, signed + idempotent webhook handling, and an abandoned-`pending-payment`-order cleanup cron that restocks and fails stale orders.
* Orders: full status workflow (pending payment → on hold/processing → completed/cancelled/refunded/failed) with an admin order-detail view and status-change action.
* Fulfillment: digital downloads are granted as signed, expiring tokens when an order completes, redeemable through a public account-free streaming endpoint with download-count and expiry enforcement.
* Emails: order-received, new-order-alert, and status-update notifications via `Hal_Cartel_Emails`, with admin-configurable recipient address and per-type on/off toggles, and download links appended automatically for completed digital-goods orders.
* Integrations: reCAPTCHA v2 verification and Mailchimp list opt-in are now wired into checkout (previously placeholder-only settings).
* Accounts: opt-in customer-account creation at checkout, an order-history `[hal_cartel_my_orders]` shortcode for logged-in shoppers, and a guest order-number + email lookup for account-free order tracking and re-downloads.
* Multi-currency pricing and per-zone shipping rates, carried through checkout, order totals, and admin order views.

= 0.1.0 =
* Initial scaffold: product CPT, cart, REST API, one-page checkout shortcode, admin UI, WooCommerce-compatible CSV import/export.