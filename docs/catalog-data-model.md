# Catalog data model

This document defines the proposed first catalog model for SteelCode Connect. It is intentionally independent from KimTec, Shopware and OLX payload formats: connectors translate their data into this model instead of shaping the database.

## Core rules

1. Every catalog record belongs to exactly one tenant.
2. A product is the customer-facing parent record. A sellable item is always a product variant.
3. A simple product still receives one default variant. This keeps pricing, stock, identifiers and channel listings consistent.
4. Supplier payloads are retained unchanged for traceability. They never become the source of truth for the UI directly.
5. Money is stored as an integer amount in the smallest currency unit, with an ISO currency code per price. Stock quantities use decimal precision.
6. Imports must be repeatable: the same source record updates its existing record rather than creating duplicates.

## Product identity

The proposed matching order for an imported supplier record is:

1. Existing source record for the same tenant, source connection and external ID.
2. Existing tenant variant with the same supplier SKU.
3. Existing tenant variant with the same EAN/GTIN.
4. Create a new product and its default variant.

The first matching rule is authoritative for an update. SKU and EAN matching is only used when an import is first seen. Ambiguous matches are not merged automatically; the import is marked for review.

## Tables

### products

The parent/catalog record shown in the product list.

| Column | Notes |
| --- | --- |
| id | UUIDv7 primary key |
| tenant_id | Required tenant ownership |
| name | Customer-facing product name; localized content belongs in product translations |
| description | Optional rich-text/plain-text description |
| manufacturer_id | Optional canonical manufacturer |
| status | draft, active, archived or blocked |
| default_variant_id | Optional convenience pointer after the first variant exists |
| created_at, updated_at | UTC timestamps |

Index product lists by (tenant_id, status, updated_at). SEO routes are stored separately in `seo_urls`, so a product can have a route per locale and, when required, per sales channel.

### Manufacturers

Manufacturers are a separate tenant-owned entity, not a free-text product field. A manufacturer has translated name and description, website, logo/media reference and timestamps. Its SEO routes live in `seo_urls`. Product imports match a manufacturer by a normalized name, but ambiguous supplier brands remain pending review instead of being silently merged.

### seo_urls

SEO URLs are reusable routes for catalogue entities rather than columns on product, category or manufacturer translation tables.

| Column | Notes |
| --- | --- |
| tenant_id | Required tenant ownership |
| locale_id | Required locale scope |
| sales_channel_id | Optional connector/channel scope; `NULL` is the global route used by the catalogue UI |
| entity_type, entity_id | Polymorphic target; currently `product`, `category` or `manufacturer` |
| path | Normalized relative path without a leading slash |
| canonical, active | Identifies the current public route |
| modified, source | Distinguishes generated, imported and manual URLs |
| redirect_code, redirect_target_id | Preserves a previous canonical path as a 301 redirect when it changes |

An active path is unique within its tenant, locale and sales-channel scope. A target has one active canonical path per locale and scope. Connector adapters can store an external platform’s SEO route in the scoped record, while WooCommerce-style single slugs map to the global record.

### Suppliers and source connections

Supplier and manufacturer are different concepts. A manufacturer makes the product; a supplier sells or provides its feed.

A supplier is a tenant-owned business entity with name, code, contact details, website, default currency and active status. A supplier can have multiple source connections, for example a KimTec feed and a CSV import. The technical connection owns credentials, field mappings, sync configuration and source records; it references the supplier but does not replace it.

This allows the same product to have one manufacturer and several supplier offers, each with its own cost, availability and source identifier.

### product_variants

The sellable unit. A simple product has one default variant.

| Column | Notes |
| --- | --- |
| id | UUIDv7 primary key |
| tenant_id, product_id | Required ownership and parent |
| sku | Required tenant SKU; unique per tenant when present |
| ean | Optional EAN/GTIN; indexed, but not globally unique |
| name | Optional variant title, such as colour or size |
| option_values | JSON object of normalized option values |
| weight_grams | Optional integer weight |
| status | active, inactive or blocked |
| created_at, updated_at | UTC timestamps |

Unique indexes: (tenant_id, sku) and (tenant_id, product_id, name). Index searching and publishing by (tenant_id, status, updated_at).

### Currencies and prices

SteelCode Connect is multi-currency. Currency is never a tenant-wide hard-coded setting and a variant can have prices in several currencies at the same time.

| Table | Purpose |
| --- | --- |
| currencies | Shared ISO 4217 registry: code, decimal precision, symbol and metadata |
| tenant_currencies | Currencies enabled for one tenant, including its default currency |
| product_prices | A variant price for one currency, price type and optional quantity tier |

The currency code is globally unique. A tenant currency row is unique per tenant and currency, and exactly one enabled row is the tenant default. Product prices contain tenant ownership, variant, currency, price type, net amount, gross amount, tax rate, quantity range, source and timestamps. Price types for the first release are cost, regular and sale. The unique key is tenant, variant, currency, price type and quantity start.

When a connector imports a valid ISO currency that does not exist yet, it creates the shared currency record from trusted ISO metadata and enables it for the importing tenant. This follows the practical Shopware-style behaviour without allowing arbitrary codes from a supplier payload. A connector can also import existing currencies from a connected Shopware shop.

Each channel connection chooses its target currency and price rule. Publishing never assumes that every channel accepts every currency: the connector either uses a matching price, converts using an explicitly configured exchange-rate policy, or blocks publication with an actionable validation message.

### Warehouses and inventory levels

Inventory is warehouse-ready from the first migration even though the first channel release publishes aggregated stock.

| Table | Purpose |
| --- | --- |
| warehouses | Tenant warehouse/location, code, name, address and active flag |
| inventory_levels | Variant quantity, reserved quantity, available quantity and reorder threshold for one warehouse |

The unique key for inventory levels is tenant, warehouse and variant. Available stock is quantity minus reserved quantity; the first channel stock policy publishes the sum of available stock across the selected warehouses.

This avoids a later data migration when multi-warehouse fulfilment is introduced.

### product_media

Images and other product assets. Files live in object storage; the database stores metadata only.

| Column | Notes |
| --- | --- |
| id | UUIDv7 primary key |
| tenant_id, product_id, variant_id | A medium belongs to a product and may optionally target a variant |
| storage_key | Object-storage path, never a public URL |
| original_url | Optional supplier URL for import traceability |
| media_type | image for MVP |
| alt_text | Optional localized accessible label |
| sort_order | Stable display order |
| checksum | Detect duplicate downloads |
| created_at, updated_at | UTC timestamps |

Unique indexes: (tenant_id, product_id, sort_order) and (tenant_id, checksum).

### product_properties

Normalized specifications that do not define a variant, for example screen size or material.

| Column | Notes |
| --- | --- |
| id | UUIDv7 primary key |
| tenant_id, product_id, variant_id | Product-level or variant-level property |
| property_key | Stable machine key, e.g. screen_size |
| label | Human-facing label |
| value | JSON value to support text, number, boolean or a list |
| unit | Optional unit, e.g. mm or kg |
| source | manual or imported |
| created_at, updated_at | UTC timestamps |

Unique index: (tenant_id, product_id, variant_id, property_key).

### Attributes, variant options and category requirements

One generic property table alone is not enough. Shopware distinguishes properties from variant-generating options, while OLX exposes required fields by listing category. SteelCode Connect uses the same separation:

| Model | Purpose |
| --- | --- |
| attributes and attribute_options | Reusable typed definitions such as colour, material, screen size or energy class |
| product_attribute_values | Product or variant values for a reusable attribute |
| option_groups and option_values | Definitions that generate product variants, such as colour and size |
| categories | Central tenant category tree |
| category_attribute_requirements | Attribute required/optional rules for a central category |
| channel_categories | Imported category trees per connected channel |
| channel_category_fields | Fields required by a channel category, including OLX category attributes |
| attribute_mappings | Maps one central attribute to a channel-specific field or option |

The product editor shows all product attributes, then highlights the required fields for each selected category/channel. A product can be saved as draft with incomplete fields, but publishing is blocked until the selected channel's required category fields have values.

Shopware properties and options map to the first two semantic groups. For OLX, its category-specific fields are retained as channel category fields and mapped to central attributes where possible. An OLX-only requirement stays in the channel override; it does not pollute the common product model.

### source_product_records

An immutable import trace plus the current normalized supplier values.

| Column | Notes |
| --- | --- |
| id | UUIDv7 primary key |
| tenant_id, connection_id | Source ownership |
| external_id | Supplier record identifier |
| product_id, variant_id | Resolved central records; nullable while review is required |
| source_sku, source_ean | Indexed identifiers from the supplier |
| source_name, source_brand | Indexed diagnostic fields |
| source_price_amount, source_currency | Latest source price |
| source_stock_quantity | Latest source stock |
| raw_payload | Original JSONB payload |
| payload_hash | Detect unchanged imports |
| sync_status | imported, matched, needs_review, rejected or failed |
| last_seen_at, created_at, updated_at | UTC timestamps |

Unique index: (tenant_id, connection_id, external_id). Index pending review by (tenant_id, sync_status, updated_at).

## Lifecycle

- New imports create or update a source product record.
- A successful match creates or updates a product and variant in one transaction.
- Manual edits affect central catalog fields only. The source record remains unchanged for auditability.
- A later source update can update source-owned fields according to source priority rules; manually locked fields are protected.
- Archived products and variants are never deleted by an import.

## Product editor

The product detail screen should follow the familiar Shopware pattern: one product page with focused tabs, plus a persistent save action and status indicator.

| Tab | Content |
| --- | --- |
| General | Name, brand, product number, EAN/GTIN, status, tags and category assignments |
| Description and media | Short/long description, media gallery, cover image, alt text and downloads later |
| Variants | Variant option groups, generated combinations, SKU/EAN, variant media and enable/disable state |
| Prices | Cost, regular and sale prices by currency, tax rate, quantity tiers and channel price rules |
| Inventory | Warehouse matrix, available/reserved stock, reorder threshold, backorders and aggregate channel stock |
| Attributes | Structured specifications, technical data and channel-mapped attributes |
| Delivery | Weight, dimensions, shipping class, delivery time and country restrictions |
| SEO | Canonical SEO URL, meta title, meta description and search keywords |
| Channels | Per-channel category, visibility, title/description overrides, currency/price selection and publication state |
| Source and history | Supplier records, raw import payload reference, field ownership, sync events and manual changes |

The editor stores only normalized canonical data. Connector-specific fields belong in channel overrides or mapped attributes, not in the core product table.

## Channel compatibility strategy

We should not make one database table identical to Shopware, WooCommerce and OLX. Their models differ. The central catalog is the canonical superset and each connector owns a capability map and validation rules.

| Capability | SteelCode Connect | Shopware | WooCommerce | OLX |
| --- | --- | --- | --- | --- |
| Parent products and variants | Native | Native | Native | Listings are primarily single items |
| Multiple currencies | Native price rows | Sales-channel currency/price support | Store currency is normally primary | Listing price is market-specific |
| Warehouse-level inventory | Native | Aggregate stock in core | Aggregate stock in core | Quantity per listing |
| Product attributes | Typed properties | Properties/custom fields | Attributes/meta data | Category-specific listing fields |
| Media | Ordered product/variant media | Product media | Product/variation images | Listing images |
| Publish state | Draft/active/archived | Visibility/active | Draft/publish | Draft then explicit publish |

The current official APIs confirm that WooCommerce variations expose SKU, GTIN, prices, stock, dimensions, images and attributes, while OLX listings expose title, descriptions, price, quantity, images and a separate draft-to-publish flow. See the [WooCommerce variation API](https://developer.woocommerce.com/docs/apis/rest-api/v3/product-variations/) and [OLX listings API](https://api-documentation.olx.ba/listings).

## Multi-stock strategy

### First release: no Shopware plugin

Use warehouses and inventory levels inside SteelCode Connect, then publish one aggregated available quantity per variant/listing:

    available = sum(quantity - reserved) across the connection's selected warehouses

This works with Shopware, WooCommerce and OLX, keeps the integration simple and lets each connector publish the stock model it supports today. It is the recommended first release.

### Later: optional Shopware multi-stock extension

If customers need warehouse selection, split fulfilment or warehouse-aware availability inside Shopware itself, build a dedicated SteelCode Connect Shopware plugin. The plugin should:

1. Register its own warehouse/inventory tables in the Shopware installation.
2. Synchronize warehouse balances from SteelCode Connect through authenticated API/webhooks.
3. Keep Shopware's normal product stock equal to the configured aggregate, so storefront and existing integrations continue to work.
4. Add an Administration warehouse-stock view and, only when needed, fulfilment allocation logic.

This is practical for self-hosted Shopware installations, where plugins can extend the database and administration. It should be a separate product milestone, not a prerequisite for the first connector. Do not add multi-stock through direct database writes from SteelCode Connect.

## Decisions required before migrations

1. Confirm the proposed matching order: source external ID → SKU → EAN → create.
2. Confirm that SKU is unique within a tenant.
3. Confirm the initial enabled currencies and the exchange-rate source/policy for conversion when a channel requires a currency not present on the variant.
4. Confirm that the first Shopware/WooCommerce/OLX release publishes aggregate warehouse stock, with the Shopware plugin deferred.
5. Decide whether the first import should publish products as draft or active. The proposed safe default is draft.

After these decisions, the first migration can create products, variants, prices, warehouses, inventory levels, media, properties and source records together with their tenant isolation and indexes.
