# Connect → WooCommerce catalogue publication

Updated: 2 October 2026.

This is outbound catalogue publication, not a customer/order migration or a
complete bidirectional mirror. Woo imports and Sales/stock operations remain
documented in [the Woo operating guide](woocommerce-import.md). Shopware and Woo
now use the same durable export workflow, selection rules, progress/history,
change reconciliation and queue lanes, with separate provider payload adapters.

## 1. Prepare the destination

1. Open the existing Woo connection in Integrations and test its credentials.
   The native Woo REST API user needs read/write access to products, variations,
   categories, attributes/terms, supported brands, media sideload and store/tax
   settings. An inbound-only credential is not sufficient for publication.
2. Ensure the destination store currency is enabled in this tenant and that the
   products have selling prices in that currency. No exchange rate is guessed.
3. Configure `CATALOGUE_MEDIA_BASE_URL` as the externally reachable HTTPS base
   URL of the Connect API before exporting images in production. See section 6.
4. Run the five supervised queue lanes described in
   [queue operations](sync-queue-operations.md). Manual work uses `async`;
   automatic publication uses `catalogue`. No Woo plugin is required for the
   supported native REST publication workflow.

## 2. Save what should be published

1. Open the same **Export** tab used for Shopware. Woo does not require a
   Shopware sales-channel ID: its connected store is the destination.
2. Choose **Selected** or explicitly choose **All**. Empty Selected filters
   never mean the whole catalogue. Category, brand, manufacturer and individual
   product rules are combined as selection rules: category/brand/manufacturer
   filters intersect, while individually selected products are added to that
   result. Exclusions are applied afterwards. Manufacturer is a selector, not a
   native Woo manufacturer field.
3. Decide whether category descendants and product variations are included.
   Required parents accompany selected variations. Automatic All scope can
   include future products; selected category/brand rules can include future
   matching products without individually adding every ID.
4. Select the catalogue language used to provide Woo's single native name and
   descriptions. This is not WPML/Polylang multilingual export.
5. Choose supported field groups, optional percentage price adjustment and
   publication status. Keep mode preserves existing status and creates new
   parent/simple products as drafts. Explicit activation can publish products.
6. Decide whether missing supported references may be created. Otherwise,
   missing references block preview and must be imported or explicitly mapped.
7. Save. Changing scope/settings invalidates a previously frozen preview.

The same selectors, All/reset controls, table, progress card and Mapping editor
are reused. Mapping is **identity/reference mapping**, not arbitrary CSV-column
matching. Existing per-connection import mappings are reused. Manual overrides
can bind categories, brands, global attribute groups, tax classes and products.
Global terms retain their group/term identities; local Woo attributes retain
their native name/option representation. SKU/name similarity alone does not
authorize adopting an unrelated destination product.

## 3. Preview, publish and inspect results

1. Build export preview. This queues a read-only preview and returns immediately.
   Follow its persisted phase and counts in the shared progress card.
2. Review paginated Create/Update rows and their validation issues. Preview
   freezes source payload fingerprints and destination-owned-field fingerprints;
   it does not upload images or create taxonomies/products.
3. Correct invalid prices, missing references, conflicting IDs or missing stored
   image files/checksums, then build a new preview. Nothing silently substitutes
   the wrong currency, tax rule or product identity.
4. Confirm Publish, or use Sync now for the saved scope. Sync now still performs
   queued preview and preflight; it does not bypass validation.
5. Review per-product results, History and Logs. A native Woo batch can return
   HTTP 200 with individual errors; these are recorded separately. Other valid
   items continue. Cancellation stops future work, not already committed writes.

Publication creates missing approved dependencies before products. Category
parent chains and global attribute groups/terms use deterministic connector
identities. Parents are acknowledged before their variations are sent through
the parent-specific variation API. Mapping and publication acknowledgment are
persisted transactionally in Connect.

## 4. Supported product fields and boundaries

| Group | Native Woo publication |
| --- | --- |
| Identity/content | SKU, simple/variable structure, parent name/short description, descriptions, barcode/global unique ID, virtual flag, parent featured flag, selected status |
| Prices | Destination-currency regular/sale prices, optional percentage adjustment, supported Woo-origin UTC sale schedule, mapped/unambiguous non-compound tax class |
| Classification | Categories, native core brands, tags, global/local attributes, variation combinations and parent defaults/attribute flags |
| Fulfilment | Weight and dimensions converted from canonical units to destination store units |
| Metadata | Supported portable custom-field values using original Woo keys or explicit technical-key mapping; existing metadata IDs are reused |
| Images | Ordered parent gallery/cover and variation image through short-lived signed tenant media URLs; unchanged published images reuse attachment IDs |

Woo global attribute collections do not provide native page controls. The
adapter filters their native collection and exposes pages of 25 in the shared
selector; product/category/brand collections use native pagination.

Private WordPress internals and `_woocommerce` source snapshots are not exported
as arbitrary metadata. Unrelated destination metadata is not cleared. Native
Woo does not offer Shopware purchase units, delivery times, rule/tier pricing or
manufacturer semantics; those are not invented. This milestone does not export
downloads, recommendation groups, plugin SEO/multilingual extensions, grouped
or external product behavior, customer/order history or documents. Unsupported
grouped/external products are blocked rather than silently converted to simple.
Complex/location-specific tax configurations require an explicit adapter;
more than 100 tax rules currently block the supported tax snapshot path.
Variable parents receive their tax status even though their selling prices are
derived from variations. Variations inherit parent tax status; mixed taxable
and non-taxable children are blocked rather than claiming Woo can apply a
per-variation status. Compatible variation tax classes remain separately mapped.

## 5. Automatic updates, replay and performance

Enable Automatic sync only after validating the saved scope. A tenant catalogue
edit coalesces a durable hint; saving does not wait for the destination. The
shared coordinator debounces for five seconds after the last change, with a
thirty-second cap for continuous edits. Reconciliation checks bounded candidate
pages, fingerprints supported source data and queues changed products plus
required parents. A periodic bounded sweep catches edits without hints.

Preview, preflight and publication yield after at most 25 items or a soft
ten-second budget. Woo batches contain at most 25 products, or variations for
one parent. A slow HTTP request/dependency step can exceed the soft budget.
Provider errors/rate limits delay resumable messages rather than repeatedly
POSTing or sleeping in a consumer. Per-tenant/connection locks, work tokens,
active-run coalescing, leases and failure backoff prevent concurrent duplication.

Started-write markers and a connector identity marker support recovery after
an uncertain remotely committed create. Recovery requires matching ownership
and payload evidence; ambiguity blocks instead of creating another product or
adopting a random SKU. Replaying a completed work token does no extra write.

Automatic updates protect destination-owned fields changed since the last
publication. Reads use Woo's authenticated **edit** context, so storefront HTML
filters do not create false conflicts. A manual preview can review/re-baseline
an intentional overwrite. This is not automatic conflict merging, remote
category/brand renaming or remote deletion when an item leaves the saved scope.

Stock is deliberately separate. Catalogue payloads never send `stock_quantity`
or change `manage_stock`; existing inventory authority/reconciliation/outbox
rules control stock publication. Historical imports do not change inventory.
Allowing test stock overwrites does not remove these production safety guards.

There is no fixed update-time guarantee for 20,000+ products or many tenants.
Only changed supported payloads need publication, and bulk imports do not occupy
the Sales/stock workers. Measure backlog, provider latency, media traffic,
memory and database capacity before increasing concurrency. See queue operations
for monitoring and tenant isolation; hard per-tenant resource quotas are not yet
implemented.

## 6. Signed media delivery and local development

Each URL permits one tenant/connection/media/checksum snapshot for fifteen
minutes. The endpoint requires a valid HMAC, active channel connection, matching
tenant/media and file name/checksum, image MIME type and a confined stored file
path. It does not require exposing a user's session. Do not log or share its
capability query string. URLs are minted at publication time, not when preview
is built. Production uses the normal public HTTPS API/proxy route.

The current local PHP API on port 8000 has no router for dynamic paths ending
in image extensions. A separate media-only listener leaves that API untouched:

```bash
php -S 127.0.0.1:8001 -t public public/catalogue-media-router.php
```

Use `CATALOGUE_MEDIA_BASE_URL=http://localhost:8001` only for this local setup.
WordPress normally blocks private hosts and nonstandard ports when sideloading.
The repository fixture `backend/tests/Fixtures/woocommerce-local-media.php`
temporarily allows only that exact signed-image endpoint for disposable local
acceptance. **Never install it in production.** It was removed from the demo
WordPress shop after acceptance. Future local tests with new image uploads need
an equivalent explicitly scoped allowance or a publicly reachable HTTPS URL.
Already mapped, unchanged images do not need to be downloaded again.

## 7. Acceptance evidence — 2 October 2026

- Live `wp-test.test`: a clearly marked QA variable product and Blue variation
  were created as Woo IDs **26730** and **26731**. Their category, brand, global
  colour group/term, image and portable metadata were created/mapped correctly.
- Destination settings reported **BAM**. The fixture used an actual BAM selling
  price, not an assumed currency or conversion.
- A real Connect name edit was picked up by the automatic catalogue lane and
  published to product **26730** with the same ID and image count. Its run
  completed with one update and zero failures.
- Replaying the completed initial run remained completed with two successes
  and no new product creation. Authenticated UI progress API reported completed
  automatic publication.
- Regression tests cover simple/parent+variation creation/update, remote-commit
  timeout recovery, replay, tenant isolation, stock preservation and destination
  conflict blocking. Additional tests cover signed-media tampering/expiry and
  attribute-selector pagination.
- Final backend suite: **88 tests, 1145 assertions**. Frontend typechecking,
  container validation and diff checks passed.
- Subsequent browser acceptance succeeded using the existing Chrome session.
  It covered checked product/variant selection and SKU search, preview progress,
  queued-run cancellation, payload review, publication confirmation and a
  completed two-product update with zero failures. Mapping correctly identifies
  **WooCommerce product ID** rather than Shopware. The same shared controls were
  checked on Shopware, including explicit All and Reset selection behavior.
  Manual mapping add/save/reload/remove was tested with the existing QA category;
  the original settings were restored. The shared component normalizes empty
  PHP mapping arrays to object dictionaries so the first mapping appears and
  persists instead of being lost during JSON serialization.
- A normal API/queue mixed run covered **90 simple products, 10 variable parents
  and 26 variants**. All 126 completed without creates/failures; a clean replay
  took **144.88 seconds**, including the post-run remote verification. All
  mapped IDs, stock, statuses and images were retained; 99 records had images.
  Initial diagnostic runs found two bugs: blank destination SKUs were missing
  from final SKU-only conflict reads, and reading multiple variation parents in
  one write chunk could exhaust the deadline without progress. Publication now
  fetches missing mapped IDs and batches at most 25 variations of one parent.
  Regression coverage includes multiple parents and blank mapped SKUs.
- The first successful diagnostic run took 779.04 seconds while fixes/workers
  were being reloaded; it is not a throughput benchmark. The clean replay above
  used the corrected worker throughout. The repeatable disposable-shop script
  is `backend/tests/Acceptance/woo-mixed-publication.php`; it restores the saved
  export scope. It publishes content only and verifies image preservation,
  not fresh image uploads, production load or multi-tenant capacity.

The demo connection remains scoped to the selected QA products with automatic
publication enabled. It does not silently export all existing imported products.
