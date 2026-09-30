# Fulfilment and access plan

## Current boundary

Connect already reserves current orders in one warehouse, releases unshipped
reservations on cancellation, and deducts shipped line quantities as Shopware
delivery events arrive. Historical orders never affect stock. The Pick lists
screen now derives printable, current quantities from **reserved allocations**;
it does not create a second stock balance or mark an order shipped.

Shopware is currently the authority for shipment state. A picker must confirm
the actual shipment in Shopware. Connect then ingests that delivery and deducts
the shipped quantity once. Do not add a local “Ship” action until an outbound
Shopware fulfilment adapter and echo-safe idempotency are in place. The manual
partial-shipment reconciliation action remains a controlled exception, not a
normal pick/ship path.

## Operational status

1. **Durable picking is implemented:** a reserved order has a versioned pick task with
   recorded quantities, short-pick state, actor, and reservation-change guard.
   Picking alone never changes on-hand or reserved stock. The printable list
   remains available. Assignment to individual staff awaits tenant roles;
   only the owner may change pick tasks for now. A task with already-picked
   goods cannot silently restart after an order edit or shipment; those goods
   require manual resolution before a new task is created.
2. **Shipment ownership remains with Shopware** for the first release. Connect
   observes delivery positions, deducts shipped stock exactly once, and leaves
   ambiguous partial shipments for controlled human reconciliation. There is
   deliberately no local Ship button or outbound Shopware shipment writer.
3. **Order-linked returns are implemented:** physical receipts are tied to
   shipped order lines and their original warehouse. The system caps cumulative
   returns at shipped quantity, stores reason/condition/actor, and accepts an
   idempotency key. Quarantined goods increase physical but unavailable stock;
   inspected goods can be released to sellable stock or written off. Immediate
   sellable restocks increase available stock. A cancellation is never a return.
   This does not initiate a Shopware refund, RMA, or credit note.
4. **The exception workbench is implemented:** tenant-scoped, paged categories
   cover unmatched products, unallocated orders, ambiguous shipments, failed
   Sales sync, failed stock publication, the latest unresolved import run,
   negative available balances, and quarantined returns. Actions navigate to
   the relevant record; supported retries use existing allocation or queue
   paths. Historical import failures superseded by a later successful run are
   not shown as active exceptions.
5. **Live order acceptance and test-shop stock cutover passed:** disposable
   Shopware storefront order 10214 was imported, reserved, picked, shipped,
   returned into quarantine, and written off. Both systems ended with 179
   sellable units for its SKU. The test catalogue's mapping gaps were repaired
   or unpublished, and a batched reconciliation brought all 2,227 published
   leaf products to zero Shopware-vs-Connect stock mismatches. Connect stock
   authority is enabled on this disposable connection. The local Messenger
   worker is running for this test session; a supervised worker is still needed
   for unattended operation. Transactional tests cover partial shipment,
   cancellation, restock, and replay; the live run covered one full shipment.
   See [the cutover record](shopware-sales-inventory-cutover.md).

Bin locations, barcode scanning, wave picking, carrier-label purchase,
accounting settlement, and cross-platform export are later capabilities, not
prerequisites for the first operational release.

## Tenant roles

Use **tenant-scoped** roles and server-side permissions. Symfony's global
`ROLE_USER` means “signed in”, not “allowed to change this tenant's stock”.
One person may need more than one operational permission group; do not force
the business into exactly one job title.

| Permission group | Intended permissions | Explicit exclusions |
| --- | --- | --- |
| Owner/admin | Members, integration credentials, stock ownership, all operations, policy and override approvals | None within the tenant |
| Warehouse manager | Warehouse configuration, approve/post counts, adjustments, transfers, return dispositions, shipment corrections | Credentials, tenant billing, member administration |
| Warehouse operator | View assigned orders/addresses and stock; pick, receive, enter counts, dispatch/receive transfers | Costs, invoice approval, stock overrides, integration settings |
| Purchasing | Suppliers, offers/costs, POs, receiving review, supplier invoices and replenishment | Customer personal data beyond required delivery context, stock overrides, credentials |
| Sales/support | Customers and orders, exception triage, initiate return requests | Physical return posting, stock adjustments, supplier costs, credentials |
| Viewer/auditor | Read-only operational and audit views according to data scope | All mutations |

High-impact stock adjustments, count posting, manual shipment reconciliation,
and return-to-sellable decisions should require manager/owner permission and
record actor, time, reason, and before/after quantity. Consider a second
approval for large variances later; do not impose it on every small business.

## Existing access gap

`tenant_memberships.role` exists, but registration creates only `owner`.
Settings → Members retains its intended member-list, search, role-selector, and
invitation layout as an explicit preview. The signed-in owner's row is real;
example rows are labelled and controls remain disabled.
Most API mutations use scattered owner checks, while some tenant endpoints
accept any signed-in member. **Do not expose non-owner invitations yet.**
First add real membership management, centralize tenant/permission checks,
audit every inventory, purchasing, sales, and integration route, and add
cross-tenant/role tests. Hide unavailable UI actions only after the API checks
are in place. Background connector workers need a separate audited system
identity, not a human membership.
