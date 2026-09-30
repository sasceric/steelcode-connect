# Implementation status

Last updated: 30 July 2026

SteelCode Connect is a multi-tenant catalogue and commerce-integration application. This document records what is implemented in the current codebase and separates it from planned work.

## Technical foundation

| Area | Current state |
| --- | --- |
| Frontend | Nuxt 4, Vue 3, TypeScript, Nuxt UI and Tailwind, based on the Nuxt UI Dashboard template. |
| Backend | Symfony 7.4, Doctrine ORM, PostgreSQL, Twig, Dompdf, Messenger and Scheduler. |
| Tenancy | Tenant-owned catalogue, company, billing and integration records. Access is resolved through tenant membership. |
| Authentication | Session-cookie login, sign-up, logout, current-user restoration, password reset and password change. |
| Localisation | Bosnian is the primary UI language; English and German are available. Product, category, manufacturer, property, custom-field and Shop-reference translations are stored per locale. |
| User feedback | Frontend uses title/message toasts. Backend-owned messages are translated through Symfony where applicable. |
| Branding | Light/dark SteelCode Connect logos, email templates, invoice template, favicon/PWA assets and a reusable translation selector with country flags. |

## Implemented modules

### Account, company and billing

- User profile fields: name, phone, title, avatar, active state and locale.
- Company details, reusable addresses and a default address.
- Payment-method management.
- Subscription plans: Starter (100 KM), Growth (200 KM) and Enterprise (300 KM).
- Monthly and annual billing. Annual subscriptions are charged in advance with a 10% discount.
- Invoice generation command and scheduled billing support. Invoice numbers reset annually in the `YEAR-001` format.
- Branded HTML email templates, including password recovery, delivered through Brevo SMTP configuration.
- PDF invoices rendered from `backend/templates/documents/invoice.html.twig`, including issuer, customer, VAT and bank details.

### Integrations

- Integrations module for Shopware, WooCommerce, OLX and Ananas connection records.
- Connector configuration forms, encrypted credentials, explicit connection testing, activation/deactivation and removal.
- Installed-integration list with logo, activation state and installation date.
- Shopware product import runs execute through Doctrine Messenger with a persisted queue/running/completed/failed status and live UI progress.
- Canonical Shopware product and category SEO routes are imported from `seo_url` with their original locale and sales-channel scopes; the first canonical Shopware route is also retained as the catalogue-global URL.
- The Shopware importer safely creates or updates products by external ID, SKU or EAN; imports all enabled matching locales, parent/child structure, reference data, media, tags, units, sales-channel visibility, regular and rule/tier prices, downloads, cross-selling, and source mappings for idempotency.

Ananas remains intentionally deferred until partner/API access is available. Connector configuration does not imply that every import or publishing capability is production-complete; see the remaining work below.

### Catalogue

- Product list and a Shopware-inspired product editor with a persistent shared detail header.
- Parent/child product variants: child products use `parent_id`, inherit parent data on generation and receive generated product numbers/SKUs.
- Product translations, HTML/WYSIWYG descriptions, locale-aware canonical SEO URLs with 301 redirect history, tags, labels, release date, visibility, category assignment and sales-channel publication data.
- Regular gross/net pricing with tax calculation and price linking.
- Advanced pricing with rule sets, quantity min/max tiers, regular/list/lowest-30-day price pairs, linked gross/net calculation and row/rule deletion.
- Gallery with uploads, stored media metadata, order changes, cover selection, individual deletion and reusable media picker/selection components.
- Product properties, property groups, colour/image/text/dropdown display settings and variant-option generation.
- Custom-field sets and custom fields, including typed field configuration, translated labels/options and uncategorised values for imported WooCommerce metadata.
- Product categories: nested tree, drag/drop movement, add child/before/after actions, translations, SEO, media, product assignment and category custom fields.
- Manufacturers: translated detail records, SEO, media, custom fields, product assignment and product-editor selection.
- Shop references: units, taxes and delivery times, including translations and locale-aware lists.
- Inventory: per-warehouse balances/movements, transfers, stock counts, suppliers/offers, purchase orders/receipts/damage handling, replenishment and operational supplier invoice matching.
- Sales: Shopware customer/address and order snapshots, historical imports, ongoing polling, reservations, recorded picking, partial shipment reconciliation, physical returns and exceptions. Connect-owned Shopware stock publication passed the disposable-shop cutover. See the [Inventory and Sales operating guide](inventory-and-sales-guide.md) for procedures, acceptance evidence and release limitations.
- Shared currency and locale registries. New connector currencies can be registered when valid ISO data is available.

## Key data-model decisions

- The internal catalogue is canonical; Shopware, WooCommerce, OLX and other connectors translate into or out of it.
- Variants are products, not a separate product-variant entity. The distinction is `products.parent_id`.
- Descriptive properties and variant-generating options share property-group/property definitions; their assignments determine the role.
- Media is stored in the central `media` table. Relation tables such as `product_media` only describe ownership, position and cover usage.
- Product/category/manufacturer and shop-reference content is translated in dedicated translation tables. The tenant default snippet locale is always the first required translation on create.
- SEO paths are a dedicated `seo_urls` resource, not translation-table columns. Canonical paths are unique per tenant, locale and optional sales channel; replaced paths remain as 301 redirects.
- Product price data supports linked gross/net values and structured advanced-price tiers. Connector adapters are responsible for capability validation and conversion, not the UI.
- Multi-warehouse data is kept internally. The initial external-channel policy is to publish aggregate available stock; a Shopware multi-stock plugin is a later, separate milestone.

## Current navigation structure

- **Catalogue:** products, categories, properties/attributes and manufacturers.
- **Inventory:** suppliers/offers, purchase orders, supplier invoices, replenishment, stock, warehouses, transfers, counts and exceptions.
- **Sales:** orders, pick lists and customers.
- **Integrations:** configured channel/source connections.
- **Company:** details, addresses, payment methods, pricing plans and billing.
- **Settings:** profile, security, team, notifications and Shop references/custom fields.

## Remaining work

### Priority 1 — make connectors production-ready

1. Complete idempotent product import for Shopware, then WooCommerce and OLX.
2. Map every supported association during import/export: translations, media, categories, manufacturer, properties/options, variants, prices, stock, visibility, SEO and custom fields/meta data.
3. Add field-mapping, category-mapping and capability-validation screens per connection.
4. Add publish/update/unpublish flows with explicit draft-first behaviour, sync logs, retry handling and actionable per-record errors.
5. Add scheduled/background sync jobs, connection locking, rate-limit handling and a full visible sync history.
6. Defer Ananas until its merchant API credentials and supported endpoints are confirmed.

### Priority 2 — complete catalogue operations

1. Close remaining Inventory release controls: non-default warehouse deactivation guards, durable worker supervision and checkout/stock coordination where required. Staff permissions remain deferred until membership rollout.
2. Add supplier-feed adapters and source-product matching/import review when distributor access/specifications are available; the supplier master, offers and purchasing workflow are implemented.
3. Complete global media-library management: search, reusable assets, non-image support if required and safe storage cleanup/auditing.
4. Add bulk catalogue actions, product duplication/archive/delete policy and import/export review screens.
5. Add category-required channel attributes and publishing validation for marketplaces such as OLX.

### Priority 3 — commercial operations

1. Add invoice payment-state handling, payment-provider reconciliation and customer-visible billing lifecycle states.
2. Add plan changes, proration/cancellation policy and product-limit enforcement.
3. Extend Sales import/synchronization beyond the implemented Shopware adapter when additional platforms enter scope.

### Priority 4 — quality, security and operations

1. Add automated backend API, domain and connector tests; add frontend component and end-to-end coverage for the main flows.
2. Run a security review of tenant authorization, credential encryption/key rotation, upload validation, rate limits and audit logging.
3. Add production deployment documentation, environment templates, backups, monitoring, error reporting and job-worker operations.
4. Normalize remaining legacy formatting and continue enforcing readable Vue/PHP formatting rules.

## Recommended next milestone

The best next milestone is **Shopware product import completion**. It exercises the entire canonical catalogue: products, translations, categories, media, manufacturers, properties, variants, prices, stock and custom fields. Once that mapping is reliable and idempotent, WooCommerce and OLX can use the same canonical services with connector-specific adapters instead of duplicating catalogue logic.

## Related documents

- [Technical specification](steelcode-connect-technical-specification.md)
- [Catalog data model](catalog-data-model.md)
- [Product field mapping](product-field-mapping.md)
- [Properties and variants](properties-and-variants.md)
- [Integrations module](integrations-module.md)
- [Error-handling rules](error-handling-rules.md)
