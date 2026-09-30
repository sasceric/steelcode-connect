# Shopware Sales and inventory cutover

This is the first Shopware-to-Connect order/inventory integration. Historical Sales imports remain read-only: they populate Customers and Orders but never reserve or deduct today's warehouse stock. Only orders created after ongoing sync is enabled can affect inventory.

For the complete purchasing, warehouse and Sales operating procedures, use the
[Inventory and Sales guide](inventory-and-sales-guide.md). This file focuses on
Shopware cutover and its acceptance evidence.

## Before enabling ongoing sync

1. Finish the catalogue and historical Sales imports. Map each stock-managed product and variant SKU. Do not enable ongoing sync while a historical Sales import is running.
2. Reconcile the physical quantity in each Connect warehouse. Shopware stock only seeds a product's Default warehouse when no level exists; a later catalogue re-import never overwrites a level that Connect already manages.
3. Keep **Connect manages Shopware stock** off. This prevents accidental outbound stock writes during setup.
4. Run the background worker with both `scheduler_default` and `async` transports (`composer imports:consume` from `backend/`). The scheduler queues a per-connection Shopware Sales poll every minute and also drains the stock outbox. In production, run the worker under a process supervisor and use a shared lock store if more than one host runs it.

## Activation

1. On the Shopware integration configuration page, enable **Orders** and **Ongoing order sync**, then save. The server records that moment as the cutover. Earlier orders stay historical and do not reserve stock.
2. Allow one successful poll. The first poll creates the per-connection checkpoint. Customers are updated incrementally if **Customers** is enabled.
3. Verify a newly placed test order: its product SKU resolves, Connect reserves its quantity in one fulfillment warehouse, and the available stock decreases. An unknown SKU or insufficient stock leaves the order visible as **new** and unallocated; pending orders are retried in rotating batches after products or stock arrive.
4. Only after comparing Connect's warehouse totals with Shopware, enable **Connect manages Shopware stock** and save. The application requires a recent successful Sales checkpoint and queues a stock reconciliation for published products. Outbound publication waits until Sales sync has passed the stock event's timestamp. For a large initial Shopware cutover, run `php bin/console app:stock-sync:reconcile-shopware <connection-id> --verify` from `backend/`. It sends retryable batches (100 products per request by default), marks each outbox event dispatched only after Shopware accepts the batch, and reads back every published leaf-product stock. A failed batch remains queued and can be retried. The ordinary scheduled outbox dispatcher continues handling ongoing changes.
5. Test cancellation, split deliveries, and full shipment. Cancellation releases only the unshipped reservation. A shipped delivery's positions deduct their exact product-line quantities; a later delivery deducts only the difference. The outbox publishes the aggregate available quantity of active fulfillment warehouses.

## Partial shipments and order edits

- Multiple Shopware deliveries with `shipped` state and position quantities are reconciled cumulatively by external order-line ID. Repeated polls do not deduct stock twice. Partial allocation records are split into shipped and still-reserved quantities, so the order detail shows both.
- Shopware's `shipped_partially` delivery state alone does **not** say how many units of each line physically shipped. A `shipped` delivery without positions alongside other deliveries is ambiguous too. The order remains visible and its remaining units stay reserved; no stock is guessed. On Sales → Orders → Details, use **Reconcile partial shipment** to enter the **total shipped so far per product line**. This writes an auditable warehouse movement in Connect, attributed to the signed-in user, but does not change Shopware's delivery state. Enter subsequent totals as shipments progress; never enter just the latest increment. If the source later supplies fully shipped delivery positions, those take over.
- Changes to unshipped order lines are reconciled automatically: additions, removal, SKU replacement, and quantity increases/decreases release old reservations and reserve the new outstanding quantities in the same warehouse when possible. If stock is insufficient or the new SKU has not been imported, the order stays visible with open quantity and the background sync retries allocation after stock or catalogue data arrives.
- Once units have shipped, their SKU cannot change, their line cannot be removed, and the order-line quantity cannot drop below already shipped units. These cases need a real return/correction workflow; silently rewriting the physical ledger would be unsafe. Name, price, tax, payment, and delivery snapshots can still update.

The polling checkpoint advances only after every fetched record succeeds. A failed record is logged and retried on the next run; it does not silently disappear. Each Shopware connection is locked while a poll is running. All imports remain paged at 25 records and reuse the existing queue and normalized Sales ingestion service.

## Operational boundaries

- Sales → Orders → Returns records an actual physical return against shipped
  quantities. Quarantine is included in physical quantity but unavailable for
  sale; release or write-off happens after inspection. Only sellable restock
  raises available stock. A return here does not refund the customer or change
  the Shopware order state. Use Shopware for those commercial actions.
- Inventory → Exceptions lists active stock/order/sync problems with links to
  the owning record. Retrying allocation recalculates after SKU or stock repair;
  retrying stock publication requeues the existing outbox event. Neither action
  fabricates inventory or bypasses the Sales-sync readiness gate.

- Shopware order and delivery changes are read through the Admin API; no Shopware App webhook or custom plugin is required for this polling version. This is eventual consistency, not an atomic checkout reservation. A new Shopware checkout may occur between a poll and an outbound stock update. For high-volume or oversell-sensitive shops, use an authenticated real-time adapter and a cutover test before treating Connect as the stock master.
- A Shopware `shipped_partially` status without explicit shipped line quantities needs the manual reconciliation action above. The integration cannot infer those quantities from Shopware's delivery-position `quantity`, which represents assigned units rather than the subset already shipped.
- If a Shopware order is already shipped but Connect has no mapped product or warehouse stock, the import cannot invent a physical source. Correct the mapping/stock and retry; investigate any resulting mismatch before making Connect the stock master.
- A cancellation after full shipment does not create a return automatically. Receive an actual customer return through the inventory return workflow when goods arrive.
- Documents, accounting-grade payment reconciliation, and migration/export to WooCommerce, PrestaShop, Shopify, or other platforms are separate projects.

These boundaries mean the Shopware full-order flow is testable, but the broader cross-platform or advanced-fulfillment module is not yet production-complete.

## Acceptance record (2026-09-30 local environment)

- The transactional Shopware-shaped flow passed: order ingestion, warehouse
  reservation, durable pick, partial delivery, quarantine, inspected restock,
  replay without duplicate stock, final delivery, and rejection of over-return.
  The existing cancellation and line-edit integration tests also passed.
- The exception workbench passed tests for unmatched SKUs, unallocated orders,
  failed stock publication/imports, and tenant isolation. The running app's
  Returns tab and Exceptions table were checked in the browser.
- The user confirmed `shopware67.test` is a disposable test shop and approved a
  storefront order. Shopware order **10214** (`M0058.8`, one unit, cash on
  delivery) was placed as a guest checkout, with no customer-account
  registration. **Ongoing Sales sync is enabled** on the `Shopware 6`
  connection. A queued poll imported the new order and customer, reserved one
  unit from **Glavno skladište**, and advanced its cursor without error.
- The pick task was completed for one unit. Setting the Shopware delivery to
  **Shipped** and polling again moved Connect's allocation to shipped, cleared
  its reservation, and changed the warehouse's physical quantity from **180 to
  179**. The mapped Shopware variant also finished at **179**. A test return
  entered quarantine, appeared in **Inventory → Exceptions**, and was then
  inspected as damaged and written off; the exception cleared and sellable
  stock remained **179** in both systems. This was a stock-ledger test, not a
  Shopware refund or credit-note test.
- The user confirmed all shop/order/customer records are disposable test data
  and authorized the stock reconciliation. Of 2,255 initially published
  products, 27 were variant parents, which are not directly stock-published.
  The missing parent mapping was repaired; the one demo SKU absent from
  Shopware was hidden on that channel rather than deleted. This left **2,227
  published leaf products**. The initial read-only comparison found 45 stock
  mismatches. After a fresh Sales poll, **Connect stock authority was enabled**
  and the queued stock was published in Shopware batches. Read-back of all
  2,227 leaf products found **zero mismatches**; the outbox had **2,227
  dispatched, zero pending or failed** events. Shopware's empty 204 response
  for ordinary PATCH stock updates is accepted, and the bulk path caches its
  API token per run. This validates the disposable test shop's balances; a
  production shop still needs its own physical-stock reconciliation and
  controlled cutover.
- The local `composer imports:consume` worker was started after the cutover and
  is consuming `scheduler_default` and `async`; this interactive process is not
  a durable service. Run it under a process supervisor for unattended or
  production sync. The full backend
  suite passed (36 tests, 392 assertions); frontend typecheck and build passed.
