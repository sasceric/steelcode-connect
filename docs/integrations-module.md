# Integrations module

## Recommendation

Use an Integrations module in the UI. It presents Shopware, WooCommerce, OLX, Ananas and future systems as installable cards, but an installation creates a tenant-specific connection record. It does not install arbitrary code or accept customer-uploaded plugins.

This gives customers the familiar plugin experience while keeping the backend secure and maintainable.

## Model

### Connector definition

A connector definition is system-owned code and metadata. Examples are Shopware, WooCommerce, OLX and Ananas.

It defines:

- key, name, icon, documentation link and supported versions
- direction: source, channel, or both
- capabilities: import products, publish products, prices, stock, media, categories, attributes and orders later
- configuration schema and credential schema
- validation rules and connection-test handler
- field/category capability map

Definitions are deployed with SteelCode Connect. A tenant cannot upload PHP, JavaScript or arbitrary connector code.

### Connection

A connection is one tenant's configured instance of a definition.

Examples:

- A Shopware 6 shop used as a sales channel
- A WooCommerce store used as both source and channel
- An OLX shop account used as a channel
- A KimTec supplier feed used as a source

A connection stores its name, connector definition, direction, status, non-secret configuration, enabled capabilities, timestamps and last test/sync result.

### Connection secrets

Credentials are kept separately from normal connection configuration:

- API URL, sales channel ID and selected currency are normal configuration.
- API keys, OAuth client secrets, passwords and tokens are encrypted secrets.
- Secrets are write-only in the UI and never returned by the API.
- Encryption keys are environment-managed and rotated outside the database.

## Customer flow

1. Open Integrations.
2. Select an available connector card.
3. Click Add integration.
4. Give the connection a name and choose its role when the connector supports multiple roles.
5. Complete the connector-specific configuration form.
6. Click Test connection.
7. Resolve validation errors, then enable the required capabilities.
8. Save and run the first import or publish job.

The card becomes an installed connection card with status, last successful sync, enabled capabilities and actions for Configure, Test, Sync, Disable and Remove.

## Shopware catalogue import

A Shopware source import is one asynchronous run, not a sequence of manual entity imports. It first synchronizes sales channels, currencies, units, tags, taxes, delivery times, manufacturers, property groups and values, custom-field definitions and categories. It then imports products, variants, translations, regular and rule/tier prices, and applies mapped categories, properties, tags, units, visibility and delivery-time references. It imports canonical SEO URLs and then reconciles product downloads and cross-selling groups.

Every run exposes its current phase, counters and diagnostic log in the connection configuration page. Entries are persisted for the UI and written to a dedicated Shopware Monolog file. Credentials and raw source payloads are excluded from both logs.

## UI structure

The module should have:

- Installed: configured tenant connections and health/status
- Browse integrations: available connector cards with Add integration actions
- Connection detail: Overview, Configuration, Field mapping, Category mapping, Sync history, Logs and Danger zone tabs

Do not put every setting in one long form. Connector forms are generated from the definition schema and grouped into Connection, Credentials, Catalogue, Pricing, Inventory and Publishing sections.

## Connection lifecycle

    available → configuring → testing → active → disabled
                                  └────→ failed

Removing a connection disables jobs first, retains audit/sync history, and requires an explicit confirmation before deleting encrypted credentials.

## Delivery order

1. Connector definition registry and Integrations UI shell
2. Connection and encrypted-secret database model
3. Generic configuration form, test-connection job and status/log model
4. Shopware connector
5. WooCommerce connector
6. OLX connector
7. Ananas connector after its authentication, listing and category requirements are verified

## Important distinction

A future SteelCode Connect Shopware plugin for multi-stock is a separate Shopware-side extension. It is not the same thing as the Shopware connector shown in Integrations. The connector synchronizes data through APIs; the optional Shopware plugin adds Shopware-native warehouse functionality.
