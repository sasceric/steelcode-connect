# Warehouse operations guide

This guide covers the current multi-warehouse workflow for a SteelCode Connect merchant.

For the full step-by-step reference, including customer/order import, Shopware
shipment ownership, ongoing synchronization, stock authority, troubleshooting
and deployment, use the [Inventory and Sales operating guide](inventory-and-sales-guide.md).

## 1. Configure warehouses

1. Open **Inventory → Warehouses**.
2. Keep the protected **Default** warehouse active. It is the fallback location for integrations that only provide one stock value.
3. Add each real location with a clear name and code.
4. Enable fulfilment only for locations that can ship customer orders.
5. Set the fulfilment priority: lower values are tried first for sales-order allocation. One order is reserved from one warehouse that can supply all its lines; the system does not silently split an order between locations.

### Deactivate a location safely

1. Resolve all nonzero stock, reserved, unavailable and incoming balances. Use
   transfers, receipts, return inspection or audited corrections as appropriate.
2. Complete/release reserved sales-order allocations and inspect quarantined returns.
3. Complete or cancel open POs, draft counts and draft transfers. Receive goods
   already in transit; both transfer endpoints are protected.
4. Open the warehouse's **Edit** action, turn **Active** off, and save.
5. If work remains, saving is rejected and the shared edit modal shows an
   explanation. The warehouse stays active. Completed/cancelled history does
   not block an empty location. The Default warehouse cannot be deactivated.

Concurrent stock/document operations hold a shared warehouse row lock;
deactivation holds an exclusive lock while checking and saving. Inactive
locations reject new stock operations, POs, transfers and counts. Reactivate
an empty location before using it again; deactivation never deletes its history.

## 2. Understand stock states

- **Stock**: physical units currently recorded in a warehouse.
- **Reserved**: units committed to an order; they cannot be allocated again.
- **Unavailable**: units physically present but not saleable, such as damaged or quarantined stock.
- **Available**: stock minus reserved and unavailable quantity.
- **Incoming**: outstanding units on sent purchase orders. Incoming stock is not available for sale.

Product detail shows both the total and the location-by-location breakdown.

## 3. Move stock between warehouses

1. Open **Inventory → Transfers** and create a draft.
2. Select different source and destination warehouses.
3. Add products and their exact quantities.
4. Review the draft. The source must have sufficient available stock.
5. Choose **Send transfer** when the goods leave the source. This creates immutable transfer-out movements.
6. Choose **Receive transfer** only when the goods arrive. This creates immutable transfer-in movements at the destination.

A draft can be cancelled without moving stock. Once sent, receive the goods rather than cancelling the transfer; this keeps the source and destination audit trail intact.

Do not use a manual adjustment to represent a normal warehouse transfer: it loses the connection between the two locations.

## 4. Reconcile physical stock with a stock count

1. Open **Inventory → Stock counts** and choose **Create count**.
2. Choose the warehouse being counted.
3. Add only the products that were physically counted.
4. Enter the actual physical quantity for each product.
5. Save the draft and review its variance:
   - positive variance means more was counted than expected;
   - negative variance means less was counted than expected.
6. Open the draft to review every product's expected quantity, counted quantity, and variance.
7. Choose **Post count** once the count is approved and confirm the action.

Posting is final. It updates stock to the counted amount and writes adjustment movements with the count ID to product stock history. A posted count cannot be edited; start a new count to correct a later discovery. If any stock line changed since the count was created, posting is rejected for the entire count. Create a fresh count rather than overwriting the later movement. Counted stock also cannot be less than already reserved and unavailable stock.

## 5. Record supplier offers

1. Open **Inventory → Suppliers** and add the business you buy from. This is not your shop customer and not the merchant account using SteelCode Connect. Add its contact person, email, phone, and postal address so they appear on the purchase order.
2. Configure the needed currencies and purchase units in the product currency catalogue and **Shop → Units** first. A case/carton can be added as a unit if needed.
3. Open **Inventory → Supplier offers**, choose a supplier and select one or more products. The product selector searches by name or product number and loads 25 at a time. Each selected product appears as a compact row; expand **More details** only where needed. Saving the batch is atomic.
4. For each supplier-product relationship, set the supplier's true **minimum order quantity** in purchase units. This is an ordering rule, not a price-break threshold. Optionally record the supplier SKU and lead time. These terms are supplier-specific and are not copied from the product's selling price.
5. Choose a configured purchase unit and enter how many stock units it contains. For example, one case may contain 12 pieces, while one bottle may contain 0.75 litres. Fractional conversions are supported to four decimal places. A PO or receipt is rejected if its conversion would require finer stock precision; adjust the entered quantity or unit definition rather than silently rounding stock.
6. Enter one or more **price tiers**, each with its own minimum quantity for that price, unit cost, configured currency and optional validity dates. The tier threshold is *not* the supplier's minimum order quantity. For example, the supplier may accept 2 cases, charge €10 from 1 case, and €8 from 10 cases. You may leave a supplier-product relationship without a price while awaiting a quote; it will not be orderable until a valid tier exists. Zero is an explicit free price, not a substitute for an unknown price.
7. Validity dates say *when a quoted price may be used*. Lead time instead says how many days the supplier normally needs *after an order is placed* to deliver. Existing dated tiers are kept when adding future prices; avoid overlapping periods for the same currency and price threshold.
8. Mark a supplier-product relationship as preferred when it is the primary source for that product. Only one active relationship per product can be preferred. If this supplier quotes in several currencies, explicitly choose the currency for automatic replenishment; manually created POs can select any currency with a valid price tier.

Supplier cost is separate from the product's selling price. Changing an offer never changes a selling price or the cost already snapshotted on an existing purchase order.

## 6. Create and receive a purchase order

1. Open **Inventory → Purchase orders** and choose **Create purchase order**.
2. Select the supplier and destination warehouse. The Default warehouse works for a one-location merchant.
3. Select products from that supplier's active offers. The selector searches by name, product number, or supplier SKU and loads more offers as you scroll.
4. Choose the PO currency and enter quantities in purchase units, at or above each supplier's minimum order quantity. The system picks the currently valid price tier with the highest threshold that the quantity meets, in the selected currency. All lines on one PO use that currency. Save the draft, then review its lines and total. You may edit the supplier, warehouse, note, lines, and quantities while the PO remains a draft. Saving a draft re-evaluates current price tiers and snapshots the selected cost and conversion on each line.
5. Download the supplier-facing PDF and review the supplier, destination, quantities, conversion, costs, and notes. The PDF uses the supplier details currently recorded for a draft.
6. Choose **Mark as sent** only when the order has been placed with the supplier. This freezes the supplier contact/address on the PO and moves converted open quantities to **incoming** at the destination warehouse; on-hand stock has not changed. **Mark as sent does not email the supplier.**
7. To send from SteelCode Connect, choose **Email PO to supplier** and confirm the recipient shown in the dialog. This sends the PDF to the email captured when the PO was marked sent and records the last emailed time/address. Alternatively, deliver the downloaded PDF yourself. A configured outbound mail sender is required for the email action.
8. When a delivery arrives, choose **Receive goods**. Enter the good and damaged quantities in purchase units for the lines actually delivered. Leave undelivered lines at zero.
9. Save the receipt. Good quantities are converted to stock units, increase on-hand stock, and create receipt movements. Damaged quantities reduce incoming, enter *on-hand and unavailable* stock, and remain unsellable while physically held. A damaged line opens a supplier claim in its receipt history.
10. For damaged goods, record both the **Damage resolution** (supplier commercial claim: returned, credited, written off, or replacement agreed) and the **Physical damaged stock** disposition (returned to supplier, scrapped, or released for sale). These are separate decisions: a credit does not physically move goods, and returning goods does not automatically record a credit. A physical return or scrap reduces on-hand and unavailable stock; release only reduces unavailable stock. Every action has a stock movement or receipt audit event. Historic damaged receipts from before physical quarantine was introduced are labelled *not booked*; the system does not invent stock for them.
11. Repeat for later deliveries. The PO remains **Partially received** until all ordered quantities are accounted for. Outstanding quantities represent the supplier backorder. Retrying the same receipt after a network failure is safe: the same delivery key cannot book stock twice.

A draft PO can be cancelled with no stock effect. Cancelling a sent or partially received PO removes only its outstanding incoming quantity; previously received good stock and receipt history remain intact. You cannot receive more than the outstanding quantity.

## 7. Replenish stock

1. Open **Inventory → Replenishment** and add a policy for each product/warehouse that should be reordered automatically. Enter a reorder point and a higher target stock quantity. Optionally override the supplier's lead time.
2. The **Suggestions** view uses *available + incoming* stock position. It shows a suggestion when that position reaches or falls below the reorder point.
3. Ensure the product has a preferred supplier-product relationship and a current price tier in its default purchasing currency. The suggested order is rounded up to complete purchase units and at least the supplier's minimum order quantity and the lowest currently priced threshold.
4. Select suggestions and choose **Create draft POs**. Drafts are grouped by supplier, warehouse, and currency. A product already in a draft PO for the same warehouse is rejected to avoid duplicate ordering.
5. Review and adjust each draft PO before sending. Creating a suggestion does not send an order, email a supplier, or change on-hand stock.

## 8. Match supplier invoices

1. Receive the goods first, then open **Inventory → Supplier invoices** and choose **Add invoice**.
2. Choose the related sent PO, enter the supplier's invoice number and date, and review the lines. The form suggests good received quantities not yet covered by a *matched* invoice. Damaged goods are not automatically billable. Remove or add lines as needed for a partial invoice.
3. Enter the supplier's billed quantity and unit cost for each line, plus tax, shipping, discount, and the invoice's stated total. The displayed calculated total is a check, not a replacement for the stated total.
4. Save a draft. Choose **Match invoice** to check the cumulative billed quantity against good receipts, each unit cost against the PO snapshot, and the stated total against the calculated amount. A discrepancy sets **Disputed** with explicit reasons. Correct the draft after resolving the discrepancy with the supplier, then match again.
5. A matched invoice is immutable. If it was entered in error, **Void invoice** with a reason. The audit record remains, and its quantity no longer counts toward matched billed goods. Re-enter the corrected invoice as a new record. Invoice numbers must be unique per supplier and year.

Matching does **not** pay the supplier, post to a general ledger, validate a tax rate against tax law, or replace accounting software. It is an operational three-way check against the PO and physical goods receipt.

## 9. Recommended routine

- Use transfers for location-to-location movement.
- Use a stock count for shrinkage, non-PO discrepancies, or periodic reconciliation. Record PO delivery damage on the receipt, not as good stock followed by an adjustment.
- Keep a short note on a count or adjustment to explain material variances.
- Review stock history before correcting a surprising balance.

## 10. Pick, ship, and return customer orders

1. Import historical orders or enable ongoing sync for new orders. Open **Sales → Orders** and review the source, customer/buyer snapshot, lines, payments and deliveries. Historical imports never reserve or deduct today's stock.
2. Ongoing sync automatically tries to reserve new orders admitted after activation. The allocator chooses the first active fulfilment-enabled warehouse, by priority, that can cover every line. If none can, the order remains unallocated and appears in **Inventory → Exceptions**. Fix stock or mapping, then retry allocation there.
3. Open the order's pick list or **Sales → Pick lists**, start the recorded task, enter actual picked quantities, save progress and confirm picking. Picking changes no stock. Record a short pick if the shelf contains fewer units than expected. Currently the owner performs these mutations; staff roles remain deferred.
4. Confirm the actual shipment in **Shopware**, with tracking where appropriate. Connect observes delivery quantities on an ongoing poll and deducts only newly shipped units, releases their reservations and writes movements. Remaining units stay reserved. If a partial-delivery state lacks exact shipped quantities, use **Reconcile partial shipment** in the order's **Details** tab and enter cumulative shipped totals. There is no ordinary Connect Ship action that pushes a shipment to Shopware.
5. For a customer return, open the order's **Returns** tab and select the original shipped line and warehouse. Enter only units physically received. The form limits the quantity to shipped units not already returned.
6. Choose quarantine while inspection is pending: on-hand and unavailable increase together, so available stock does not rise. Resolve it to **Restock** for inspected saleable goods or **Write off** for unsaleable goods. The receive form also permits immediate restock/write-off when that decision has already been made. Damaged or unknown-condition goods cannot be released as saleable. Retries with the same request key do not book stock twice. Historical orders without Connect shipment allocations have no native returnable quantity.

A physical return does not issue a refund, create a credit note, or change the Shopware order automatically. Handle those commercial actions separately in the selling platform or accounting workflow.

## 11. Resolve operational exceptions

Open **Inventory → Exceptions** to review unmatched order products, orders without allocation, shipment reconciliation, failed stock or Sales sync, import failures, stock discrepancies, and quarantined returns. Use the row action only for the supported safe retries (allocation or sync). A quarantined return needs an explicit inspection and disposition on its order. Investigate stock discrepancies before changing stock authority; do not use a blind adjustment to hide a sync or mapping error.

## Current boundary

This workflow covers warehouse stock, counts and transfers; batch supplier offers; purchase orders and receipts; physical damaged-goods quarantine; replenishment draft suggestions; operational invoice matching; and order allocation, picking, partial shipping, and physical customer returns. It does not make supplier payments or customer refunds, create accounting entries or credit notes, automatically place replacement orders, or integrate external ERP plugins. For the staged Shopware Sales and stock-authority cutover, use [the cutover guide](shopware-sales-inventory-cutover.md).
