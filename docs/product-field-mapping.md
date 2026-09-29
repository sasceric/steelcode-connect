# Canonical product fields

SteelCode Connect uses one parent/child `products` model. Shopware children and WooCommerce variations become child products; OLX and Ananas listings normally become standalone products.

## Canonical fields

| Area | Canonical fields | Shopware | WooCommerce |
| --- | --- | --- | --- |
| Identity | id, parent_id, type, SKU, EAN/GTIN, manufacturer number | product number, EAN, parent ID | SKU, global unique ID, variation parent |
| Content | translations: name, short/long description, SEO title/description; locale-aware canonical SEO URL in `seo_urls` | product translations and SEO URL records | product/variation content and permalink slug |
| Commerce | active, visibility, tax, prices, list price, cost, scheduled/tier/rule prices | active, visibilities, tax, prices | status, catalog visibility, regular/sale/cost prices |
| Fulfilment | weight, dimensions, shipping class, delivery time, stock policy | dimensions, delivery time | dimensions, shipping class, backorders |
| Classification | categories, tags, manufacturer, properties, options | categories, tags, properties/options | categories, tags, attributes/variation attributes |
| Relations | media, downloads, cross-selling, related/upsell products | media, cross-sell | images, downloads, upsell/cross-sell |
| Extensions | namespaced JSON key/value with locale support | custom fields | meta_data key/value |

Unknown connector data is kept in source records and namespaced extension values; it is never discarded.
