# Sales import field coverage

The Sales importer reads Shopware Admin API customer and order entities in pages of 25. It stores normalized records for the fields SteelCode Connect actively uses **and** a sanitized source-field snapshot for other API-exposed business attributes. Re-importing the same external ID updates the records rather than creating duplicates. The source snapshot keeps Shopware field names so future platform adapters can map them deliberately; it is not itself a WooCommerce, PrestaShop, or Shopify export format.

## Customer

- Normalized: identity, email, name, company, customer number, account type, title, active/guest state, VAT IDs, default-address IDs, and all saved addresses.
- Source snapshot: all other Admin API attributes returned by Shopware, including language, group, sales channel, last payment method, salutation, affiliate/campaign codes, opt-in dates, birthday, login/order statistics, custom fields, and timestamps. Requested group, language/locale, salutation, payment method, and tag association data is kept in `_associations`; relationship IDs are kept in `_relationships`.
- Each customer address keeps its normalized address fields plus its full sanitized API attribute snapshot and custom fields.
- The existing catalogue reference import reads Shopware custom-field sets, field definitions, and `custom-field-set-relation` assignments, so customer/order custom-field definitions can be retained when that reference import has run. The Sales import itself preserves field values; it does not rebuild definitions independently.
- Customer/order profiles are separate: an order's buyer information is a historical snapshot, while the order also links to the imported customer where an external customer ID exists.

## Order

- Normalized: customer and billing/shipping snapshots, number/date/status, currency/tax/shipping totals, all line items (including non-product lines), payment transactions, deliveries, tracking, and warehouse allocation-related quantities.
- Source snapshot: other Admin API attributes such as currency factor, sales channel/language, price structure, comments, affiliate/campaign codes, rounding, tax calculation type, custom fields, and timestamps. Order tags are requested and retained as source associations. The mapper can preserve document **metadata** if supplied, but the current default order import does not request the documents association or download document files.
- Line items, payments, deliveries, order customer, and order addresses retain sanitized source attributes in their respective snapshots. Their original external IDs remain available for later mapping.

## Deliberate exclusions and next migration work

Passwords, password hashes, registration/guest access hashes, deep-link codes, API keys/tokens, credentials, IP addresses, and user-agent data are excluded recursively. Copying those values would be unsafe and would not create working customer logins on another platform. Destination accounts need a deliberate invite/password-reset flow.

This importer is not yet a complete cross-platform migration feature. Document binaries, customer reviews/wishlists, promotion usage, payment provider vault data, and platform-specific extension entities are not transferred. Shopware source IDs do not automatically become valid IDs on another platform; a destination exporter must translate languages, customer groups, tax/currency/shipping/payment methods, tags, custom-field definitions and values, and historical order states. Consent fields should be preserved for audit, not interpreted as automatic marketing consent on a destination platform. Each connector needs a field-coverage matrix and round-trip tests before claiming full migration support.

The Shopware field inventory was checked against the installed Shopware Core definitions (`CustomerDefinition`, `CustomerAddressDefinition`, `OrderDefinition`, `OrderCustomerDefinition`, `OrderAddressDefinition`) rather than a hand-picked list of example fields.
