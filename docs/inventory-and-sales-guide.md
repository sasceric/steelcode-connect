# Inventory and Sales — complete operating guide

Implementation reference date: **30 September 2026**.

This guide explains the Inventory and Sales workflows currently implemented in
SteelCode Connect, including the Shopware integration. It is intended for the
business owner, purchasing and warehouse staff, and the person operating the
application. Menu and button names below use the English interface; translated
interfaces expose the same workflows.

The first release supports purchasing and physical inventory in Connect, with
customers, orders, payments and deliveries imported from Shopware. Shopware
remains the place where checkout, shipment confirmation and customer refunds
are performed. Connect can become the authority for the stock quantity
published to Shopware after a deliberate stock reconciliation.

## Contents

1. [People, ownership and the complete business flow](#1-people-ownership-and-the-complete-business-flow)
2. [Where to find each feature](#2-where-to-find-each-feature)
3. [Stock quantities and what each action changes](#3-stock-quantities-and-what-each-action-changes)
4. [Set up warehouses and opening stock](#4-set-up-warehouses-and-opening-stock)
5. [Suppliers, purchase units and price tiers](#5-suppliers-purchase-units-and-price-tiers)
6. [Purchase orders and delivery to the supplier](#6-purchase-orders-and-delivery-to-the-supplier)
7. [Receive goods, handle damage and supplier backorders](#7-receive-goods-handle-damage-and-supplier-backorders)
8. [Move stock between warehouses](#8-move-stock-between-warehouses)
9. [Stock counts and corrections](#9-stock-counts-and-corrections)
10. [Replenishment suggestions](#10-replenishment-suggestions)
11. [Supplier invoice matching](#11-supplier-invoice-matching)
12. [Connect Shopware and import the catalogue](#12-connect-shopware-and-import-the-catalogue)
13. [Import historical customers and orders](#13-import-historical-customers-and-orders)
14. [Customer profiles, addresses and source fields](#14-customer-profiles-addresses-and-source-fields)
15. [Read an order and its commercial information](#15-read-an-order-and-its-commercial-information)
16. [Activate ongoing Sales sync and stock authority](#16-activate-ongoing-sales-sync-and-stock-authority)
17. [Allocate, pick and ship new orders](#17-allocate-pick-and-ship-new-orders)
18. [Partial shipments, cancellations and order edits](#18-partial-shipments-cancellations-and-order-edits)
19. [Receive and inspect customer returns](#19-receive-and-inspect-customer-returns)
20. [Exception workbench and troubleshooting](#20-exception-workbench-and-troubleshooting)
21. [Daily operating routine](#21-daily-operating-routine)
22. [Background workers and deployment operations](#22-background-workers-and-deployment-operations)
23. [Tenant isolation, permissions and audit history](#23-tenant-isolation-permissions-and-audit-history)
24. [Worked example from supplier to customer return](#24-worked-example-from-supplier-to-customer-return)
25. [Verified scope and remaining release work](#25-verified-scope-and-remaining-release-work)

## 1. People, ownership and the complete business flow

### 1.1 Who is who?

| Term | Meaning in Connect | Example |
| --- | --- | --- |
| Tenant / merchant | The company using Connect; owns its business data and integrations | Your business with its Shopware shop |
| User / member | A person logging into that tenant | The owner or an employee |
| Customer | A buyer purchasing from the merchant's shop | Someone placing a Shopware order |
| Supplier | A business the merchant buys goods from | Kimtec, Comtrade or another distributor |
| Warehouse | A location where the merchant records its own stock | Main warehouse, branch or returns area |
| Sales channel | A storefront or channel belonging to a connected platform | A Shopware storefront |
| Integration connection | The configured link and credentials for an external system | The merchant's Shopware connection |

A supplier belongs to the same supplier master whether it provides a catalogue
feed, receives purchase orders, or both. A separate distributor entity is not
needed just because the business is a distributor. However, the current
supplier module does **not** automatically provide a Kimtec or Comtrade feed
adapter. That adapter needs the distributor's API/feed specification and access.

### 1.2 The intended end-to-end flow

1. A product exists in the Connect catalogue. Today this can come from the
   Shopware catalogue import or the catalogue UI.
2. A supplier-product relationship describes where it can be bought, in which
   purchase unit, at which cost, and under which ordering conditions.
3. The merchant creates and places a purchase order with that supplier.
4. Sent purchase orders contribute **incoming** quantities at the destination
   warehouse.
5. Receiving actual goods adds physical stock. A purchase order alone does not.
6. Connect calculates the available stock across eligible warehouses and, when
   enabled, publishes one quantity per stock-managed product/variant to Shopware.
7. A buyer orders through the Shopware storefront.
8. Ongoing Sales sync imports the order. Connect reserves its goods in a warehouse.
9. The warehouse picks the goods. Actual shipment is confirmed in Shopware.
10. Connect observes the shipment and deducts the shipped goods from physical stock.
11. Customer returns, supplier damage, stock counts and transfers keep the
    inventory ledger aligned with the physical business.

When a distributor catalogue adapter is added, steps 1–2 can be fed by that
adapter. A distributor's advertised stock is its stock, not stock physically
held in the merchant's warehouse. Importing a supplier feed must never create
received warehouse stock by itself. Automatic supplier ordering, dropshipping
and supplier availability publication are separate connector capabilities.

### 1.3 Which system owns which information?

| Information or action | Current responsibility |
| --- | --- |
| Warehouse balances, counts, transfers, purchasing and physical returns | Connect |
| Customer registration and storefront checkout | Shopware |
| Imported customer profile and address-book updates | Shopware supplies the profile; Connect imports it |
| Order prices, taxes, payment state and delivery information | Shopware supplies commercial snapshots |
| Warehouse allocation and reservations | Connect for orders admitted to the live inventory flow |
| Pick progress | Connect |
| Normal shipment confirmation and tracking entry | Shopware; Connect reads the resulting delivery |
| Ambiguous partial shipment quantities | Controlled manual reconciliation in Connect |
| Published Shopware product stock | Shopware initially; Connect after stock authority is enabled |
| Payment capture, refunds and customer credit notes | Selling platform/payment provider/accounting workflow |

Importing information does not create a two-way editor. For example, viewing a
customer address in Connect does not mean editing it there writes back to Shopware.

## 2. Where to find each feature

| Menu / screen | Purpose |
| --- | --- |
| Inventory → Suppliers | Supplier contacts and primary address |
| Inventory → Supplier offers | Supplier-product terms, units, costs and price tiers |
| Inventory → Purchase orders | Draft, send, PDF/email, receipts and supplier backorders |
| Inventory → Supplier invoices | Operational matching against ordered and received goods |
| Inventory → Replenishment | Policies and draft-PO suggestions |
| Inventory → Stock | Stock overview |
| Inventory → Warehouses | Warehouse configuration and warehouse product balances |
| Inventory → Transfers | Draft, send, receive or cancel warehouse transfers |
| Inventory → Stock counts | Expected/count quantities, variance and posting |
| Inventory → Exceptions | Orders, stock or integration problems needing attention |
| Sales → Orders | Imported orders, customer links, commercial details and returns |
| Sales → Pick lists | Printable picking lists and recorded pick tasks |
| Sales → Customers | Customer profile, saved addresses and linked order history |
| Integrations → a Shopware connection → Import | Catalogue/Sales scope, historical import, ongoing sync and stock authority |
| Catalogue → Products → product detail | Stock totals, warehouse balances and Stock movements |

Lists reuse the compact table layout, column visibility controls, sticky action
column and pagination where supported by the screen. Product selectors reuse
the searchable select, search by name/product number, and fetch another 25
options when the user scrolls to the end. Variant options show their actual
variant product number and combination. Select the specific variant when that
is the SKU being bought, transferred or sold.

## 3. Stock quantities and what each action changes

### 3.1 Definitions

Stock is recorded per **tenant + product/variant + warehouse**.

| Quantity | Meaning | Can it be sold now? |
| --- | --- | --- |
| Stock / on hand | Physical quantity recorded at that warehouse | Only its available portion |
| Reserved | On-hand quantity committed to an unshipped customer order | No |
| Unavailable | On-hand quantity held for damage, quarantine or inspection | No |
| Available | On hand − reserved − unavailable | Yes, if the warehouse is eligible for fulfilment |
| Incoming | Outstanding quantity expected from sent purchase orders | No |

Unavailable and reserved stock are portions of on-hand stock, not extra physical
goods. Incoming stock is separate. A transfer in transit is tracked by its
transfer document; it is no longer source on-hand and is not yet destination
on-hand. Do not assume the destination's incoming field contains transfer goods.

Example: a warehouse with 100 on hand, 15 reserved and 5 unavailable has 80
available. A PO for another 40 units makes incoming 40; available remains 80
until the goods arrive.

### 3.2 Stock effect of the main actions

In the table, `q` means quantity in stock units.

| Action | On hand | Reserved | Unavailable | Incoming | Available |
| --- | --- | --- | --- | --- | --- |
| Create/edit draft PO | No change | No change | No change | No change | No change |
| Mark PO sent | No change | No change | No change | + outstanding q | No change |
| Receive good PO goods | +q | No change | No change | −q | +q |
| Receive damaged PO goods | +q | No change | +q | −q | No change |
| Release held damaged goods for sale | No change | No change | −q | No change | +q |
| Return/scrap held damaged goods | −q | No change | −q | No change | No change |
| Reserve a sales order | No change | +q | No change | No change | −q |
| Record picking | No change | No change | No change | No change | No change |
| Ship reserved goods | −q | −q | No change | No change | Normally no change from the already-reserved position |
| Cancel unshipped reservation | No change | −q | No change | No change | +q |
| Receive customer return into quarantine | +q | No change | +q | No change | No change |
| Restock quarantined customer return | No change | No change | −q | No change | +q |
| Write off quarantined customer return | −q | No change | −q | No change | No change |
| Send warehouse transfer | −q at source | No change | No change | No change | −q at source |
| Receive warehouse transfer | +q at destination | No change | No change | No change | +q at destination |
| Post count / manual stock adjustment | Set to approved physical total | No automatic change | No automatic change | No change | Recalculated |
| Import historical order / match invoice / generate PDF / email PO | No change | No change | No change | No change | No change |

The channel stock sent to Shopware is:

```text
max(0, floor(sum(available quantity in active, fulfilment-enabled warehouses)))
```

Connect supports stock quantities to four decimal places. The Shopware stock
writer publishes integer quantities, so fractional available totals are rounded
down. Product totals displayed in Connect can include locations that are not
eligible for channel fulfilment; check the warehouse breakdown before comparing
a displayed total with published Shopware stock.

Shopware receives an aggregate quantity. The current connector does not create
Shopware warehouse entities or expose the internal warehouse breakdown there.
Variant parents are skipped when publishing stock; actual stock is published
for their leaf variants.

## 4. Set up warehouses and opening stock

### 4.1 Default warehouse

Every tenant has a protected warehouse with code `default`. Its display name
may be localized, for example **Glavno skladište**. This is the fallback location
for a source platform that supplies only one stock value.

For a merchant with one location, use this warehouse. Multiple warehouses are
optional. The Default warehouse cannot be deleted or deactivated, and its
protected code remains `default`.

Its current configuration also forces it active, fulfilment-enabled and at
priority 0. It is not a configurable non-fulfilment quarantine location. Use
unavailable quantity to hold stock there, or a separate location for other
warehouse configuration needs.

### 4.2 Add real locations

1. Open **Inventory → Warehouses**.
2. Add a clear warehouse code and name for each location.
3. Keep active locations enabled.
4. Enable fulfilment only where customer orders can actually be supplied.
5. Set the priority; lower numbers are considered first for allocation.
6. Open a warehouse to review its product balances.

Example: Main has priority 0, Branch has priority 10, and an inspection location
is not fulfilment-enabled. The allocator tries Main first, then Branch. Stock
held only in the inspection location does not increase the published channel stock.

Before deactivating a non-default warehouse, resolve its balances, reservations,
incoming POs and open transfers. The current update endpoint does not enforce
an empty-warehouse check for non-default locations; treat this as an operating
restriction until that guard is added. Deactivation excludes the warehouse from
new allocation/channel stock calculations; it does not clear its existing ledger.

### 4.3 Establish opening stock

1. Import or create the products and their variants.
2. Check the quantities physically present at each location.
3. Review **Stock by warehouse** on product detail or the warehouse product list.
4. Use a count for several physically counted products, or **Adjust stock** on
   product detail for a single correction.
5. Select the warehouse and enter the **new total physical quantity**, not the
   amount to add. For example, changing 10 to 12 adds 2; entering 2 sets the
   balance to 2.
6. Record a meaningful note and save/post the correction.
7. Review **Stock movements** and the resulting balance.

Manual stock cannot be negative or fall below reserved + unavailable quantity.
Investigate the associated reservations/quarantine before trying to reduce it
further.

When importing Shopware catalogue stock, Connect seeds Default only if a stock
level does not already exist for that product there. Re-importing the catalogue
does not continually replace a balance already managed by Connect. Another
warehouse's balance is never inferred from Shopware's single stock value.

## 5. Suppliers, purchase units and price tiers

### 5.1 Create the supplier

1. Open **Inventory → Suppliers** and create the supplier.
2. Enter its business name and available contact information.
3. Add the primary postal address and email that should appear on purchase orders.
4. Keep the supplier active while it can be used for purchasing.

The current supplier record has a primary contact/address. Multiple supplier
contacts and independent address books are outside this release.

### 5.2 Create supplier offers for several products

1. Configure the required currencies in the catalogue currency configuration
   and purchase units under **Shop → Units**.
2. Open **Inventory → Supplier offers**.
3. Choose the supplier and select one or more products/variants.
4. Review each compact product row. Expand **More details** where additional
   commercial terms are needed.
5. Enter the purchase terms and any price tiers for each relationship.
6. Save. Batch creation is atomic: invalid data prevents the whole batch from
   being partially saved.

### 5.3 Meaning of the fields

| Field | Meaning | Example |
| --- | --- | --- |
| Supplier SKU | The supplier's own identifier for this product | Supplier calls our SKU `ABC-10` its `K-4921` |
| Purchase unit | Unit used when ordering | Case |
| Stock units per purchase unit | Conversion into the product's stock quantity | 1 case = 12 pieces |
| Minimum order quantity / MOQ | Supplier's minimum accepted order, in purchase units | At least 2 cases |
| Lead time in days | Expected time from ordering to delivery | 5 days |
| Price tier minimum quantity | Quantity at which that tier's price applies | €8 per case from 10 cases |
| Unit cost | Cost per purchase unit for that tier | €10 per case |
| Currency | Configured currency used for the tier | EUR |
| Valid from / until | Dates when that quoted price can be selected | October price list |
| Preferred relationship | Primary purchasing source for that product | Supplier used for replenishment |
| Replenishment currency | Currency selected for automatic purchasing suggestions | EUR |

MOQ and price threshold are independent. A supplier can require 2 cases while
having a tier priced from 1 case. Lead time describes delivery delay; a valid
date describes the usable period of a price.

Supplier SKU, cost, MOQ and supplier lead time are supplier-specific. Connect
does not infer them from the product's selling price. A product's catalogue
unit may help choose a purchase unit, but the actual supplier pack conversion
still needs to be checked.

### 5.4 Price and conversion example

Our product is stocked in pieces. The supplier sells cases of 12 pieces:

- MOQ: 2 cases.
- Tier 1: €10 per case from 1 case.
- Tier 2: €8 per case from 10 cases.

Ordering 2 cases means 24 stock units and a line cost of €20. Ordering 10 cases
means 120 stock units and a line cost of €80. No selling price changes.

Fractional conversions are supported, such as one bottle = 0.75 litres. If a
quantity/conversion requires more than four decimal places in stock units, the
PO or receipt is rejected rather than silently rounding the physical ledger.

An unpriced relationship can be kept while waiting for a quote, but cannot be
ordered until a valid price tier exists. Cost zero explicitly means free goods.
Avoid overlapping validity periods for the same currency and tier threshold.
Only one active relationship per product can be preferred.

Warehouse quantity and price are separate concerns. Supplier cost belongs to
the offer/PO, selling prices belong to the product/catalogue pricing workflow,
and a warehouse level holds quantities. This release has no automatic
warehouse-specific selling price or FIFO/average-cost valuation. Different
receipt costs remain traceable through their POs; they do not turn a quantity
balance into an accounting valuation. A product's catalogue purchase-price
field is not a substitute for each supplier's actual quoted terms.

## 6. Purchase orders and delivery to the supplier

### 6.1 Create and review a draft

1. Open **Inventory → Purchase orders** and create a PO.
2. Choose the supplier and receiving warehouse.
3. Select products with active offers from that supplier. The selector can
   search name, product number and supplier SKU.
4. Choose the PO currency.
5. Enter quantities in **purchase units** and meet each relationship's MOQ.
6. Check the selected price tier, conversion, line costs and total.
7. Save the draft and review it.

All lines on the PO use the same currency. The applicable price tier is the
currently valid tier with the highest threshold met by the entered quantity in
that currency. While the PO is a draft, saving changes re-evaluates the terms
and snapshots the selected cost and conversion on its lines.

You can edit supplier, warehouse, note, lines and quantities while the PO is a
draft. A later supplier-offer change does not silently rewrite a sent PO.

### 6.2 Place and send the purchase order

1. Download the supplier-facing PDF and review it.
2. Place the order with the supplier through the agreed business channel.
3. Choose **Mark as sent** when the order has been placed.
4. Check that the PO is Sent and incoming quantities appear at the receiving
   warehouse.
5. To email from Connect, choose **Email PO to supplier**, review the recipient
   in the dialog, and confirm. Alternatively, send the PDF yourself.

**Mark as sent does not send an email or call a distributor ordering API.**
The email action requires configured outbound mail. Connect records the last
emailed timestamp/address. On sending the PO, supplier contact/address details
are frozen on that PO; later supplier edits do not change that sent document.

### 6.3 Purchase-order states

| State | Meaning | Usual next action |
| --- | --- | --- |
| Draft | Editable proposal; no incoming or received stock | Review and mark sent, or cancel |
| Sent | Placed with supplier; open quantities are incoming | Receive a delivery |
| Partially received | Some ordered quantities accounted for; some remain open | Receive later delivery or cancel remaining balance |
| Received | All ordered quantities accounted for by receipts | Review invoices and any damage claims |
| Cancelled | No further receipt against cancelled open quantities | Keep document for history |

Cancelling a draft has no stock effect. Cancelling a sent or partly received PO
removes only its outstanding incoming quantity. Already received goods and
receipt history remain.

## 7. Receive goods, handle damage and supplier backorders

### 7.1 Receive an actual delivery

1. Open the sent/partly received PO and choose **Receive goods**.
2. Check the physical delivery against the PO.
3. Enter good and damaged quantities in purchase units for the lines delivered.
4. Leave undelivered quantities at zero.
5. Check the receiving warehouse and stock-unit conversion.
6. Save the receipt and inspect the updated balances and receipt history.

Good quantity increases on hand and available. Damaged quantity increases on
hand and unavailable together, so it cannot be sold. Both reduce incoming.
The PO cannot receive more than its outstanding quantity.

Example: a sent PO for 10 cases of 12 has incoming 120 pieces. A delivery of
5 good cases and 1 damaged case produces 60 saleable pieces, 12 unavailable
pieces and 48 still incoming. The remaining 4 cases are the supplier backorder.

Receipt requests use a delivery key so retrying the same delivery after a
network failure does not receive it twice. A genuinely separate delivery is a
new receipt, even if its quantities happen to match the previous delivery.

### 7.2 Resolve damaged goods: two separate decisions

1. Open the damaged receipt history.
2. Record the supplier's **Damage resolution**: returned, credited, written off
   or replacement agreed, according to the actual commercial agreement.
3. Separately record **Physical damaged stock**: returned to supplier, scrapped
   or released for sale.
4. Review the stock movement or resolution audit record.

A credit does not physically remove goods. Returning goods does not prove the
supplier issued a credit. A replacement agreement does not automatically
receive replacement stock or place another PO.

Returning/scrapping physically held damage reduces both on hand and unavailable.
Releasing inspected saleable goods reduces unavailable and raises available.
Historical damaged receipts marked **not booked** predate physical quarantine;
Connect does not invent stock for those records.

## 8. Move stock between warehouses

1. Open **Inventory → Transfers** and create a draft.
2. Select different, active source and destination warehouses.
3. Select products/variants and enter quantities in stock units.
4. Save and review. Creation rejects quantities greater than the source's
   current available balance.
5. Choose **Send transfer** when goods leave the source.
6. Receive the goods at the destination, then choose **Receive transfer**.
7. Review the source and destination product movements.

The source balance is checked again when sending. A draft does not reserve
stock; another sales order or transfer can consume availability after the
draft is created. A transfer that was valid yesterday can therefore fail to
send today.

Sending creates transfer-out movements and changes the document to In transit.
Receiving creates transfer-in movements and changes it to Received. During
transit the goods are represented by the transfer document, not saleable stock
at either location. The current receive action receives the transfer's lines;
there is no separate partial-transfer receipt workflow.

Only a draft can be cancelled. Once sent, receive it and record any necessary
subsequent correction. For goods physically returned to the original location,
use a new reverse transfer after receipt. Never use unrelated adjustments to
imitate a normal warehouse transfer.

## 9. Stock counts and corrections

### 9.1 Why a stock count exists

A stock count compares the system's recorded quantity with an actual physical
count. It is used for periodic inventory checks, losses, shrinkage or an
opening-stock reconciliation. Saving the draft is a review step. **Post count**
is the action that applies the counted quantities to stock.

### 9.2 Count and post

1. Open **Inventory → Stock counts** and choose **Create count**.
2. Select the warehouse.
3. Add only products actually counted and enter their full physical quantity.
4. Include held/quarantined goods in physical quantity; they remain unavailable.
5. Add a note explaining the count or known discrepancies.
6. Save the draft and open its details.
7. Review expected quantity, counted quantity and variance for every line.
8. Choose **Post count** and confirm only after the physical count is approved.
9. Check the stock balances and product Stock movements.

Positive variance adds quantity; negative variance removes quantity. Posting
sets each line to the counted total and records an adjustment with the count
reference. It does not add the counted amount to the existing stock.

### 9.3 If posting is rejected

A count records the inventory row version and expected quantity when created.
If any counted line changes before posting, the **entire count** is rejected.
This protects later receipts, shipments, reservations and other changes.

Refresh, review the intervening movements, and create a fresh count based on
the current physical situation. Do not bypass the version check or reuse old
counted totals without reconciling the intervening activity. A counted total
below reserved + unavailable is also rejected.

A posted count is final. Correct a later discovery with a new documented count
or adjustment; do not rewrite the posted count's history.

## 10. Replenishment suggestions

1. Open **Inventory → Replenishment**.
2. Add a policy for the product and warehouse.
3. Enter a reorder point and a higher target quantity; optionally override
   the supplier's lead time.
4. Make sure the product has a preferred active supplier relationship, a valid
   price tier and a selected replenishment currency.
5. Review **Suggestions**.
6. Select the suggestions to order and choose **Create draft POs**.
7. Review each resulting draft, then follow the normal send/receive workflow.

The stock position is **available + incoming**. A suggestion appears when that
position reaches or falls below the reorder point. Suggested quantities are
rounded to complete purchase units and satisfy MOQ and a currently priced tier.

Example: available 8, incoming 0, reorder point 10 and target 30 requires
22 stock units. If a case contains 12 pieces and MOQ is 2 cases, the suggestion
is 2 cases = 24 pieces. If incoming were already 24, the position would be 32
and no reorder would be suggested.

Drafts are grouped by supplier, warehouse and currency. A product already on
a draft PO for that warehouse is rejected to avoid duplicate ordering. Draft
POs themselves are not incoming stock; review/send or cancel old drafts before
creating another. Replenishment creates proposals, not automatic supplier orders
or a demand forecast based on sales history.

## 11. Supplier invoice matching

1. Receive the goods first.
2. Open **Inventory → Supplier invoices** and add an invoice against the sent PO.
3. Enter the supplier's invoice number and date.
4. Review the proposed good received quantities not already covered by matched
   invoices; adjust the lines for an actual partial invoice.
5. Enter billed quantities, unit costs, tax, shipping, discount and the supplier's
   stated total.
6. Save the draft.
7. Choose **Match invoice**.
8. If Disputed, review the reasons, resolve the discrepancy with the supplier,
   correct the draft and match again.

The three-way check compares billed quantities with good receipts, costs with
the PO's snapshotted costs, and stated total with the calculated total. Damage
is not automatically treated as good billable stock. Other matched invoices
count toward the cumulative billed quantity.

The calculated total is the sum of billed quantity × unit cost for each line,
plus entered tax and shipping, minus entered discount. Lines and final totals
are rounded using the configured currency precision. Matching checks that
rounded result against the stated total; it does not automatically accept a
commercial price change merely because the difference is small.

A matched invoice is immutable. If entered incorrectly, void it with a reason
and create the corrected invoice. The void remains in the audit history and
its quantity no longer counts as matched billing. Invoice numbers are unique
per supplier and year.

Matching does not pay the supplier, post accounting entries, validate tax rates
against tax law or calculate inventory valuation. It is an operational control.

## 12. Connect Shopware and import the catalogue

### 12.1 Prepare the connection

1. Create a Shopware Admin API integration with permissions for the catalogue
   and Sales entities that will be imported.
2. For Connect-owned stock, that integration also needs permission to write
   product stock through the Admin API.
3. In Connect, add/open the Shopware integration and configure its base URL
   and integration credentials.
4. Test the connection and ensure it is active and enabled with the channel
   direction configured.
5. Ensure the worker described in [section 22](#22-background-workers-and-deployment-operations)
   is running.

Shopware administrator login credentials are not the API integration credentials.
The connector uses the integration's access-key pair and obtains an API token.
Credentials are stored encrypted; do not put them in operational notes or logs.

### 12.2 Import the catalogue first

1. Open the connection's **Import** tab.
2. Choose catalogue scope and required related data, including variants and
   custom-field definitions when needed.
3. Run **Import catalogue** and review progress/history/logs.
4. Verify product numbers, variant combinations and channel publications.
5. Check opening stock by warehouse.

The catalogue workflow imports supporting entities before products: sales
channels, currencies, units, tags, taxes, delivery times, manufacturers,
properties, custom-field definitions and categories, according to the selected
scope. Products/variants and associated catalogue data follow.

Order allocation resolves stock-managed product lines by product number/SKU.
A variant's product number must match the actual variant, not only its parent.
The external Shopware product ID on the channel publication is also required
for stock publication. A valid SKU match and a valid outbound publication
mapping serve different purposes.

The initial stock import can seed Default where no balance exists. Before
making Connect stock-authoritative, verify the physical merchant-owned quantity
and separate it across real warehouses. Existing levels are not overwritten
by later catalogue imports.

## 13. Import historical customers and orders

### 13.1 Choose the Sales scope

1. Open **Integrations → Shopware connection → Import**.
2. In **Sales import**, enable **Customers**, **Orders**, or both.
3. Choose **Import orders from** to limit orders by their order date. Leave it
   blank to import the available order history.
4. Keep **Ongoing order sync** and **Connect manages Shopware stock** off during
   the initial historical import/setup.
5. Save the configuration, then choose **Import sales**.
6. Review progress, created/updated/failed counts and the diagnostic logs.
7. Open **Sales → Customers** and **Sales → Orders** to verify the imported records.

The history-from date filters **orders**, not customer profiles. When Customers
is enabled, the customer phase imports the available customer profiles and
address books, including customers with no order in the selected date range.

### 13.2 What happens in the background?

1. The action creates a normal queued `IntegrationImportRun` of type `sales`.
2. The Messenger worker consumes it.
3. Customers are imported first when selected; orders follow when selected.
4. Shopware records are fetched in pages of 25 with the requested associations.
5. Each record is mapped into Connect's normalized customer/order model.
6. Imported IDs are resolved within the connection, so repeating an import
   updates the same source records instead of creating another copy.
7. Item failures are logged and processing continues with other records.

An order with unmatched product SKUs can still be stored and inspected; its
unresolved lines are reported in the run. A run reaching Completed does not
guarantee that its failed-item count is zero. Review that count and the log.

Cancellation stops after the current item and retains already imported records.
Fix the problem and run the relevant scope again. Catalogue and Sales imports
use the same existing progress/history/logging approach.

### 13.3 Historical records never change today's stock

Historical import creates informational orders. It does not reserve, ship,
deduct goods or reconstruct a past warehouse balance. Historical order state
is kept separately from live inventory state.

Importing 158 old Shopware orders therefore does not subtract the quantities
from today's stock a second time. Re-importing them does not promote them into
new inventory orders. Native returns require Connect shipment allocations;
an old historical order alone does not establish returnable warehouse stock.

## 14. Customer profiles, addresses and source fields

### 14.1 Read the customer detail

1. Open **Sales → Customers** and choose a customer.
2. In **Overview**, review identity/contact details, customer number, private/
   company account type, company information, active state and VAT IDs.
3. Review source information such as language, customer group, salutation,
   birthday, affiliate/campaign codes and custom fields where supplied.
4. Open **Addresses** to see the imported saved customer addresses and defaults.
5. Open **Orders** for linked order history; an order number opens its detail.

Customer addresses are separate records owned by that customer and tenant.
They are not the merchant's company/billing addresses. A profile import with
a complete address book also reconciles addresses removed in Shopware.

### 14.2 Current profile versus checkout snapshots

A customer profile is the customer's current information. An order stores the
buyer, billing and shipping information received for that order. Changing the
customer's saved address later does not automatically rewrite an old order's
checkout address.

A registered customer's source ID links the order to its imported customer
where available. If only the order was imported, Connect may create an
incomplete customer stub; the UI marks that the full profile was not imported.
Import Customers to populate that profile and saved address book. Order imports
do not overwrite a separately imported complete customer profile.

Catalogue translation tables remain relevant to product/category/property
content. Customer names, customer addresses and order commercial snapshots are
stored as received, rather than as catalogue translations. Translated interface
labels do not alter those business records.

Guest checkout keeps the buyer information on the order and does not create a
registered customer account in Connect. A customer-profile import may retain
Shopware's guest profile as source data, but that does not grant a login or
convert the guest order into a registered buyer relationship.

### 14.3 Why there are Custom fields and Shopware source fields cards

Connect normalizes fields used by its workflows and preserves sanitized API
business attributes for the rest. Language/group/method/tag associations and
source relationship IDs can remain in these cards. An attribute being stored
does not mean it has a dedicated editable control or its own local catalogue.

Customer, address and order custom-field values are retained. Custom-field
definitions and assignments come from the catalogue reference import; the Sales
import does not independently recreate every definition.

Passwords/hashes, access/deep-link codes, credentials/tokens, IP addresses and
user-agent data are excluded. Customer passwords are not migrated, and imported
buyers do not automatically get a Connect login.

See [Sales import field coverage](sales-import-field-coverage.md) for the field
inventory and deliberate exclusions.

## 15. Read an order and its commercial information

1. Open **Sales → Orders** and click the order number.
2. In **General**, check the customer link or buyer snapshot, connection, date,
   state, currency, billing address and shipping address.
3. Review payment/shipping method and state, and order totals: items gross,
   shipping net/tax/gross, total net, tax and grand total.
4. Inspect unmatched-product warnings and Custom fields / Shopware source fields.
5. In **Details**, inspect items, quantities, reserved/fulfilled quantities,
   payment transactions and deliveries/tracking.
6. Use **Returns** for physical customer-return operations after shipment.

Source commercial information includes product lines, discounts, fees and other
provider line types. Only stock-managed product lines participate in warehouse
allocation. A discount or shipping charge is not a physical product to pick.

Payment records retain their source external ID, method, state, reference,
amount and currency. Delivery records retain source ID, method, state, tracking
codes, shipping-address snapshot and order-line references. One order can
contain multiple payments or deliveries; review the detail tables instead of
assuming the summary card shows every record.

Shopware remains the source of order financial amounts and tax information.
Connect does not recalculate an imported order using today's catalogue prices
or local tax settings. Catalogue name/translation changes do not rewrite
received order-line names. Later source order corrections can update supported
imported snapshots while the physical shipment history remains protected.

Imported payment state is not proof that Connect performed a capture or refund.
There is no payment collection, bank settlement, refund or sales accounting
engine in this module. The mapper can retain Shopware document metadata if
supplied, but the default order importer does not request the documents
association. Document files are not downloaded and there is no Documents tab
for this release.

## 16. Activate ongoing Sales sync and stock authority

These are separate settings. Enable them in this order.

With Connect stock authority off, Connect does not publish its ledger balances
to Shopware. It also does not continuously overwrite existing warehouse levels
with Shopware stock: the catalogue stock import is an initial bootstrap. The
two balances can therefore diverge during setup or while independently operated.
This setting is not a bidirectional stock mirror.

### 16.1 Start ongoing Sales sync

1. Finish initial catalogue/historical Sales import and inspect failures.
2. Verify SKU mappings and the physical stock in each warehouse.
3. Keep **Connect manages Shopware stock** off.
4. Enable **Orders** and **Ongoing order sync**, then save.
5. Record the activation time for the business cutover.
6. Wait for a successful background poll and check **Ongoing sales sync** on
   the connection screen.
7. Place an authorized test order through the storefront and verify its import
   and warehouse reservation.

The server records the activation time. The live polling cutoff uses the source
order's creation timestamp, while the historical date filter uses its order
date. Earlier orders remain historical; new source orders admitted after
activation enter the live stock flow. Do not
toggle ongoing sync off/on casually: reactivation establishes a new cutover
time, and orders placed during an inactive interval need explicit review.

Ongoing sync requires Orders enabled and an active Shopware channel connection.
Customers are updated incrementally if Customers is selected. A running
historical Sales import blocks activation, and ongoing polling pauses while
such an import is active.

### 16.2 How automatic polling works

1. Symfony Scheduler triggers a queueing job every minute.
2. That job dispatches an async poll for each eligible connection.
3. Each connection is locked to prevent overlapping polls.
4. The poll reads new/changed customers and orders, plus changes to deliveries,
   transactions and order lines.
5. It fetches bounded pages and feeds the same normalized ingestion service.
6. A five-minute overlap is re-read to catch recent updates; replay is safe.
7. Pending allocation is retried in rotating batches after stock/mappings become
   available.
8. The checkpoint advances only after fetched records succeed. Failure retains
   the checkpoint and records an error for retry/investigation.

This version uses authenticated Shopware Admin API polling. It does not require
or currently install a Shopware plugin/App webhook. A live shipment can be
observed on a later minute tick; queue backlog or unavailable services can make
the delay longer. New historical imports and ongoing sync are distinct jobs.

### 16.3 Enable Connect stock authority

1. Compare Connect's available quantities with Shopware, including warehouse
   fulfilment flags, reservations and unavailable stock.
2. Repair publication external IDs, unknown SKUs and unexplained quantity gaps.
3. Establish which system will own stock and stop independent competing stock
   writers for these products.
4. Let ongoing Sales sync complete successfully.
5. Enable **Connect manages Shopware stock** and save.
6. Review stock publication and exceptions, then read back the quantities in
   Shopware.

Activation requires an existing successful checkpoint for the current cutover,
no older than five minutes. Enabling ongoing sync and stock authority together
on the first save cannot bypass that prerequisite. First enable ongoing sync,
wait for a poll, then enable stock authority.

Activation queues the published leaf products for reconciliation. Stock changes
then coalesce into one pending outbox event per tenant/product. The dispatcher
recalculates current availability after commit instead of sending an old
increment. Only active, visible publications on active/enabled connections
receive stock. An external Shopware product ID is required.

For each event, the stock writer waits until the Sales checkpoint has passed
the event timestamp. Failed writes retain an error and can be retried. A pending
event waiting for Sales is not necessarily a failed event.

### 16.4 Large initial reconciliation

An application operator can run this from `backend/` after the stock audit and
activation:

```bash
php bin/console app:stock-sync:reconcile-shopware <connection-id> --verify
```

Replace `<connection-id>` with the actual tenant's integration connection ID.
The command validates the resolved connection and uses that connection's
credentials and tenant-owned stock. It sends batches of 100 by default, avoids
direct variant-parent writes, and marks unchanged outbox events dispatched only
after Shopware accepts the batch. Changes requeued during a remote request are
left pending. Failed batches can be rerun because publication sets an absolute
stock value.

`--batch-size=20` uses smaller batches. `--verify` reads back every mapped,
published leaf product and reports mismatches or missing products. With no
pending work it still performs the read-back. This bulk command currently
requires one active stock-authoritative connection for that tenant; tenants
with several such connections must use the ordinary dispatcher and a deliberate
multi-channel reconciliation procedure.

### 16.5 Current consistency limit

The checkpoint gate and internal inventory locks protect Connect's ledger, but
a Shopware checkout can occur between a Sales poll and a stock write. Minute
polling does not provide an atomic reservation shared with Shopware checkout.
Do not promise strict oversell prevention for a busy shop based on this version.
Coordinated checkout/reservation and a faster authenticated event adapter remain
release work for that requirement; adding a webhook alone does not make the
two systems' transactions atomic.

Use the detailed [Shopware cutover guide](shopware-sales-inventory-cutover.md)
for the activation record and acceptance checklist.

## 17. Allocate, pick and ship new orders

### 17.1 Automatic allocation

1. The customer places an order in Shopware.
2. Ongoing sync imports its lines and commercial snapshots.
3. Connect matches the product/variant SKUs.
4. It tries active fulfilment warehouses in priority order.
5. It reserves the order only in a warehouse that can cover all its outstanding
   stock-managed product lines.
6. The order becomes Reserved and its available stock decreases.

The first release does not silently split an order across warehouses. If Main
has product A but Branch has only product B, combined stock can be enough while
neither location can supply the complete order. That order remains unallocated.
Transfer goods to a suitable location or resolve the business fulfilment plan.

Unknown products or insufficient stock leave a visible New/unallocated order.
Use **Inventory → Exceptions** to investigate. After a product import/stock
receipt, pending allocation is retried by the worker or the supported row retry.
Payment-state import does not itself capture money or create a configurable
payment-dependent allocation policy.

### 17.2 Pick the reserved goods

1. Open **Sales → Pick lists**, or use the order's pick-list action.
2. Verify order number, buyer/address, assigned warehouse, SKU and quantity.
3. Print the list if staff use paper.
4. Choose **Start picking** to create the recorded task.
5. Pick the goods from the specified warehouse.
6. Enter the actual picked quantity for every line, including zero for missing goods.
7. Choose **Save progress** while work is in progress.
8. Choose **Confirm picked** when the recorded task is complete.

Full quantities produce Picked; fewer than reserved produce Short pick. At
least one unit must be picked to confirm a task. Picking changes neither
on-hand nor reserved stock, and does not mark the Shopware delivery shipped.

Pick tasks have versions. Another user's update or an order/reservation change
can make the task outdated. Refresh rather than overwriting a newer task. An
empty outdated task can be replaced; tasks with already-picked goods or a
completed pick require physical reconciliation before another task. The current
UI does not provide a full automated repicking/reset workflow. Do not hide such
an issue by creating duplicate tasks or resetting data.

### 17.3 Confirm actual shipment

1. Prepare the picked goods and shipping information.
2. In Shopware, confirm the actual delivery shipment and enter tracking where
   appropriate.
3. Wait for ongoing sync and open the Connect order again.
4. Verify delivery state, fulfilled quantities, warehouse movements and remaining
   reservations.
5. Verify the resulting published stock after its outbox event is dispatched.

For a shipment of `q`, Connect reduces physical stock by `q`, releases that
portion of reserved stock and writes a shipment movement. Re-reading the same
shipment does not deduct it again. The order becomes Partially fulfilled or
Fulfilled according to the recorded quantities.

There is no ordinary local **Ship** action that pushes fulfilment to Shopware.
Also, a completed pick task is not a technical prerequisite enforced by the
source shipment importer: Shopware-confirmed shipments can arrive without a
Connect pick. The business should follow the pick-before-ship procedure even
though these are separate records.

## 18. Partial shipments, cancellations and order edits

### 18.1 Explicit partial deliveries

If Shopware provides several deliveries with explicit positions and quantities,
Connect sums the positions of deliveries confirmed Shipped by their external
order-line IDs. It applies the difference from quantities already fulfilled.
Unshipped quantities remain reserved.

Example: a line contains 5 units. A first confirmed delivery with 2 units ships
2, leaving 3 reserved. A second confirmed delivery with 3 units raises the
cumulative shipped quantity to 5 and deducts only the additional 3.

### 18.2 Ambiguous partial delivery: manual reconciliation

Shopware's `shipped_partially` state alone does not describe the exact physically
shipped quantity per line. Delivery positions may describe assigned units,
not the shipped subset. A fully shipped delivery without positions mixed with
other deliveries can also be ambiguous.

1. Open the **Shipment reconciliation** exception or the order's **Details** tab.
2. Obtain the actual shipped totals from the warehouse/delivery records.
3. Use **Reconcile partial shipment** to enter the **total shipped so far for
   every product line**.
4. Save and check fulfilled/reserved quantities and movements.
5. For a later shipment, submit the new cumulative totals.

For a five-unit line already shipped 2, entering 4 means another 2 shipped.
Do not enter 2 again expecting it to add another 2. Totals cannot exceed the
order line or reduce already fulfilled quantity. The action changes Connect's
physical ledger, records the acting user, and does not change Shopware's
delivery state. The exception can remain while the source delivery is still
ambiguous, even after the known quantities are reconciled.

### 18.3 Cancellation

1. Cancel the order in Shopware according to the actual business decision.
2. Allow ongoing sync to observe it.
3. Verify that unshipped reservations are released.
4. Review already shipped units separately.

Cancellation does not put shipped goods back on the shelf. If a partially
shipped order is cancelled, only unshipped reservations are released. A
cancellation after full shipment does not automatically create a return or
refund; receive the actual returned goods through the return workflow.

### 18.4 Changes to order lines after import

Source edits to unshipped product lines can add/remove lines, replace an SKU,
or change quantity. Connect releases/reconciles the old reservation and tries
to reserve the outstanding quantities, retaining the assigned warehouse where
possible. Insufficient stock or an unknown replacement SKU leaves open quantity
for retry and investigation.

Once units are shipped, their SKU cannot be silently replaced, their line cannot
be removed, and the line quantity cannot fall below shipped quantity. The
import reports these conflicts rather than rewriting physical history. Review
the source correction and use a real return/correction workflow where needed.
Commercial snapshot corrections do not authorize undoing shipped goods.

Any reservation change can invalidate an existing pick task. Review physically
picked goods before starting another task for the edited order.

## 19. Receive and inspect customer returns

### 19.1 Receive only goods that physically arrived

1. Open **Sales → Orders → the order → Returns**.
2. Select an original shipped line and warehouse from the offered options.
3. Check the remaining returnable quantity: shipped minus already returned.
4. Enter the quantity actually received, reason, condition and note.
5. Choose **Quarantine** while the goods await inspection.
6. Save and review the return record and stock movements.

Returns are bounded by shipped allocations recorded in Connect for that
order/warehouse. They cannot exceed cumulative shipped, unreturned quantity,
and cannot be used to add arbitrary inventory. Historical orders without
Connect shipment allocations have no native returnable options.

The current return posts to the original shipping warehouse. It does not select
an unrelated receiving warehouse. If the business needs another storage
location after the goods become saleable, use a normal transfer; a dedicated
returns-location receiving workflow is future work.

### 19.2 Inspect and resolve quarantine

1. Inspect the returned goods physically.
2. Open the quarantined return's resolution action.
3. Set the inspected condition.
4. Choose **Restock** for saleable goods or **Write off** for goods to remove.
5. Save and check available/unavailable balances.

Quarantine adds on hand and unavailable together. Restocking removes the hold
and increases available. Writing off removes on hand and unavailable together.
Damaged or unknown-condition goods cannot be restocked as saleable.

The receive form also allows immediate Restock for already-inspected acceptable
goods, or immediate Write off. Immediate Restock adds saleable on-hand stock;
immediate Write off records the return without adding those discarded goods
to inventory. Use quarantine when physically holding goods pending a decision.
Resolving a quarantined record is final; repeated identical requests are safe,
but a different final disposition cannot casually overwrite its history.

The receive request has an idempotency key: retrying the identical request
does not add stock twice. A return does not reduce the historical fulfilled
quantity, refund the customer, change Shopware state or create an RMA/credit
note. Perform those commercial actions separately in Shopware/accounting.

## 20. Exception workbench and troubleshooting

### 20.1 Work an exception

1. Open **Inventory → Exceptions**.
2. Select the relevant category; the screen shows one category at a time.
3. Open the referenced order, product, warehouse or integration.
4. Resolve the underlying cause.
5. Use the supported **Retry** action where available.
6. Refresh after the worker or operator action completes and verify the balance.

| Category | What it indicates | Correct response |
| --- | --- | --- |
| Unmatched products | A live order has an unresolved product SKU | Import/correct the actual product or variant; retry allocation |
| Unallocated orders | Mapped order quantity cannot be reserved | Check one warehouse can cover the whole order; receive/transfer stock; retry |
| Shipment reconciliation | Delivery state lacks trustworthy shipped line quantities | Reconcile cumulative shipped totals in order Details |
| Failed stock sync | Outbound publication failed and has an error | Fix ID/permissions/connectivity; retry the event and run the worker |
| Failed Sales sync | Connection checkpoint has a recorded failure | Inspect integration error, fix source/credentials, retry Sales sync |
| Failed imports | Latest import failed or contains failed items | Inspect the run logs and rerun the repaired scope |
| Stock discrepancies | A warehouse level has negative available quantity | Investigate stock/reservations/unavailable; reconcile physical facts |
| Quarantined returns | Customer goods await inspection/disposition | Open the order Returns tab and restock or write off after inspection |

The workbench is not a full cross-system stock-comparison report. Its stock
discrepancy category identifies negative availability in Connect. Use the
Shopware read-back verification for Connect-versus-Shopware differences. An
empty category does not prove all other categories or channel stocks are clean.

### 20.2 Import stays Queued

Check the worker is consuming `async`, the connection is active/enabled, and
the queue/database are reachable. Frontend and API running alone do not execute
queued jobs. Do not start another import to solve a missing worker.

### 20.3 Fewer orders than expected

Check **Import orders from**, saved Orders scope, cancellation, active progress
and failed-item logs. The historical import reads all matching pages; a count
of 4 is not an intentional fixed order limit. Customer scope and order date
scope are separate. Check the connection is the intended Shopware shop.

### 20.4 Product exists but an order will not allocate

Check the exact variant SKU, fulfilment flag, warehouse priority and available
quantity. Aggregate stock spread over warehouses is not sufficient if no one
warehouse can cover the order. Incoming and unavailable goods cannot be reserved.

### 20.5 Send transfer returns 422 / insufficient stock

Review the current source availability, not just physical on hand. Drafts do
not reserve their lines, so later reservations can invalidate them. Adjust the
business plan or source stock; do not force the send by changing the database.

### 20.6 Count posting conflicts

Review movements after count creation and count again. Version conflicts reject
the whole posting so a stale count cannot erase another operation.

### 20.7 Stock authority activation is refused

Enable Orders + Ongoing order sync, save, and let a poll complete successfully.
The checkpoint must match the current activation and be within five minutes.
Then enable Connect stock authority. Inspect the worker/connection error if
the checkpoint never advances.

### 20.8 Stock write is waiting or differs from the product total

Check Sales has passed the event timestamp, the worker is running, the
publication is visible/active and has an external product ID, and the connection
is stock-authoritative. Compare only active fulfilment warehouses and apply
integer rounding. Parent product stock is not directly written. Inspect failed
events before retrying. Avoid independent Shopware or ERP writers continually
overwriting the same stock.

### 20.9 Picking shows Outdated or another-user conflict

Refresh and inspect source edits/shipments and reservations. Physically reconcile
picked goods; current task guards do not authorize discarding a previous pick.

### 20.10 Return item is missing or Restock is refused

Check a shipped Connect allocation exists, it is the original warehouse, and
returnable quantity remains. Change the condition only after actual inspection;
Damaged/Unknown cannot become saleable through a blind restock.

### 20.11 PO email failed

Check the frozen recipient on the sent PO, outbound mail configuration and
application logs. Mark as sent and email are separate; an email failure does
not mean received stock should be changed or the PO duplicated.

### 20.12 Supplier invoice is Disputed

Read the quantity/cost/total discrepancy. Match good receipts and other matched
invoices against the supplier's bill. Correct the draft after business agreement;
do not change physical stock merely to make an invoice pass.

## 21. Daily operating routine

### Start of day

1. Check the worker and the Shopware connection's latest Sales checkpoint/error.
2. Review all relevant exception categories.
3. Review new/unallocated orders and pick lists.
4. Review sent POs, expected deliveries and damaged goods awaiting resolution.
5. Review replenishment suggestions and existing draft POs.

### During the day

1. Receive actual supplier deliveries immediately against the correct PO.
2. Separate damaged goods from saleable stock and record their holds.
3. Send/receive transfers at the physical departure/arrival times.
4. Pick reserved goods and confirm shipments in Shopware.
5. Receive customer returns only when goods arrive, then inspect them.
6. Use counts/adjustments for documented discrepancies, not as substitutes for
   receipts, transfers, shipments or returns.

### End of day / periodic checks

1. Review unallocated orders, short picks and ambiguous shipments.
2. Review failed Sales/stock/import events and unresolved quarantine.
3. Review in-transit transfers and outstanding supplier backorders.
4. Match supplier invoices against received goods.
5. Perform planned stock counts and read back channel stock when investigating
   discrepancies or validating a cutover.

## 22. Background workers and deployment operations

This section is for the application operator. Business users should not need
to run terminal commands for normal daily work.

### 22.1 Run the existing worker

From the repository root:

```bash
cd backend
composer imports:consume
```

This consumes `scheduler_default` and `async`, disables Symfony debug/query
profiling, and applies a 256 MB worker memory limit. The scheduler triggers
Sales polling and stock-outbox dispatch every minute. It also serves other
scheduled application work.

An interactive terminal process is suitable for development, but not durable
operation. Production needs a process supervisor that starts the worker,
restarts it on exit/memory limit, captures logs and starts it after reboot.
Use the intended deployment environment and its database, credentials and
shared lock configuration. Multiple worker hosts require a shared lock store.
Do not create a new supervisor/service per tenant prematurely; tenant routing
can be introduced when operating scale requires it.

### 22.2 Deploy changes to handlers safely

1. Deploy the code/dependencies and required database migrations.
2. Rebuild the container/cache for the same environment and debug mode used by
   the worker.
3. Restart the managed workers so they load the new code/container.
4. Check `debug:messenger` in that mode and observe a scheduled tick.
5. Confirm the connection checkpoint advances and the queue is not failing.

For this project's local dev-environment worker with debug disabled, diagnostics
can use:

```bash
APP_DEBUG=0 php bin/console cache:clear
APP_DEBUG=0 php bin/console debug:messenger
```

Adjust `APP_ENV` appropriately for the actual deployment. Clearing one mode's
container does not reload an already running worker. During acceptance a stale
non-debug container lacked the new Sales handlers; rebuilding it and restarting
that worker restored the scheduled path. Observe actual processing rather than
assuming the command's startup banner proves synchronization.

### 22.3 Diagnostics and controlled retries

From `backend/`:

```bash
php bin/console messenger:failed:show --stats
php bin/console messenger:failed:show <message-id>
php bin/console messenger:failed:retry <message-id>
php bin/console app:stock-sync:dispatch --limit=100
php bin/console app:stock-sync:reconcile-shopware <connection-id> --verify
```

Diagnose a failure before retrying its specific message. Import-run failures,
Messenger failures, Sales checkpoint errors and stock-outbox errors are distinct
records; a queue retry does not necessarily repair the underlying import run.
Stock dispatch retains the Sales-readiness gate. Do not mark events dispatched
by hand or advance a checkpoint to hide a failure.

Integration import logs are visible on the connection screen and are written to
`backend/var/log/integrations/shopware-YYYY-MM-DD.log`. Runtime/worker errors
must also be captured by the supervisor/application logging. Avoid storing
credentials or full customer payloads in diagnostic output.

Monitor worker exits, queue backlog/failed messages, stale Sales checkpoints,
pending/failed stock events and unresolved exceptions. A stopped worker leaves
configured sync settings enabled but does not execute them.

## 23. Tenant isolation, permissions and audit history

Each merchant's warehouses, stock, suppliers, POs, customers, orders, integration
credentials and operational records belong to that merchant's tenant. Source
external IDs are resolved within the owning integration connection; a source
customer ID is not a global customer identifier across shops/companies.

The design remains a modular monolith with tenant-owned workflows. Later
database shards, dedicated workers or dedicated instances should route the
same workflows rather than replace them. Data visibility must follow active
tenant membership; changing a URL or submitting an arbitrary UUID must not
become a way to access another company.

Current operational mutation controls largely require the tenant owner. Full
staff membership/invitations and centralized role permissions are deferred.
The Members preview is not a completed authorization system. Do not invite
non-owner staff until those permissions and endpoint audits are completed.

The proposed groups are Owner/admin, Warehouse manager, Warehouse operator,
Purchasing, Sales/support and Viewer/auditor. They are planned permission groups,
not all available roles today. See [Fulfilment and access plan](fulfilment-and-access-plan.md).

Stock changes retain movements, document references and notes; user-triggered
operations record the acting user where supported. Background channel operations
are source-attributed rather than impersonating a picker. Counts, receipts,
pick versions, return request keys and locks guard supported operations against
stale writes or replay. These safeguards do not mean every endpoint has
completed a full role/security audit.

## 24. Worked example from supplier to customer return

Assume product `ABC-10` is stocked in pieces in Main warehouse. The supplier
sells cases of 12 at €10 per case and accepts a minimum of 2 cases. Main is
active and fulfilment-enabled. Starting balances are all zero, and the Shopware
connection has passed its cutover checks.

| Step | Business event | On hand | Reserved | Unavailable | Incoming | Available |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
| 1 | Create draft PO for 2 cases | 0 | 0 | 0 | 0 | 0 |
| 2 | Mark PO sent | 0 | 0 | 0 | 24 | 0 |
| 3 | Receive 1 good case | 12 | 0 | 0 | 12 | 12 |
| 4 | Receive remaining good case | 24 | 0 | 0 | 0 | 24 |
| 5 | Import new Shopware order for 3 pieces; reserve | 24 | 3 | 0 | 0 | 21 |
| 6 | Pick all 3 and confirm picking | 24 | 3 | 0 | 0 | 21 |
| 7 | Confirm first 2 shipped; reconcile explicit delivery | 22 | 1 | 0 | 0 | 21 |
| 8 | Confirm final 1 shipped | 21 | 0 | 0 | 0 | 21 |
| 9 | Receive 1 customer-returned piece into quarantine | 22 | 0 | 1 | 0 | 21 |
| 10 | Inspect and restock that piece | 22 | 0 | 0 | 0 | 22 |

The sent PO is €20 of supplier cost. The customer order keeps its independently
received selling price/tax/shipping totals. Matching the supplier invoice does
not change the stock in the table. Customer refund handling also does not
substitute for physical return receipt.

After the appropriate Sales checkpoint/outbox processing, Shopware receives
the available quantities: 12, 24, 21 and finally 22. Shipping reserved goods
does not deduct available stock a second time. If the quarantined return were
written off instead, on hand would return to 21, unavailable to 0 and available
would remain 21.

If the remaining unshipped unit were cancelled at step 7, on hand would stay
22, the remaining reservation would become 0 and available would rise to 22.
The two already shipped pieces would not be recreated by cancellation.

## 25. Verified scope and remaining release work

### 25.1 Current acceptance evidence

On 30 September 2026 in the disposable local Shopware test environment:

1. Guest storefront order **10214**, one unit of variant `M0058.8`, was imported
   through the queue, reserved and picked.
2. Shipment confirmed in Shopware was ingested by Connect, clearing the
   reservation and reducing physical stock from 180 to 179.
3. A physical return entered quarantine, appeared in Exceptions, and was
   inspected as damaged and written off. Saleable stock remained 179.
4. Publication mapping gaps were repaired or the absent demo SKU was hidden
   from that channel. Variant parents were excluded from direct stock writes.
5. Connect stock authority was enabled after reconciliation. Read-back of all
   **2,227 published leaf products found zero mismatches**; the outbox had
   2,227 dispatched and zero pending/failed events at that check.
6. The scheduled minute tick successfully queued and completed the real Sales
   poll and invoked the stock dispatcher after the worker cache was refreshed.
7. The backend suite passed **36 tests / 392 assertions**. Tests cover additional
   partial shipment, cancellation, line edit, return/replay and tenant-isolation
   scenarios. Frontend typecheck/build and mapping validation also passed.

These are recorded acceptance results, not a live monitor or a claim that a
development worker is still running after the session or machine restarts.
The live storefront test covered one full shipment; other lifecycle cases were
checked by the transactional tests.

### 25.2 Before production or staff rollout

1. Deploy supervised workers and operational monitoring; verify restarts and
   the correct environment/container.
2. Reconcile the real merchant's physical stock and mappings and perform its
   own controlled cutover.
3. Complete tenant membership/permissions and route audits before staff access.
4. Add the non-default warehouse deactivation guard for remaining balances and
   open operations; until then, follow the operating restriction in section 4.
5. Implement coordinated checkout/stock handling if strict oversell prevention
   or high-volume real-time operation is required.

### 25.3 Explicitly outside the current release

- Kimtec/Comtrade catalogue feeds and automatic distributor ordering until
  their adapters are built with access/specifications.
- Non-Shopware Sales/stock adapters and complete cross-platform migration/export.
- Shopware App webhooks, outbound shipment writing and a shared checkout
  reservation mechanism.
- Customer document binaries, password migration or payment-provider vault data.
- Payment capture/refunds, accounting settlement, credit notes and inventory valuation.
- Split-warehouse order fulfilment, automated repicking/wave picking, bin
  locations, barcode scanning and serial/lot tracking.
- Carrier-label purchase, dropshipping and external ERP/plugin warehouse mapping.

These limits keep the current workflow explicit. The platform-neutral core is
the basis for additional connectors; it does not by itself establish that the
same field coverage and lifecycle have been implemented for every platform.

## Related documentation

- [Warehouse operations quick guide](warehouse-operations-guide.md)
- [Shopware Sales and inventory cutover](shopware-sales-inventory-cutover.md)
- [Sales import field coverage](sales-import-field-coverage.md)
- [Fulfilment and access plan](fulfilment-and-access-plan.md)
- [Original multi-warehouse and purchasing plan](multi-warehouse-and-purchasing-plan.md)
