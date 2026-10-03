# Connect → Shopware catalogue publication

Implemented first scope: 1 October 2026.

This workflow sends selected Connect products to an active Shopware connection
through its Admin API. It is separate from **Import catalogue**, Sales imports
and stock synchronization. It supports manual publication and opt-in automatic
product publication; it is not a full platform migration or a bidirectional mirror.

## 1. Open the connection

Open **Integrations → your Shopware connection**. The existing screen now has
**Import**, **Export**, **Mapping**, **History** and **Logs** tabs. Export is
currently available for Shopware only. Existing Woo import matching is unchanged.

The connection must be active and its existing API integration must have read
access to the referenced entities and write access to products/media. Creating
references also requires write access to those entities. No new
Shopware plugin is required for this supported catalogue workflow.

All settings, selections, credentials, plans, queue messages, mappings, files and
results are resolved through the authenticated tenant. Owner access is required
for this workflow until the separate staff-permissions rollout is completed.

## 2. Choose what to publish

In **Export**, select a destination sales channel and an explicit scope:

- **Selected products and filters:** the rules below apply. Matching future
  products are included automatically when automatic synchronization is enabled.
- **All products:** all tenant products, with optional variants and exclusions.
  Category/brand/manufacturer/manual-include selectors are disabled because they
  do not narrow this mode. This is a stored server-side rule, not thousands of
  selected IDs or just the first dropdown page.

For selected scope, choose:

- Categories, optionally including their subcategories.
- Brands and/or manufacturers.
- Individual products, including specific variants.
- Products to exclude.

Category, brand and manufacturer filters are combined with **AND**. Within a
filter, any selected value matches. Individual products are added with **OR**.
The include dropdowns have an **All** option. All categories/brands/manufacturers
means no restriction for that filter, not loading/selecting every record.
Choosing **All products** switches to the all-catalogue scope; choosing an
individual product switches back to selected scope. Clearing All products
also restores selected scope. The X uses the shared select's built-in clear
control, aligned inside the selector, not below it. Exclusions remain explicit:
an All exclusions choice would exclude everything and is not offered.

Empty filters and an empty individual selection in selected mode mean **nothing**,
not all products. Per-selector clear controls remove only that selection.
**Reset selection** clears includes/excludes and restores selected mode; it keeps
the destination, mappings, pricing and publication preferences. Save explicitly.

Selecting parent products can include their variants. Selecting a specific
variant always includes its required parent, even if that parent was excluded.
The preview shows this dependency. Duplicate selections become one product row.
Brand filters select products; they do not turn multiple brands into Shopware's
single manufacturer.

Selectors reuse the shared searchable select, load 25 options at a time and
search products by name or product number. Preview tables use the shared compact
table and pagination footer. The selection is resolved in the database; the
browser does not download the entire catalogue to calculate it.

## 3. Review destination mappings

Open **Mapping**. Standard fields such as product number, name and description
have fixed, typed mappings; users do not need to match every API column manually.
Select the groups Connect is allowed to write:

| Group | Supported data |
| --- | --- |
| Content | Names, descriptions, EAN, manufacturer number, translated SEO metadata. |
| Prices | Tax reference and regular currency gross/net prices, list and regulation price pairs. |
| Classification | Category, manufacturer and property assignments. |
| Fulfilment | Units, delivery time, purchase limits/steps, dimensions, weight and supported shipping fields. |
| Custom fields | Mapped or safely created compatible destination definitions and translated values. |
| Images | Tenant-owned local media, image assignments, positions and cover. |

Product identities and variant parent/options/configurator dependencies are
always checked. Creating a product requires its essential name, tax and prices
even if an update field group was unchecked.

Previously imported references from **this same connection** are reused.
Languages and currencies match automatically only when a unique destination
locale/ISO code agrees. Manual mappings override these matches. References from a
different shop are not assumed to exist in this destination.

For a missing reference, choose its type, the Connect record and an existing
Shopware record, click **Add mapping**, then **Save mappings**. Available types
are category, manufacturer, property value, tax, currency, language, unit,
delivery time, custom field and product identity.

Alternatively enable **Create missing catalogue references**. Preview plans the
missing categories and their parent chain, manufacturers, property groups/options,
taxes, units, delivery times and compatible custom fields/sets used by selected
products. The eye dialog includes these dependencies. Nothing is created during
preview. Publication creates parents/sets/groups before their dependent records
and products, using stable tenant/connection IDs and persisted mappings.

Choose **Parent for new root categories**, normally the selected channel's
navigation root, to place a new category tree in the storefront. Without it,
categories remain independent roots; this does not reconfigure Shopware navigation.

Languages and currencies must already exist and be mapped; creating currencies
or inventing currency conversion is deliberately excluded. Existing references
are reused, not blindly overwritten. A modified Connect-created reference is
blocked for reconciliation/manual mapping. A custom-field technical-name collision
is blocked rather than merging unrelated schemas. Compatible scalar/JSON field
types are supported; plugin-specific/select/media definitions need explicit mapping
or remain blocked. Manufacturer/category image export is not part of reference
creation; product media and cover publication are supported separately.

Product identity mapping is useful when the destination already has the intended
product but Connect has not imported its identity. A conflicting SKU is blocked
for review, not silently merged. Product/language/currency overrides must be
one-to-one. Mapping a tax to a different rate or a currency to a different ISO
code is blocked; no currency conversion is inferred.

The initial pricing rule is a per-connection percentage adjustment to supported
selling prices. It is not a supplier-cost pricing engine, currency converter,
advanced rule/tier exporter or per-product channel-price override system.

Save Mapping changes before switching tabs. Import matching remains a separate
section of this tab and does not grant permission to overwrite destination data.

## 4. Build a preview

Return to **Export** and click **Build export preview**. Current export settings
are saved and a normal Messenger job is queued. Preview performs Shopware reads
only: it does not create products or upload images.

The job resolves the selection in bounded batches, validates references,
prices, variant combinations and custom-field types, then stores a frozen
proposed payload for every selected product. The table shows create/update,
validation errors and the proposed Shopware payload behind the eye button.

Missing mappings, ambiguous identities, unavailable media, wildcard/nested
variants and incompatible types block publication. Fix the issue and build a
new preview. A ready preview must also be rebuilt after settings/catalogue
changes or when older than 24 hours. The destination endpoint is frozen too;
changing it requires a fresh preview. Active connection/configuration and
cancellation are rechecked between publication operations. Source data is rechecked before the first
write and again at each product; owned destination fields are checked against
their preview snapshot before that product is written.

The shared run-progress card distinguishes **queued** (not started by a worker)
from **running**, shows the current phase, processed/selected counts, elapsed
time, last reported activity and issue count, and offers cancellation. A warning
after two minutes without reported progress is a diagnostic hint, not proof that
a worker is dead: Shopware/network operations can be slow. Polling failures show
an explicit warning and retry without pretending the last-known state is current.
Cancelled/failed queue runs stop the export spinner even if they never reached
the handler. The same progress card is used on the Import tab.

## 5. Choose status and publish

The publication modes are:

- **Keep existing status:** existing active status is left unchanged; newly
  created products are inactive.
- **Activate:** explicitly writes active status.
- **Deactivate:** explicitly writes inactive status and reduces visibility in
  the selected channel. It does not delete the product.

The selected channel receives a visibility association. Changing filters later
does not delete, deactivate or unassign previously published products. Updating
category/property/media/configurator assignments is additive/upsert-based;
destination-only associations are not implicitly removed.

Click **Publish preview**, review the confirmation, then confirm. Publication
uses the existing Messenger queue and standard run history/logging/cancellation.
Parents publish before their variants. A failed parent blocks its children;
ordinary per-product API errors are recorded without discarding other successes.

Stable tenant/connection identities, existing association IDs and persisted
external-ID mappings prevent a replay from creating duplicate listings. Already
completed messages are ignored. A worker interruption can resume the same run;
completed item rows are retained. If an API result is uncertain or destination
data changed, review the item and build a fresh preview rather than overwriting
unseen changes. External writes cannot be rolled back as one database transaction.

## 6. Save rules, Sync now and automatic publication

**Save** stores configuration. It does not publish immediately in manual mode.
**Build export preview → Publish preview** remains the explicit review workflow.

**Sync now** asks for confirmation and queues a fresh preview followed by
publication of the saved scope. **All products + Sync now** is the deliberate
full-catalogue action. The entire scope is processed in bounded batches; the
browser never downloads all products. A validation error blocks that batch/run
before product writes; successful source/target checks are not bypassed.

Enable **Automatically sync this saved scope**, then save, to authorize future
queued product writes under those rules. First use Sync now for the initial
catalogue. The Scheduler dispatches a database-only coordination tick every
five seconds. This does not fetch every catalogue every five seconds:

1. Tenant/connection/product-coalesced change hints prioritize ordinary product,
   translation, assignment and media changes. Saving a product only writes the
   durable hint; it does not wait for Shopware. Edits debounce for five seconds
   after the latest change, with a thirty-second cap for continuous edits.
2. Up to 100 candidates are inspected per connection per scan; no more than
   25 changed candidates are queued, plus their required parent dependencies.
3. Unchanged published fingerprints are skipped. When no edit hints are pending,
   a keyset reconciliation page is scheduled about once per minute. The cursor
   eventually also detects data changed outside ordinary application writes.
4. The same frozen preview/publisher performs all validation and writes. Active
   integration runs are skipped, so periodic scans do not stack publication runs.
   A leased per-connection job prevents each scheduling tick from adding another
   reconciliation job. Oldest-dispatched eligible connections are considered
   first, with no more than 25 dispatched in a tick.
5. Publication baselines detect changes to previously owned fields in Shopware.
   Automatic publication blocks these conflicts; review a fresh manual preview
   to explicitly authorize overwriting the currently visible destination values.
6. An unchanged failing item backs off for 15 minutes instead of generating a
   run every minute. New source/settings changes can be retried sooner. Inspect
   Export, History and Logs; this is not automatic success after a failed write.

Preview, full-source preflight and publication are separate durable phases under
one history record. Preview/preflight/publication process up to 25 products per
delivery and yield after approximately ten seconds at a completed-product
boundary. This is a soft budget: one slow product,
its dependencies or image upload can take longer. A checkpoint and the next queue
message are committed together. Delivery tokens ignore replayed older chunks;
published rows and checked preflight rows are retained across interruptions.
No product writes start until the whole preview has passed source preflight.

Manual previews, Sync now and historical imports use the bulk queue. Automatic
catalogue work has its own lane, separate from ongoing Sales polling and stock
dispatch. One bulk import therefore does not occupy the Sales/stock consumers.
An active import on the **same connection** intentionally holds automatic
catalogue publication to avoid publishing while matching/identities are changing.
Historical import handlers still page within their existing jobs; they have not
been converted into the new export checkpoint workflow.

Regular Shopware API requests have a thirty-second total-duration cap, OAuth
fifteen seconds and image upload ninety seconds. Catalogue chunks encountering
429/5xx/network errors yield to a delayed message, honour Retry-After up to one
hour, and retry a phase up to five times with backoff. There is no worker sleep.
Ordinary validation/conflict errors remain visible failures. An uncertain write
does not grant permission to overwrite a changed destination on retry.
Currency/locale code lookups cache for five minutes with tenant, connection,
endpoint and credential-sensitive keys; write-time reference guards remain fresh.

Publication uses a maximum of 25 products per Sync API request, with fresh batched
target/reference reads and a second fresh-source check after dependency/media
preparation. The parent request is acknowledged before variants are submitted.
A rejected validation batch falls back to guarded single-item writes so one bad
record does not discard valid neighbours. Authentication failures stop the run;
429/5xx/network failures yield to delayed reconciliation rather than immediate
single-item retries. A durable started-write marker permits acknowledgement only
when the current destination exactly matches the intended owned fields. A later
external edit still blocks the record; the marker never permits a blind overwrite.

Each item's mapping, baseline, stock-outbox entry, published/failed state and
progress counters commit atomically. Older non-atomic runs repair their counters
once per phase. Subsequent chunks do not recount the entire export plan. Partial
work indexes match parent-first ordering and skip already checked/published rows.

The Export screen shows pending edit hints and their oldest age using the shared
alert component. This is a backlog diagnostic, not a claim that each hint still
requires a write: fingerprints may discard unchanged hints. See
[queue operations](sync-queue-operations.md) for all worker commands, safe
deployment and monitoring.

Automatic synchronization is eventual, not instantaneous. Large initial
catalogues and bulk edits take multiple queued batches. Direct database changes
and reference-only edits may wait for the reconciliation cursor; its cycle time
depends on catalogue size and worker capacity. Disabling automatic synchronization
or changing configuration invalidates its queued plans before further writes.
Changing scope never deletes or unpublishes products that leave it.

## 7. Check results and stock

Use **Export** for per-product results and **History / Logs** for run progress,
counts, cancellation and failure reasons. A run can finish processing while its
plan is marked failed because some products failed; check the failure count,
not just the run's terminal status.

Catalogue publication never changes Connect warehouse balances. New Shopware
products start with stock zero; updates omit Shopware stock. If the connection
already uses **Connect stock authority**, successful publication queues the
existing inventory outbox to send the configured available stock separately.
Its warehouse selection, reconciliation and ownership safeguards still apply.

Cancellation stops at operation boundaries. Products/images already written to
Shopware stay written and remain visible in the operational history. It is not
an undo action. An image whose content no longer matches its stored checksum is
blocked; upload it as a new image instead of changing files behind the system.

## Scope and remaining acceptance

Browser acceptance on 2 October 2026 verified the shared Export screen in the
existing Chrome session: selecting one existing QA product, saving, building a
preview, confirming publication and seeing **one update, zero creates/failures**.
Preview took 15 seconds; the publication run displayed 29 seconds. Explicit
**All products** switched to whole-catalogue scope without enumerating every
product; **Reset selection** returned to the empty selected scope. All was not
saved/published during this check. Original settings were restored and verified.
The shared Woo controls also passed preview cancellation, payload review and
publication. These visual checks do not replace a production capacity/security
assessment or full fresh-media acceptance.

This first publication scope does not export brands as a Shopware extension,
advanced price rules, downloads, recommendations/cross-selling, source-specific
plugin configuration, customers/orders/documents, complete SEO URL ownership or
warehouse/multistock platform extensions. Unchecked and unsupported field groups
are not a promise of full catalogue round-trip parity. Woo destination export
remains a later milestone. A signed Shopware App/webhook bridge is optional later
work; current catalogue writes use the Admin API, and current Sales events use
the already documented polling path. No extension was installed by this work.

The selected 100-product content-update acceptance run and live draft creation,
reference creation, cover assignment, custom-field and automatic-update checks
have passed. This is not a load test of every optional field on every product.

Queue/chunk regression verification (2 October 2026): backend suite **78 tests,
867 assertions** passed; frontend typecheck, focused component lint, container
lint and diff checks passed. A 55-product fixture validates checkpointed
preview/publication, duplicate delivery, delayed 429 recovery and an edit on a
later preflight page blocking every destination write. Separate fixtures verify
tenant leases/debounce and Scheduler redispatch into the intended lanes. Local
database migrations were applied. These are automated fixture checks, not a new
live throughput measurement. The local lane consumers were subsequently started
and refreshed, and the 2 October scale/live checks are documented in
[queue operations](sync-queue-operations.md#6-measured-local-acceptance--2-october-2026).
Broader production-scale acceptance, live activation/deactivation and fresh
binary-image upload coverage remain release checks. More sophisticated pricing
remains a separate planned capability. Distributor access is still deferred,
not fabricated for this test.

## Verified evidence

- Automated tests cover read-only preview, draft creation, stable replay,
  no stock overwrite, source-edit rejection, tenant isolation, category/brand
  selection, descendant/variant switches and required-parent/name handling.
  They also cover missing-reference planning/creation and replay, automatic
  destination-conflict protection, bounded batches, new matching products,
  unchanged-item skipping, failure backoff and queued-plan invalidation after
  disabling automatic sync or changing the endpoint.
- The disposable Shopware connection was previewed and published through the
  UI confirmation and the normal handler: parent **M0058** and variant
  **M0058.8**, **2 updated, 0 failures**.
- All **6,603** local inventory balances retained checksum
  `6eebec48477a8c14fad5ddae79ec762c` across that publication.
- A disposable QA product was previewed without remote writes, then created
  inactive with stock **0**, ten newly created catalogue dependencies, a cover
  assignment using existing media and a translated text custom-field value.
  The existing Scheduler and normal worker subsequently published its local
  edit automatically: **1 updated, 0 failures**. Its cover/custom-field value
  remained intact; active status stayed false and stock stayed zero.
- A normal UI **Sync now** run updated **100 existing mapped parent products**,
  content only, **0 creates, 0 failures**, in **2m 20s** of worker execution.
  This does not claim fresh image upload or all-field/variant load coverage.
- The original saved export scope was restored after testing; the inactive QA
  records remain available for inspection. The inventory checksum above was
  unchanged after all live checks. The original automatic-sync preference and
  saved scope are restored; the acceptance test never expands publication to all.
- Latest full backend regression: **79 tests, 887 assertions**; frontend typecheck
  and changed-component lint passed. Production security/load acceptance is
  separate from these local checks.
- On 2 October the five queue lanes were active. A controlled ordinary edit of
  the inactive QA product published in about **9 seconds**, **one update / zero
  failures**, without changing stock or inventory. A real-DB, simulated-HTTP
  20,000-product fixture completed correctly with 7,219 API calls rather than
  82,417 before batching. See the benchmark boundaries and full results in the
  queue operations guide; this does not establish a production SLA.

## Maintenance commands

Production runs are consumed by the existing supervised Messenger workers.
These scoped commands are diagnostic alternatives, not a second queue system:

```sh
php bin/console app:catalogue-export:preview TENANT_UUID CONNECTION_UUID
php bin/console app:catalogue-export:run TENANT_UUID RUN_UUID
php bin/console app:catalogue-export:run TENANT_UUID RUN_UUID --allow-publish
```

The first creates a read-only preview. The second consumes an existing preview
run. The last processes an already-confirmed publication run and requires the
explicit flag because it writes to Shopware. They use the same tenant-scoped
handler and locks and do not restart the browser, frontend or API.
