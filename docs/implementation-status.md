# Implementation status

Last updated: **3 October 2026**

SteelCode Connect is a multi-tenant catalogue and commerce-integration application. This document records what is implemented in the current codebase and separates it from planned work.

## Original idea and current MVP scope

The [24 July technical specification](steelcode-connect-technical-specification.md)
remains the historical starting plan. Its central idea is unchanged:

**Supplier or connected-shop catalogue → Connect catalogue, mapping and pricing
rules → sales channels.** Shopware is one source/destination, not the mandatory
centre of the architecture.

The original first vertical slice used KimTec as its source and Shopware and/or
OLX as its destination. **KimTec, Comtrade and other distributor-feed adapters
are now deferred until actual access and API/feed specifications are available.**
They are not blockers for the current milestone. Do not invent their contracts
or describe the existing supplier master as a working catalogue-feed connector.

The **Connect → Shopware catalogue publication** workflow now includes explicit
All/Selected scope, queued preview/publication, Sync now, optional reference
creation and saved-scope automatic product synchronization. Live draft creation,
automatic updating and a 100-product content update passed; broader production
acceptance remains. Use
the existing Connect catalogue, populated through the UI or the implemented
Shopware/Woo imports, to test this path without distributor access.

| Original workstream | Current position |
| --- | --- |
| Authentication, tenant and integration foundation | Implemented; multi-member permissions and production security acceptance remain. |
| Central catalogue and reusable administration UI | Implemented for the delivered catalogue scope. |
| Shopware catalogue import | Implemented; no longer the next milestone. |
| Woo catalogue and Sales import | Implemented within the documented core compatibility boundaries; an expansion beyond the initial MVP. |
| Inventory, purchasing, customers and orders | Operational core implemented; these expanded the initial MVP, which excluded order-driven stock operations. |
| Distributor catalogue ingestion | Deferred until access is available; supplier records/offers and purchasing are already implemented. |
| Destination mapping, product-selection and channel-pricing rules | Shopware selection, reference/identity mapping and percentage selling-price adjustment implemented. Advanced pricing/overrides remain. |
| Catalogue publication | Shopware and Woo manual and saved-scope automatic product/variant publication implemented through a shared queued workflow with preview, missing-reference creation, guards and results. Live Shopware 100-product content update and Woo parent/variation create, automatic update and replay passed. Neither is full round-trip parity. |
| OLX/Ananas listing workflows | Not implemented; configuration cards are not evidence of listing support. |

This is not yet the complete original end-to-end distribution MVP. The largest
remaining release milestone is broader production acceptance and operational rollout, not another Inventory/Sales
module. Do not reopen those modules for advanced ERP features unless a concrete
release defect or agreed business requirement makes it necessary.

## Technical foundation

| Area | Current state |
| --- | --- |
| Frontend | Nuxt 4, Vue 3, TypeScript, Nuxt UI and Tailwind, based on the Nuxt UI Dashboard template. |
| Backend | Symfony 7.4, Doctrine ORM, PostgreSQL, Twig, Dompdf, Messenger and Scheduler. |
| Tenancy | Tenant-owned catalogue, company, billing and integration records. Access uses a shared membership lookup that rejects missing/ambiguous company context; multi-company switching is not implemented yet. |
| Authentication | Session-cookie login, sign-up, logout, current-user restoration, password reset and password change. |
| Localisation | Bosnian is the primary UI language; English and German are available. Product, category, manufacturer, property, custom-field and Shop-reference translations are stored per locale. |
| User feedback | Frontend uses title/message toasts. Backend-owned messages are translated through Symfony where applicable. |
| Branding | Light/dark SteelCode Connect logos, email templates, invoice template, favicon/PWA assets and a reusable translation selector with country flags. |
| Shared UI | Compact reusable tables, server-side paging/search/sort on implemented lists, column visibility, sticky actions/footer patterns and searchable lazy-loaded selectors. |
| Background execution | Messenger/Scheduler, persisted run history/logs, cancellation, failure transport, connection locks and a stock outbox are implemented. Shopware catalogue previews/publication reuse these foundations. |

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
- Connector configuration forms, encrypted credentials, activation/deactivation and removal; provider-specific connection testing where implemented.
- Installed-integration list with logo, activation state and installation date.
- Shopware and Woo catalogue/Sales import runs use the existing Messenger queue, persisted history, per-run logs, live UI progress and cancellation controls. Collections are paged rather than loaded in one HTTP request.
- Canonical Shopware product and category SEO routes are imported from `seo_url` with their original locale and sales-channel scopes; the first canonical Shopware route is also retained as the catalogue-global URL.
- The Shopware importer safely creates or updates products by external ID, SKU or EAN; imports all enabled matching locales, parent/child structure, reference data, media, tags, units, sales-channel visibility, regular and rule/tier prices, downloads, cross-selling, and source mappings for idempotency.
- The Woo importer maps products/variations, categories, core brands, global/local attributes, stable term identities, per-product attribute flags/defaults, tags, supported prices/taxes, image assignments, supported public downloads and cross-/up-sells. Source metadata uses one tenant-owned **WooCommerce fields** set with stable typed definitions.
- Woo reimports reconcile connector-owned claims while protecting manual/other-source assignments. Unsupported core equivalents such as purchase units and delivery times are not invented or used to clear local values.
- Shopware and Woo have historical customer/order imports, optional ongoing Sales polling and guarded stock publication through the shared inventory outbox. Historical Sales imports never change stock.
- Shopware Export/Mapping adds explicit All/Selected scope, category/brand/manufacturer/product rules, clear/reset controls, paged read-only previews, frozen typed payloads, reference/identity overrides, draft-first publication and per-product results. Opt-in reference creation covers category parent chains, manufacturers, property groups/options, taxes, units, delivery times and compatible custom fields/sets; existing records and technical-name collisions remain guarded.
- Shopware Sync now validates and publishes the saved scope through the same queue. Opt-in automatic product publication prioritizes transactional change hints, inspects bounded candidate pages, queues up to 25 changed candidates plus required parents, coalesces active runs, skips unchanged fingerprints, applies failure backoff and protects intervening Shopware edits using publication baselines. It is eventual sync, not a complete reference-update/bidirectional mirror or a signed webhook App.
- Woo Export/Mapping reuses that UI and the provider-neutral queued workflow and reconciliation service. It publishes supported simple/variable products and variations, categories, native brands, attributes/terms, prices, dimensions, portable metadata and signed stored images. Live creation, automatic updating and completed-run replay passed. Unsupported equivalents remain explicit; see [Woo publication](woocommerce-catalogue-publication.md).

Ananas remains deferred until partner/API access is available. OLX listing
publication is not implemented and requires a confirmed supported API/access
model. Other platform adapters are later milestones. Connection configuration,
import mappings and local publication/visibility records do not themselves
create or update a complete product in an external shop. The new explicit
Shopware Export workflow does publish its documented supported product fields;
see the [publication guide](shopware-catalogue-publication.md).

The implemented ongoing synchronization covers Sales, supported stock writes and
opt-in Shopware/Woo product publication within saved scopes. Full bidirectional
migration and plugin-specific field parity are not implemented. Read
the [Woo operating guide](woocommerce-import.md) and
[Shopware cutover guide](shopware-sales-inventory-cutover.md) for provider-specific
boundaries and acceptance evidence.

### Catalogue

- Product list and a Shopware-inspired product editor with a persistent shared detail header.
- Parent/child product variants: child products use `parent_id`, inherit parent data on generation and receive generated product numbers/SKUs.
- Product translations, HTML/WYSIWYG descriptions, locale-aware canonical SEO URLs with 301 redirect history, tags, labels, release date, visibility, category assignment and sales-channel publication data.
- Regular gross/net pricing with tax calculation and price linking.
- Advanced pricing with rule sets, quantity min/max tiers, regular/list/lowest-30-day price pairs, linked gross/net calculation and row/rule deletion.
- Gallery with uploads, stored media metadata, order changes, cover selection, individual deletion and reusable media picker/selection components.
- Product properties, property groups, colour/image/text/dropdown display settings and variant-option generation.
- Custom-field sets and custom fields, including typed field configuration, translated labels/options and a shared JSON renderer for structured Woo metadata. Product/customer/order values use existing shared forms/cards.
- Product categories: nested tree, drag/drop movement, add child/before/after actions, translations, SEO, media, product assignment and category custom fields.
- Manufacturers: translated detail records, SEO, media, custom fields, product assignment and product-editor selection.
- One manufacturer and separate multiple brands; Woo core brands do not become several manufacturers. Product/variant lists reuse cover/name cells, placeholders and stock enrichment for the requested page.
- Shop references: units, taxes and delivery times, including translations and locale-aware lists.
- Shared currency and locale registries. New connector currencies can be registered when valid ISO data is available.

### Inventory, purchasing and Sales

- Protected Default warehouse, per-warehouse balances and audited movements;
  product warehouse-stock views and warehouse product lists.
- Transfers, stock counts, supplier master records, supplier-product commercial
  terms, dated cost tiers, purchase orders, partial/damaged receipts,
  replenishment and operational supplier-invoice matching.
- Customer profiles/address books and order-time commercial snapshots, customer
  links/history, line items, taxes, payment and delivery snapshots.
- Full-order allocation into one eligible warehouse, reservations, cancellation
  release, durable picking, partial shipment reconciliation, physical returns,
  quarantine/disposition and an exception workbench.
- Tenant/product-coalesced stock publication to supported existing Shopware/Woo
  product identities, after explicit stock-authority cutover and reconciliation.
- Shopware disposable-shop acceptance covered a live storefront order,
  allocation, picking, shipment, return/quarantine and stock reconciliation.
  Woo acceptance covered ongoing order ingestion/replay/cancellation and a
  guarded stock write; shared parent-managed variant pools block stock-authority
  activation rather than being silently redistributed.

These are operational inventory workflows, not accounting settlement, payment
capture/refund execution, universal provider shipment writers or advanced WMS
features. See the [Inventory and Sales operating guide](inventory-and-sales-guide.md)
and [fulfilment/access plan](fulfilment-and-access-plan.md) for exact boundaries.

## Key data-model decisions

- The internal catalogue is canonical. Adapters translate between it and external systems; an adapter's planned outbound role is not proof that its product writer already exists.
- Variants are products, not a separate product-variant entity. The distinction is `products.parent_id`.
- Descriptive properties and variant-generating options share property-group/property definitions; their assignments determine the role.
- Media is stored in the central `media` table. Relation tables such as `product_media` only describe ownership, position and cover usage.
- Product/category/manufacturer and shop-reference content is translated in dedicated translation tables. The tenant default snippet locale is always the first required translation on create.
- SEO paths are a dedicated `seo_urls` resource, not translation-table columns. Canonical paths are unique per tenant, locale and optional sales channel; replaced paths remain as 301 redirects.
- Product price data supports linked gross/net values and structured advanced-price tiers. Connector adapters are responsible for capability validation and conversion, not the UI.
- Multi-warehouse data is kept internally. The initial external-channel policy is to publish aggregate available stock; a Shopware multi-stock plugin is a later, separate milestone.
- Supplier cost, supplier availability, channel selling price and physically received warehouse stock are distinct. Future distributor-feed imports must never turn advertised supplier availability into received merchant stock.
- Orders retain immutable commercial snapshots; live order operations and historical imports are separate. Catalogue translations do not rewrite past order names or prices.
- New resources, mappings, publication jobs, credentials, files, cache keys and audit records must remain tenant-scoped. Preserve the modular-monolith design so large tenants can later use dedicated workers/shards without a second domain implementation.

## Current navigation structure

- **Catalogue:** products, categories, properties/attributes and manufacturers.
- **Inventory:** suppliers/offers, purchase orders, supplier invoices, replenishment, stock, warehouses, transfers, counts and exceptions.
- **Sales:** orders, pick lists and customers.
- **Integrations:** configured channel/source connections.
- **Company:** details, addresses, payment methods, pricing plans and billing.
- **Shop:** taxes, units, delivery times and custom fields.
- **Settings:** profile, security, translations, notifications and Members preview. The real owner's row is shown; invitations/operational role changes are not enabled.

## Remaining work

### Priority 1 — Connect → Shopware catalogue publication

Publication means **exporting catalogue data from Connect into Shopware through
its API**, not downloading a CSV and not importing more Shopware data into
Connect. It also does not mean exporting customers, orders, payment execution
or historical documents.

1. **Implemented first scope:** per-connection product selection and destination category/attribute/field
   mapping, including clear ownership/conflict rules for already-mapped products.
   Existing external-ID/SKU/EAN import matching is not destination mapping.
2. **Partially implemented:** a percentage selling-price adjustment. Broader channel-pricing rules and explicit product overrides remain. Start with a small,
   declarative supported rule set; catalogue price storage and advanced-price
   editors are not a supplier-cost-to-channel-price rule engine.
3. **Implemented first scope:** a validation/preview step covering required fields, destination references,
   currency/tax compatibility, variants and supported custom-field types.
   Unsupported data must be explained, not silently guessed or discarded.
4. **Implemented first scope:** queued create/update operations for supported catalogue records and
   associations: products/variants, translations, categories, manufacturer,
   properties/options, media, regular prices, visibility and compatible mapped custom fields.
   Stock writes must reuse the existing reconciled ownership/outbox policy.
5. **Implemented:** new listings draft-first; activation/deactivation explicit.
   Persist destination identities and publication outcomes. Do not delete or
   overwrite unrelated Shopware records as an implicit export side effect.
6. **Implemented:** shared UI, queue/log/history/cancellation patterns, tenant-scoped locks
   and bounded retries. Publication-specific messages/state may be added where
   needed; do not duplicate the catalogue or import UI.
7. **Verified delivered scope:** read-only preview, live draft creation with ten
   new reference dependencies, cover/custom-field assignment, an automatic
   edit/update and a 100-product content-update run. Automated guards cover replay,
   conflict/failure, scope isolation and queued-plan invalidation. Broader live
   activation/deactivation, fresh binary-media upload and production-scale
   checks remain; no duplicate listings or inventory changes are acceptable.

**Current milestone finish line:** a tenant connects Shopware, chooses up to
100 existing Connect products, reviews mappings/prices, publishes them safely,
sees destination IDs and outcomes, updates/deactivates them, and retries a
failed job without duplicating listings. This proves the active publication
slice; it is not acceptance of a still-unavailable distributor connector.

**Queue optimization (2 October 2026):** separate control, ongoing Sales, stock,
incremental catalogue and bulk lanes; durable five-second edit debounce (thirty-
second continuous-edit cap); per-connection reconciliation leases; 25-item export
preview/preflight checkpoints and short publication chunks; finite HTTP durations,
delayed bounded catalogue retries, cached tenant/connection code lookups and
backlog visibility. Publication now batches up to 25 Sync API products, re-reads
sources/targets/references before writing, isolates validation failures and safely
reconciles uncertain remote commits. Atomic item/counter acknowledgements and
parent-first work indexes remove repeated whole-plan recounts/sorts. Existing
history/components and inventory guards are reused. The local five-lane workers
were activated; a live inactive QA edit completed in about nine seconds with no
stock/inventory changes. The real-DB/simulated-HTTP 20,000-item fixture completed
correctly and used about 91% fewer API calls after batching.
Historical imports remain paged within their existing bulk handlers. See
[queue operations](sync-queue-operations.md); this is not production load-test
acceptance or a zero-wait/crash-proof guarantee.

### Priority 2 — release gates before a production pilot

1. **Completed locally:** non-default warehouse-deactivation guard for balances,
   reserved allocations, quarantined returns and open POs/transfers/counts.
   Shared operational/exclusive deactivation row locks protect the concurrent
   case. Default stays active; completed history remains accessible. Verify the
   merchant's own warehouse lifecycle during its production pilot.
2. Implement real membership/invitations and centralized tenant permissions
   before non-owner staff rollout. Keep the original Members design/preview and
   add cross-tenant/role tests before enabling its controls. Roles remain a later
   implementation step, not the next publication milestone.
3. Extend the existing automated tests with publication contract/API tests and
   an end-to-end connection → selection/mapping → publish/update → retry test.
4. Complete production tenant-isolation/security acceptance, credential-key
   management, upload review, audit coverage and the original RLS decision.
5. Verify backup restoration, retention, health/error monitoring and failed-job
   alerts. Production workers/scheduler are operated through systemd/Supervisor;
   a stopped local development worker is not missing product functionality.
6. If the pilot is paid, define/enforce the required billing/payment states,
   plan limits and cancellation policy before charging customers. The existing
   subscription/invoice foundation is not a complete payment-provider lifecycle.

### Deferred until access or an explicit later milestone

- **KimTec, Comtrade and other distributor feeds:** resume only with actual
  credentials, sample payloads and supported API/feed contracts. Reuse suppliers,
  offers, product matching and catalogue services. No guessed vendor adapter,
  generic feed implementation or extra supplier module is required now.
- **OLX listing publication:** a candidate next destination after the Shopware
  slice, subject to confirmed official API/access, category attributes and
  listing requirements. Do not claim the current card is a functioning exporter.
- **Ananas and additional destinations/providers:** separate adapters after
  access and requirements are confirmed; no requirement to finish every platform
  for this Shopware milestone.
- Full cross-platform customer/order migration, document binaries, outbound
  shipment writers, payment captures/refunds and supplier accounting/payment
  integration.
- Plugin-specific/multilingual Woo data, protected-download entitlement,
  complex/location-based Woo tax policies and shared parent stock pools.
- Bin locations, barcode/wave picking, serial/lot tracking, advanced forecasting
  and a Shopware-native multi-stock extension.
- Advanced billing, broader media-library lifecycle work and additional bulk
  catalogue convenience features unless the pilot establishes a concrete need.

## Recommended next milestone

**Local tenant-boundary acceptance completed (3 October):** two-company real HTTP
tests, foreign/mixed ID rejection, historical source identity, media path/cache
guards, worker ownership, credential/settings-cache separation and scoped import
logs. Manufacturer assignment's silent foreign-ID acceptance was corrected.
Full backend validation now passes **116 tests / 1,588 assertions**. See
[Tenant-isolation acceptance](tenant-isolation-acceptance.md) for the exact tested
scope, fail-closed multi-membership behavior and remaining deployment checks.
This is not a completed production penetration test or staff permission rollout.

**Complete release acceptance for the delivered Shopware/Woo publication scope**:
visual browser checks, production media reachability, security/tenant isolation,
monitoring and realistic load/capacity checks. Both providers now reuse scope,
preview, confirmation, queue/history and automatic-sync patterns. Detailed
boundaries are in the [Shopware guide](shopware-catalogue-publication.md) and
[Woo publication guide](woocommerce-catalogue-publication.md).
Do not reopen completed import work or expand into another ERP module.

## Verification snapshot

- Shopware live order/inventory cutover evidence is recorded in the
  [cutover guide](shopware-sales-inventory-cutover.md).
- Woo completed expanded catalogue import and full replay: **11,232 processed
  records, zero creates and zero failures** in each completed run; product
  mappings remained **8,231**. Counts include reference/recommendation passes,
  not 11,232 distinct products.
- Woo Sales replay: **483 updated, zero creates and zero failures**. One
  WooCommerce fields set retained **15 definitions**. Inventory balance checksum
  and movement counts remained unchanged across the final catalogue replay.
- Latest recorded backend regression run: **116 tests, 1588 assertions**; see
  [release readiness](sync-release-readiness.md) for the latest evidence, and the
  Woo publication guide for its earlier live acceptance record.
  Frontend typechecking, PHP syntax, container validation, YAML/locale JSON and
  diff checks passed. These checks do not replace production security/load
  acceptance. Live Shopware checks passed: a two-product parent/variant update,
  inactive QA creation with ten catalogue references, cover/custom-field
  assignment, automatic updating and **100 content updates with zero failures**.
  The original saved scope was restored and local inventory checksum remained
  unchanged. These are not full optional-field or production-scale acceptance;
  see the publication guide for exact boundaries.
- Provider-specific limits and detailed acceptance records live in the
  [Woo guide](woocommerce-import.md) and
  [Inventory and Sales guide](inventory-and-sales-guide.md).
- Browser acceptance on 2 October covered the existing shared Export controls:
  Woo preview/cancel/payload/confirmation/publication, provider-correct Mapping
  labels, Shopware preview/publication, and explicit All/reset behavior. Saved
  scopes were restored. Warehouse UI rejected a busy warehouse with an inline
  shared alert and successfully created/deactivated an empty QA location.
  Manual mapping add/save/reload/remove was also verified. Empty API mapping
  arrays are now normalized into object dictionaries in the shared component,
  fixing first-row reactivity and JSON persistence for both providers.
- Mixed Woo acceptance updated **126 existing records** (90 simple products,
  10 variable parents, 26 variants), with **99 image-bearing records**. The clean
  replay completed in **144.88 seconds**, zero creates/failures, and retained
  destination IDs, images, stock and status. Testing exposed and fixed blank-SKU
  mapped lookup and cross-parent variant-chunk starvation. This was content-only
  publication with image preservation, not 126 fresh image uploads or a
  production/multi-tenant capacity guarantee.
- Release-readiness work added tenant-scoped Shopware/Woo health diagnostics,
  alert exit codes, production media URL/security guards and concurrent durable-queue
  benchmarks. It found and fixed exporters clearing stock destination identities;
  the demo's 99 failed stock events recovered through ordinary guarded processing.
  Reverse identity lookups now have a concurrently built tenant/connection index.
  Simulated-provider 20,000-product correctness passed alongside two other tenant
  processes. Live production-scale/media-network acceptance is still separate;
  see [release readiness](sync-release-readiness.md) for measurements and limits.
- Real message-boundary worker recovery passed on 3 October for **39 Shopware
  and 35 Woo mixed records**, using separate actual Messenger processes and
  isolated acceptance queues. Completed-message replay changed neither run
  counters nor IDs/images/stock/status; all 74 owned payloads matched live data.
  Saved export settings were restored. Both Sales cursors advanced; 39 tenant
  stock notifications completed and 37 Shopware leaf stocks matched warehouse
  availability. Woo's source-stock authority was preserved. This proves graceful
  worker recycling, not arbitrary SIGKILL or real 20,000-product throughput.

## Related documents

- [Sync release readiness](sync-release-readiness.md)
- [Shopware catalogue publication](shopware-catalogue-publication.md)
- [WooCommerce catalogue publication](woocommerce-catalogue-publication.md)
- [Technical specification](steelcode-connect-technical-specification.md)
- [Catalog data model](catalog-data-model.md)
- [Product field mapping](product-field-mapping.md)
- [Properties and variants](properties-and-variants.md)
- [Integrations module](integrations-module.md)
- [Error-handling rules](error-handling-rules.md)
- [Inventory and Sales operating guide](inventory-and-sales-guide.md)
- [WooCommerce operating guide](woocommerce-import.md)
- [Shopware Sales and inventory cutover](shopware-sales-inventory-cutover.md)
- [Fulfilment and access plan](fulfilment-and-access-plan.md)
