# Warehouse operations guide

This guide covers the current multi-warehouse workflow for a SteelCode Connect merchant.

## 1. Configure warehouses

1. Open **Inventory → Warehouses**.
2. Keep the protected **Default** warehouse active. It is the fallback location for integrations that only provide one stock value.
3. Add each real location with a clear name and code.
4. Enable fulfilment only for locations that can ship customer orders.
5. Set the fulfilment priority: lower values are chosen first when fulfilment allocation is introduced.

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
2. Open **Inventory → Supplier offers** and add the supplier's product offer. Search by product name or product number.
3. Enter the supplier SKU (if any), unit cost, three-letter currency, minimum order quantity, and lead time. Cost and minimum quantity are per **purchase unit**.
4. Set the purchase unit and how many stock units it contains. For example, a case may contain 12 individual items. Ordering 5 cases creates 60 incoming stock units. If you buy single items, keep the default **unit × 1**.
5. Optionally set a validity start and end date. An offer outside its date range cannot be used on a new or edited PO.
6. Mark an offer as preferred when it is the primary source for that product. Only one active offer per product can be preferred.

Supplier cost is separate from the product's selling price. Changing an offer never changes a selling price or the cost already snapshotted on an existing purchase order.

## 6. Create and receive a purchase order

1. Open **Inventory → Purchase orders** and choose **Create purchase order**.
2. Select the supplier and destination warehouse. The Default warehouse works for a one-location merchant.
3. Select products from that supplier's active offers. The selector searches by name, product number, or supplier SKU and loads more offers as you scroll.
4. Enter quantities in the displayed purchase unit, at or above each offer's minimum. All lines must use one currency. Save the draft, then review its lines and total. You may edit the supplier, warehouse, note, lines, and quantities while the PO remains a draft. Saving a draft refreshes its line costs from the current active offers.
5. Download the supplier-facing PDF and review the supplier, destination, quantities, conversion, costs, and notes. The PDF uses the supplier details currently recorded for a draft.
6. Choose **Mark as sent** only when the order has been placed with the supplier. This freezes the supplier contact/address on the PO and moves converted open quantities to **incoming** at the destination warehouse; on-hand stock has not changed. **Mark as sent does not email the supplier.**
7. To send from SteelCode Connect, choose **Email PO to supplier** and confirm the recipient shown in the dialog. This sends the PDF to the email captured when the PO was marked sent and records the last emailed time/address. Alternatively, deliver the downloaded PDF yourself. A configured outbound mail sender is required for the email action.
8. When a delivery arrives, choose **Receive goods**. Enter the good and damaged quantities in purchase units for the lines actually delivered. Leave undelivered lines at zero.
9. Save the receipt. Good quantities are converted to stock units, increase on-hand stock, and create receipt movements. Damaged quantities reduce incoming but never enter on-hand or sellable stock. A damaged line opens a claim in its receipt history.
10. For a damaged receipt, click its **Damage resolution** status and record whether the goods were returned, credited, written off, or a replacement was agreed. Add a note, such as the supplier's return authorization. Each change is timestamped and retained in the receipt history. Recording **Replacement agreed** does not add stock; book replacement goods through a new PO/receipt when they physically arrive.
11. Repeat for later deliveries. The PO remains **Partially received** until all ordered quantities are accounted for. Outstanding quantities represent the supplier backorder. Retrying the same receipt after a network failure is safe: the same delivery key cannot book stock twice.

A draft PO can be cancelled with no stock effect. Cancelling a sent or partially received PO removes only its outstanding incoming quantity; previously received good stock and receipt history remain intact. You cannot receive more than the outstanding quantity.

## 7. Recommended routine

- Use transfers for location-to-location movement.
- Use a stock count for shrinkage, non-PO discrepancies, or periodic reconciliation. Record PO delivery damage on the receipt, not as good stock followed by an adjustment.
- Keep a short note on a count or adjustment to explain material variances.
- Review stock history before correcting a surprising balance.

## Current boundary

This workflow covers supplier offers, validity dates, purchase-unit conversion, editable PO drafts, PDFs, optional supplier email, partial receipts, damaged-goods resolution, and warehouse stock. It does not perform supplier invoice matching or payment, automatically create a replacement order, or integrate external ERP plugins. Damaged receipt quantities are tracked as claims, not as physically held unavailable stock; if you need to store quarantined goods in a warehouse, that requires a separate inventory intake/quality workflow.
