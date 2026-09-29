# Multi-warehouse and purchasing plan

## Purpose

SteelCode Connect is the merchant's inventory system of record. The merchant
is the tenant; customers are buyers from a connected sales channel, and
suppliers are businesses the tenant buys stock from. Shopware-specific
extensions and connector behaviour are deliberately outside this milestone.

Every tenant has a protected **Default warehouse**. A one-location merchant
uses it without seeing extra complexity. A multi-location merchant operates
the same inventory model across several warehouses.

## Inventory rules

- Stock is held per product and warehouse, never as an untracked product-wide
  number.
- `available = on hand - reserved - unavailable`.
- Incoming stock is informative and cannot be sold until received.
- Current balances are fast read models. Inventory movements are immutable
  audit records and are the source for every change.
- A product's channel quantity is the sum of sellable available stock across
  warehouses enabled for fulfilment.
- The Default warehouse cannot be deleted, renamed by code, or deactivated.
- A warehouse cannot be deactivated while it holds stock, reservations, or an
  open transfer.
- Sales prices and supplier costs are different records. A supplier offer must
  never silently change a selling price.

## Core vocabulary

| Term | Meaning |
| --- | --- |
| Tenant / merchant | The business using SteelCode Connect. |
| Customer | A buyer from the merchant's shop or sales channel. |
| Supplier | A business supplying goods to the merchant. |
| Warehouse | A physical, virtual, 3PL, or returns location that holds stock. |
| On hand | Physically received inventory at a warehouse. |
| Reserved | On-hand inventory allocated to an unfulfilled sales order. |
| Unavailable | On-hand inventory held for damage, quality control, or safety stock. |
| Available | Inventory that can be sold now. |
| Incoming | Confirmed inventory expected from a PO or transfer. |

## Delivery sequence

### Phase 1 — multi-warehouse foundation

1. Extend warehouses with an address, fulfilment flag, priority, and protected
   Default semantics.
2. Extend inventory levels with unavailable and incoming quantities while
   retaining on-hand and reserved quantities.
3. Make stock mutations explicit operations: adjustment, receipt, transfer
   out, transfer in, reservation, release, shipment, return, and damage.
4. Add a product warehouse-stock view with totals and per-warehouse balances.
5. Preserve the current simple stock form by routing it to Default warehouse
   when a merchant has one active fulfilment warehouse.

**Acceptance:** two warehouses can hold different quantities for one product;
the product total is correct; an adjustment creates a movement; Default
warehouse remains safe.

### Phase 2 — warehouse operations

1. Add stock transfers: Draft → In transit → Received / Cancelled.
2. Create paired transfer movements. Origin stock is unavailable while goods
   are in transit; destination on-hand changes only on receipt.
3. Add stock counts with expected, counted, approved, and adjustment records.
4. Add movement filters and references for warehouse staff audits.

**Acceptance:** transfers never create or lose stock, and all changes can be
traced to a document and user.

### Phase 3 — suppliers and purchasing

1. Add supplier master records, contacts, addresses, currencies, and active
   state.
2. Add product supplier offers: supplier SKU, purchase unit, MOQ, lead time,
   cost, currency, validity, and preferred flag.
3. Add purchase orders: Draft → Sent → Partially received → Received /
   Cancelled.
4. Confirmed PO lines contribute to incoming quantity. Goods receipts create
   on-hand stock only for quantities actually received.
5. Support partial receipts, damaged receipts, and supplier backorders.

**Acceptance:** a PO can be received in several deliveries and inventory stays
correct at every step.

### Phase 4 — replenishment

1. Set per-product/per-warehouse reorder point, target quantity, and lead
   time.
2. Show low-stock and incoming-stock views.
3. Generate purchase-order suggestions using available plus incoming stock and
   the preferred valid supplier offer.

### Phase 5 — sales allocation

1. Add customers and sales orders only when order synchronisation or native
   ordering starts.
2. Reserve stock for orders, release on cancellation, and deduct on shipment.
3. Allocate by warehouse priority, then full-order availability; split
   fulfilment is an explicit later setting.

## Implementation boundaries

- Phase 1 is native SteelCode work and does not require Pickware, Shopware,
  WooCommerce, or a connector plugin.
- External connectors later consume the calculated aggregate available stock
  unless a dedicated multi-location integration is intentionally enabled.
- Accounting valuation, supplier invoices, serial/lot tracking, and advanced
  demand forecasting are separate future milestones.

## Delivered in the current milestone

- Protected Default warehouse, per-warehouse stock balances, transfer and stock-count documents.
- Reviewable count variances, confirmation before posting, inventory row versions, and rejection of stale count drafts.
- Reviewable transfers and cancellation of drafts; sent transfers require receipt.
- Supplier master records with a primary contact/address and searchable product offers with cost, currency, supplier SKU, minimum quantity, purchase-unit conversion, validity dates, lead time, and one active preferred supplier per product.
- Purchase orders with editable drafts, snapshotted line costs and purchase-unit conversion, Draft → Sent → Partially received → Received / Cancelled, incoming balances, idempotent audited partial/damaged receipts, and immutable good-stock movements.
- Supplier-facing PO PDF, explicit email delivery with a last-sent audit timestamp, and damaged-receipt resolution history. Marking a PO sent and emailing it are distinct actions.
- Server-paged count, transfer, offer, and PO listings using the shared compact table; lazy product/supplier/offer selectors using the shared searchable select.

Supplier invoice matching/payment, multi-contact or multi-address supplier records, physical quarantine of damaged goods, automated replacement orders, Phase 4 replenishment, and Phase 5 sales allocation are separate future work. The current damaged-goods flow tracks supplier claims but does not book damaged goods into a warehouse.
