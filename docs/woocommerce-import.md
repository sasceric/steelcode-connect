# WooCommerce imports and ongoing Sales/stock sync

## Scope of the first adapter

WooCommerce uses the existing Integration import screen, Messenger `async`
transport, import-run history, logs and cancellation controls. It imports into
the shared, tenant-scoped catalogue and Sales entities; there is no separate
WooCommerce customer, order or product UI.

The adapter supports inbound catalogue and historical Sales imports, optional
queued ongoing order polling, and guarded outbound stock publication for
independently managed leaf products/variations. All settings reuse the existing
integration screen. Supported outbound catalogue publication and optional
saved-scope automatic updates now reuse the Export/Mapping tabs; see
[Woo catalogue publication](woocommerce-catalogue-publication.md). This is not
a complete bidirectional catalogue mirror, full gateway ledger, Woo warehouse
plugin or instantaneous webhook integration.

## Run an import

1. Open Integrations and select the WooCommerce connection.
2. Use Test connection to verify the saved REST API credentials against the
   actual store. The API user must be permitted to read the selected resources,
   including customers, orders, tax rates and store settings.
3. Select the catalogue scopes and Save. Run Import catalogue first.
4. Follow progress in Import, History and Logs. The frontend does not perform
   the import itself; an up-to-date Messenger worker must consume the job.
5. Enable Customers and/or Orders under Sales import. Leave the history date
   empty for all orders, or select the starting date. Save.
6. Run Import sales after catalogue import finishes, so line items can resolve
   to imported products and variations.
7. Review Sales → Customers and Sales → Orders. Review warnings for source
   products that were deleted or cannot be matched.

The bulk import worker command remains `composer imports:consume` in `backend`.
Ongoing sync now uses separate control, Sales and stock consumers; see
[queue operations](sync-queue-operations.md) for the full command list.
New handler deployments require a graceful worker replacement by the process
supervisor; clearing a container cache does not update an already-running
worker's loaded PHP classes. Do not stop a user's running development services
to perform that replacement without coordination.

## Catalogue mapping

References import before products. Collections are read in pages of 25 with
ascending IDs, and the ORM is cleared between pages. Variations are fetched
from each variable product's variation endpoint, also in pages of 25.

| WooCommerce data | Connect representation |
| --- | --- |
| Products and variations | Shared Product records, parent/variant relationships and combination values |
| Categories | Category translations, hierarchy and product assignments |
| Global/local attributes | Shared property groups and values; global terms use immutable Woo term IDs, including unused terms; local options use deterministic names |
| Core brands, when available | Separate tenant-owned Brands, translated names and all product brand assignments; variations inherit parent brands when the source omits them |
| Tags | Shared tags and assignments |
| Price/store currency | Existing currency registry, gross/net prices, regular-price reference and UTC sale schedules in advanced prices |
| Tax rates | Shared taxes; supported single, non-compound rate per class |
| Images | Existing tenant media storage and ProductMedia links |
| Downloads | Shared ProductDownload links to safe same-origin public files |
| Cross-sells/up-sells | Separate source-owned recommendation groups using shared ProductCrossSelling records |
| Store visibility | Existing integration sales channel and publication records |
| Source metadata | Typed definitions in one “WooCommerce fields” custom-field set, plus sanitized source snapshots |

### Brands and manufacturers

Connect keeps **one Manufacturer** and **multiple Brands** on a product. Woo core
brands are not treated as several actual manufacturers. On Product → General,
the separate Brands field uses the shared searchable multiselect, with 25 options
per request and more loaded on scroll. Existing selections stay checked, including
when they are not on the current search page. Names follow the catalogue locale.

On a Woo connection, the existing import scope switch is labelled **Brands**.
Its saved `areas.manufacturers` key is retained for configuration compatibility;
the Woo adapter maps it to brands, while Shopware continues to map it to its
single manufacturer. Re-imports reconcile only this connection's brand assignments,
including removals. Manually added brands and other connections' assignments are
preserved. Explicit user deselection removes the selected product's assignment;
the next authoritative import may restore a brand still assigned in Woo.

The earlier Woo import created manufacturer records from brands and assigned the
first brand. Those existing manufacturer choices are intentionally not deleted
or overwritten by the correction. New Woo imports populate Brands instead.
Shopware's single manufacturer import and stock/order sync remain unchanged.

To repair already-imported Woo products without downloading their images or
touching inventory, run:

```sh
php bin/console app:woocommerce:backfill-brands TENANT_UUID CONNECTION_UUID
```

The command checks tenant ownership, takes the same connection import lock,
refreshes brand reference records, then reconciles cached product snapshots in
batches of 25, parents before variations. Products without cached snapshots are
reported as skipped and need a normal catalogue re-import. Brand source metadata
preserves slug, hierarchy and image information; brand images are not yet exposed
as a separate brand-management gallery.

Product → Variants now uses the same cover/name cell as the product listing.
It shows the variation's cover, falls back to the parent's cover, then to
`placeholder-light.webp`. **Stock** has the same meaning as in the product list:
available quantity across Connect warehouses (on-hand minus reserved and unavailable).
Cover and stock enrichment is batched for the requested variant page only.
The shared table, column-visibility control, compact row layout and pagination
footer remain unchanged.

The variant-generation modal reuses the shared property-group/value picker with
independent group and value scroll panels. Values load in pages of 25; search is
server-side. Existing options from both imported and locally generated variations
are preselected, including values outside the first page. Selected values remain
visible as badges. The **Existing variants** tab uses the shared compact table to
show product numbers and combinations; the footer distinguishes missing and
already-generated combinations. Generation adds only missing combinations and
never removes or overwrites existing variations, their prices or identifiers.
Repeat generation is a no-op for existing combinations. Parent-level locking
serializes generator requests, and new product numbers skip already-used numbers.

Acceptance on the demo connection (30 September 2026): 10 Brand records;
8,231 product/variation snapshots processed with zero skips; all 4,869 explicit
source brand assignments present; 16,066 source-owned assignments including
inherited variation assignments, with zero parent/variant brand mismatches.
Repeating the repair created no duplicate assignments and left the inventory
balance checksum and movement count unchanged. Browser checks verified search,
multiple checked options, save/refresh persistence, and a variant cover plus stock
quantity. Backend regressions: 51 tests, 577 assertions; Nuxt type checking passes.

External-ID mappings are scoped to tenant and connection. Product matching
uses the saved matching order, with source ID first by default, then SKU/EAN.
Ambiguous matches fail instead of choosing an arbitrary product. A missing SKU
receives a stable, connection-scoped generated identifier.
Woo returns an inherited parent SKU when a variation has no own SKU. Such a
variation also receives its own stable identifier and never replaces its parent.

Core WooCommerce content has one language. It is stored in the tenant's default
catalogue locale. WPML/Polylang and other multilingual plugins require their own
adapter; their semantics are not inferred from arbitrary metadata.

Units and delivery times are not independent core WooCommerce catalogue
entities. Weight and dimensions are converted from the store's configured
measurement units to Connect's existing grams/millimetres fields. Protected
downloads, extension-specific manufacturers, multilingual content and plugin
workflows require dedicated adapters. Core metadata is imported losslessly;
that does not imply understanding the business rules of every plugin.

Fields without a core Woo equivalent are left unchanged rather than cleared:
purchase units, delivery-time references, manufacturer number, release date,
minimum quantity, purchase steps, restock time, local visibility and SEO fields.
Woo's explicit sold-individually flag maps to a one-unit purchase maximum.
Blank variation weights/dimensions inherit their parent values.

Tax classes with multiple/location-specific or compound rates need an explicit
mapping policy. Such products fail with a visible explanation rather than
receiving guessed prices. Images currently use the existing same-origin media
policy; external CDN images need an explicit trusted-origin policy.

Reimport updates mapped records, avoids duplicate products/media/assignments,
and preserves manual SEO overrides. Categories, tags, properties, media and
downloads track separate connector/manual claims. Removing an association in
Woo removes only that connection's claim on the next enabled-scope import.
The visible assignment remains if a manual or other-connector claim exists.
Shopware's category/tag/property importer uses the same shared ownership rules,
so importing one connection cannot wipe another connection's new claims.
Explicit user editing can remove an assignment; a later authoritative import
may restore an assignment still present in Woo. Saving/reordering existing
assignments manually protects them from connector-only removal.

Historical assignments created before ownership tracking have no provable
source. The migration protects them as manual; it never guesses ownership to
delete user data. Such legacy associations need deliberate manual cleanup if
they have become stale. Product deletion mirroring is deliberately not enabled.

### WooCommerce fields

1. Enable **Custom fields**, save and run **Import catalogue**. Sales imports
   normalize customer/order metadata as part of their own record import.
2. The first metadata-bearing record creates one tenant-owned set named
   **WooCommerce fields**, with product, customer and order relations.
3. Fields use a stable technical name derived from connection, entity type and
   original meta key. This separates genuinely different stores/entity types
   without creating another set on every import. Labels retain the original key.
4. Open Product → Custom fields and choose **WooCommerce fields**. The existing
   shared form displays product fields only. Customer/order overviews group their
   respective values under the same set using the shared source-fields card.
5. Actual numbers and booleans remain typed. Strings remain strings, including
   numeric-looking strings whose plugin meaning is unknown. Objects, arrays,
   nulls, repeated keys and inconsistent types use a read-only JSON field rather
   than a lossy text conversion. Repeated keys preserve every value.
6. Reimports update values and remove absent values owned by that connection;
   definitions stay available for other records. Manual/unrelated values remain.
   Prior read-only Sales imports' raw keys are migrated only where the old value
   is demonstrably identical to its stored source metadata.
7. Passwords, tokens, credentials, order keys and sensitive transport metadata
   are excluded recursively. No metadata is interpreted as executable markup.

Field definition creation is serialized per tenant, including simultaneous
imports from different Woo connections. The general JSON renderer is reused
instead of adding Woo-only product/customer/order forms.

### Attributes, variations and defaults

Global attribute references and their term endpoints are imported in pages of
25 before products. A term rename updates the same local property UUID, its
translation and its current name/slug lookup aliases. Existing name-based
imports are adopted when the old label is still known. If a term was renamed
before its ID was ever captured, old name-only provenance cannot reliably be
recovered; the old value is retained rather than guessed/deleted.

Woo's per-product attribute position, visibility, variation flag, selected value
IDs and default value are stored separately from global property-group settings.
They are returned in product/variant-configuration APIs. Variation attributes
resolve names or slugs to those same term UUIDs. The shared generator includes
the parent's variant-enabled selections and already-existing combinations.
Woo's empty variation option ("Any value") remains an explicit wildcard, never
an invented blank property. Existing wildcard combinations protect covered
concrete combinations from being generated as duplicates.
Local attributes have no upstream term ID: a local value rename is intentionally
a new value; obsolete connector-owned assignments are reconciled on reimport.

### Downloads, recommendations, scheduled sales and images

1. **Product images** (`areas.media`) controls image fetching and reconciliation.
   The Woo import screen exposes this scope using the existing scope switches;
   disabled means
   no product-image download or image-link removal. The source's image order is
   retained among connector-owned images; manual/other-source positions remain.
2. Image URL or modification-time changes get a new revision mapping. Equal
   content is deduplicated by tenant-owned checksum. Proven unchanged legacy
   image mappings are adopted without downloading everything again.
3. **Product downloads** independently controls shared download links. Public
   same-origin images, PDF, plain text and ZIP files up to 10 MB are accepted;
   HTML/scripts/executables, external hosts and redirects are rejected. Downloads
   are streamed with a size cap and served through the authenticated media API.
   Woo customer purchase entitlement/licence/protected-download workflows are
   not reproduced; this is catalogue-file import, not digital-order fulfilment.
4. **Product recommendations** imports cross-sells and up-sells in a second
   paged pass after products and variations exist, so forward references resolve.
   Missing source products produce a per-record warning, never a guessed SKU
   match. Changed/removed recommendations reconcile only their owned groups.
5. **Prices** stores Woo's current effective price and the regular-price reference.
   Dated sale prices are additionally imported into shared advanced prices with
   UTC start/end dates and a connection-owned pricing context. Removing a sale
   removes only that context's price. Connect does not invent a local promotion
   engine: Woo remains responsible for its live effective-price transitions.

### Acceptance and maintenance

Use the normal Integration UI and supervised Messenger worker for routine
imports. For controlled maintenance with a fresh PHP process, the following
command invokes exactly the same handler, saved scopes, locks, logs, history and
cancellation checks without restarting the API/frontend/browser:

```sh
php bin/console app:woocommerce:run-import TENANT_UUID CONNECTION_UUID products
php bin/console app:woocommerce:run-import TENANT_UUID CONNECTION_UUID sales
```

It rejects foreign/inactive connections and existing queued/running imports.
It is not a second import implementation or a replacement for supervised workers.
Transient transport failures and HTTP 429/502/503/504 use up to three attempts
for reads and idempotent absolute-stock PUTs, with fresh OAuth signatures.
Ambiguous POST failures are never blindly replayed. Persistent source outages
fail visibly in run history; repeat imports remain safe after recovery.
Rolling-deployment column defaults protect already-loaded older workers until
their normal graceful replacement. New code still requires that replacement.

Regression coverage includes repeat imports, stable term renames, typed and
structured metadata, source removals, manual preservation, scope-disabled media,
file updates, sale dates, related products and unchanged stock movements.

## Stock safety

Managed stock on a simple product or independently stock-managed variation may
seed an absent inventory level in the default warehouse. The shared inventory
service controls ownership: an existing Connect stock level is not overwritten
by catalogue reimport. Opening stock is recorded as an inventory movement.

An unmanaged product's `in stock` flag is **not** a numeric warehouse quantity.
Parent-managed variable-product stock is not distributed across variations or
duplicated into each variant warehouse balance.

Historical Sales import never reserves, ships, cancels or returns stock, even
when the source order status is completed, cancelled or refunded. Imported
historical records remain outside the live fulfillment workflow. Live orders
require the separate cutover below; importing an old order again cannot make it
reserve current stock.

## Enable ongoing orders, step by step

1. Finish catalogue and historical Sales imports. Review failed records and
   unresolved products before beginning live inventory operations.
2. Reconcile Connect warehouse balances against actual physical stock. Enable
   fulfillment only for the warehouses that can supply orders. Importing a
   catalogue is not proof that opening stock is still accurate.
3. On the Woo connection's Import tab, enable Orders and optionally Customers.
   Enable **Ongoing order sync**, leave **Connect manages shop stock** off, and
   Save. The server records a UTC cutover timestamp; it is not supplied by the
   client. Only orders created at/after that cutover enter live inventory.
4. Run up-to-date `composer sync:control`, `composer sync:sales` and
   `composer sync:stock` consumers in separate processes, plus
   `composer imports:consume` for historical imports. The scheduler discovers active
   channel connections every minute and queues tenant-scoped Woo sync messages.
   The scheduler's legacy `QueueShopwareSalesSync` name now covers both adapters;
   it does not create a second queue or duplicate inventory workflow.
5. Confirm **Ongoing sales sync** reports a successful last-sync time and no
   error. Every scan has a fixed UTC upper bound, 25-record pages, a five-minute
   overlap, and a persistent checkpoint. A failed record does not advance the
   checkpoint; Messenger retries and the exceptions screen expose failures.
6. New source orders appear under Sales → Orders. Source IDs resolve product
   and variation identity; a matching SKU alone cannot bind an unmapped Woo
   line to another product. Unmatched or unavailable lines stay visible as
   operational exceptions rather than inventing warehouse stock.
7. For registered customers on changed orders, the optional Customers scope
   fetches the current Woo profile/address data before ingesting the order.
   Order-time snapshots remain separate. Core Woo's customer collection does
   not provide the same modified-date query as orders: standalone profile edits
   without order activity are refreshed by Import Sales, not a claimed full
   incremental customer mirror.

Imports and live polling share a tenant/connection lock. Pending order retries
reuse the shared, bounded round-robin allocation processor (100 orders per
pass), and re-resolve source product mappings if catalogue mappings arrived
after the order. Disabling ongoing sync stops subsequent processing. Restarting
with a new cutover excludes orders placed while sync was disabled; review that
interval explicitly rather than assuming an automatic stock catch-up.

## Live statuses, shipments and returns

- New/pending/on-hold/processing/completed orders can reserve stock through the
  existing allocation policy. Woo `completed` is not evidence of physical
  shipped quantities, and does not automatically deduct warehouse stock.
- Open line quantity changes use the shared locked reconciliation workflow;
  already shipped quantities cannot be silently reduced or reassigned.
- Cancelled, failed and fully refunded orders release unshipped reservations.
  A refund does not prove goods returned: previously shipped stock remains
  deducted until the normal inspected-return workflow records a physical return.
- On a live Woo order detail, reuse **Shipment reconciliation** to enter the
  cumulative units actually shipped for every product line. For example, enter
  1 first and 3 later to ship two more units, not three additional units. The
  existing locked partial-shipment workflow prevents double deductions and
  shipping beyond the order quantity. Core Woo provides no authoritative
  delivery positions; a completed order awaiting physical quantity confirmation
  is shown under Inventory → Exceptions → Shipment reconciliation.
- These shipment/return operations are local Connect inventory operations.
  They do not capture/refund a payment or write Woo tracking/order statuses.
  Reopening a cancelled order is not an automatic new allocation workflow;
  review it explicitly rather than expecting the cancellation to be reversed.

## Optional Connect stock authority

1. Keep shop authority during the initial reconciliation and ongoing-order
   acceptance. The existing `stockAuthority: shopware` value is the legacy
   configuration token meaning **source-shop authority**, including for Woo.
2. Verify every published stock-managed leaf has the correct opening quantity
   in Connect, and check existing native Woo open orders/stock reductions.
   A successful Sales checkpoint is necessary, but not sufficient, evidence of
   a physically reconciled inventory.
3. Check Woo variable products. Shared parent-managed pools are not represented
   as independent Connect variation balances. Activation is blocked when the
   imported published catalogue contains such pools. The publisher also checks
   the live variation response and rejects inherited `manage_stock: parent`.
   Do not redistribute a parent quantity across every child. Pool-aware stock
   accounting is a separate capability, not an implicit conversion.
4. Only after reconciliation and a recent successful ongoing Sales scan, enable
   **Connect manages shop stock** and Save. The shared outbox queues published
   leaf products. Visibility-zero products and inactive channels are excluded.
5. Warehouse changes publish the aggregate available quantity from active,
   fulfillment-enabled warehouses, never on-hand plus reserved stock. The
   outbox waits for a fresh, error-free Sales checkpoint newer than its event
   before publication. A missing/stale/failed checkpoint blocks publication.
6. Simple products use `products/{id}`; variations use
   `products/{parentId}/variations/{variationId}`. Tenant-scoped source mappings
   verify both identities. The publisher reads current stock management first,
   skips intentionally unmanaged products, never enables `manage_stock`, and
   rejects fractional quantities instead of rounding them.
7. Updates assign an absolute whole-unit `stock_quantity`, not a decrement.
   Unchanged quantities skip the write; successful updates require a matching
   source ID and quantity in the response. Failed writes remain in the shared
   outbox for retry and are visible as stock-sync exceptions.

Woo natively reduces/restores stock on payment and order state transitions. A
changed live order therefore queues a fresh absolute publication even when its
Connect reservation is unchanged. An identical overlap replay does not reset
the outbox timestamp and postpone publication forever. Physical shipping
reduces both on-hand and reserved quantities; available stock is not deducted
twice. This does **not** provide a distributed transaction with Woo checkout.
Polling and queued publication have latency (typically multiple worker cycles);
simultaneous checkout/payment changes can temporarily diverge. Use a controlled
cutover, operational monitoring and an explicit safety-stock policy before
production; instantaneous checkout guarantees require additional integration.

No Woo plugin/webhook is required for this polling phase. Woo REST credentials
need write permission for stock publishing; retain read-only shop authority if
that permission or the reconciliation is unavailable. Do not enable both an
uncoordinated inventory plugin and Connect as independent stock writers.

## Customers and orders

Customers use the shared customer entity and its billing/shipping address book.
An entirely blank source address does not create an empty address-book row;
the original blank source fields are still preserved in the source snapshot.
Woo customer ID is scoped to its connection; customer zero denotes a guest,
not a single customer shared by all guest orders. Safe source fields and
metadata are preserved; passwords, tokens, order keys, sensitive URL/session
fields and IP/user-agent data are filtered out.

Orders retain their order-time customer and address snapshots independently of
current customer profiles. Reimporting old orders does not overwrite an
already-imported current customer profile. Lines preserve source product and
variation IDs, SKU/name, quantity, discounts and tax/net/gross amounts. Current
source mappings resolve variation identity before SKU matching.

Order totals and shipping taxes are imported from the source, not recalculated
using today's product prices or tax settings. Shipping lines populate delivery
method/address snapshots. Core WooCommerce does not provide authoritative
per-delivery shipment quantities or tracking, so none are invented.

Payment method, source transaction reference and paid/refunded state populate
the existing payment tab. This is the order's payment snapshot, not a gateway
transaction ledger or an instruction to capture/refund money. Woo order refund
references remain in the sanitized source snapshot; full refund transaction
documents and platform document migration are outside this first adapter.
When a source order has a paid date but no gateway name, the known paid state
is retained with an unknown method; no method name is invented.

## Queue and security behavior

- Imports use the saved server-side scopes, not arbitrary scopes from a queued
  client payload. Run, connection, mappings and resources are tenant-scoped.
- A per-tenant/connection lock serializes Woo imports. Cancellation and lock
  refresh are checked while records/pages are processed.
- Each record has a database transaction. Validation/mapping/media failures
  are counted and logged without rolling back earlier successful records.
  A database error that closes the ORM manager aborts the run safely.
- Transient HTTP 429/502/503/504 responses have bounded retries. HTTP OAuth is
  re-signed for every attempt; HTTPS uses Basic authentication.
- Credentials are decrypted only server-side. Redirects are disabled, errors
  do not expose provider bodies/signed URLs, and this client's requests bypass
  URL logging/profiling so OAuth signatures are not retained there.
- Source records are upserted on rerun. An interrupted run is not automatically
  resumed from an internal checkpoint; inspect it and start a new import.
- Production needs supervised workers, failed-queue monitoring, import alerts
  and retention/access policies for imported personal data and source snapshots.

## Verification

Automated tests cover OAuth signing, HTTPS authentication, 25-item paging,
safe errors, totals, source metadata filtering, guest identity, duplicate
attribute values, tenant-bound mappings, idempotent import, customer linking
and historical-import stock safety. Full demo-store acceptance results should
be recorded separately from these implementation guarantees.

### Local demo acceptance — 30 September 2026

Connected store: `http://wp-test.test`. The store contained earlier demo data in
addition to the recently generated records.

- Catalogue: 2,463 main products, 5,768 variations; 8,231 distinct product mappings.
- References: 21 categories, 10 core brands, 25 global attribute groups; 1,864
  property values used by the catalogue were mapped, including local attributes.
- Completed catalogue run: 8,327 processed records including its references,
  zero record failures. A replay of all 8,231 stored product snapshots created
  zero additional products.
- Sales: 96 source customer profiles and 386 orders imported. The customer list
  contains 97 records including a guest customer retained for an order.
- Repeating Sales import processed 482 records with zero creates and zero
  failures. All 386 orders were checked against source totals, currency,
  registered-customer links, delivery counts and historical status; no
  discrepancies were found.
- Inventory balance checksum and movement count were unchanged by both
  historical Sales import runs.
- Test defects found and corrected: repeated attribute values; inherited
  parent SKU merging variants; missing inherited manufacturer references;
  stale worker container; missing variant-stage translation.
- The cancelled early identity test had seeded 191 variable-parent balances
  incorrectly. These demo-only balances were reversed through locked, audited
  inventory adjustments. No movements, test runs or historical logs were
  deleted. Final audit: no self-parent products, no variant manufacturer
  mismatches, and no numeric opening balances on variable parents.
- Regression suite: 45 tests, 468 assertions; frontend type check, service
  container validation, YAML/JSON parsing and diff checks passed.

### Ongoing-sync acceptance — 30 September 2026

- Enabled ongoing order sync through the existing connection UI. Stock authority
  remained with Woo: the actual demo catalogue contains shared parent stock
  pools, which the activation guard rejects before any stock cutover.
- After the user's approval, replaced only the exact background worker using
  Symfony's targeted graceful SIGTERM handling. The browser, port 3000 frontend
  and port 8000 API stayed running. The replacement consumes the same scheduler
  and async transports, and scheduler logs show both Shopware and Woo messages.
- Created the explicitly marked, unpaid demo order **26726** for two units of
  mapped Woo product **18136**. The real queued handler imported it and reserved
  exactly two units; overlap and an extra queued pass did not reserve again.
- All **386 historical orders** remained historical. The local test product
  retained on-hand 79 with reserved 2 during the open order.
- Exercised a real authenticated stock PUT through the publisher on this
  independently managed product, read it back, and restored its original source
  quantity. Stock-management mode was unchanged. This checks the publisher, not
  shop-wide stock authority or end-to-end outbox cutover.
- Cancelled only the marked demo order and consumed its queued update. Connect
  now shows cancelled with on-hand **79**, reserved **0**; Woo read-back is **79**.
  All **386 historical orders** still have historical status.
- Automated lifecycle tests cover 25-item paging, replay, tenant mismatch,
  quantity edits, manual partial/full shipment, cancellation, financial refund
  without restock, failed-checkpoint retention, and exact variation publication.
  They also verify no management-mode conversion or fractional-stock rounding.
- Final regression suite: **49 tests, 537 assertions** passed, along with
  frontend typechecking, container validation and diff checks.

Live Woo webhooks, shared parent stock pools, location-specific tax calculation,
plugin-specific data, outbound order fulfillment and full gateway/refund ledgers
remain explicit limitations, not silently enabled scopes.

### Catalogue parity acceptance — 1 October 2026

- Completed the expanded catalogue import and a full live replay using the same
  import handler and saved scopes: **11,232 processed, zero creates and zero
  failures** in each completed run. This includes references, global terms,
  products, variations and the second recommendation pass; it is not a product
  count. Product mappings remained **8,231**.
- Repeated Sales import: **483 updated, zero creates and zero failures**.
  Metadata uses **one WooCommerce fields set with 15 definitions**, not another
  set or duplicate definitions on each replay.
- Inventory remained **6,603 balances and 6,811 movements**, with checksum
  `34b38d106f31b0c4fddfe343f470ad73` unchanged across the final catalogue replay.
- Two earlier acceptance retries stopped visibly on source transport timeouts.
  Their successful records/history were retained, with no stock change. Bounded
  safe-read/absolute-stock retries were added; after the source responded again,
  the full replay completed successfully. This is not automatic checkpoint
  resumption or an unlimited retry loop.
- Browser verification confirmed the existing product Custom fields form and
  grouped order overview card, plus the reused Woo import scope controls. Units
  and delivery times are hidden for Woo; Product images is independently scoped.
  No running browser/frontend/API service was stopped. When port 8000 later
  had no listener, the documented local API was started on that free port.
- Final regression suite: **62 tests, 724 assertions** passed. Frontend
  typechecking, PHP syntax, service-container validation, YAML/locale JSON and
  diff checks passed. The existing Volar plugin-resolution warning remains a
  tooling warning; it did not produce a TypeScript failure.
- Worker replacement/start remains an operational step requiring coordination.
  These acceptance runs used the fresh-process maintenance command; they do
  not imply an absent or old Messenger worker has been started/reloaded.

API semantics were checked against the installed Woo source and the official
[WooCommerce REST API documentation](https://woocommerce.github.io/woocommerce-rest-api-docs/)
and [order API documentation](https://developer.woocommerce.com/docs/apis/rest-api/v3/orders/).
