# Properties and variants

SteelCode Connect has one shared vocabulary for product facts and variant choices. It is deliberately independent from Shopware, WooCommerce, OLX and supplier payloads.

## Terms

| SteelCode Connect | Meaning | Shopware | WooCommerce |
| --- | --- | --- | --- |
| Property group | Reusable definition such as Color, Size or Material | Property group | Global attribute taxonomy, e.g. `pa_color` |
| Property | A selectable value in a group, such as Blue or XL | Property group option | Attribute term |
| Product property | A descriptive/filterable value on a product | Product property | Product attribute where `variation=false` |
| Variant option | A group/value that creates child products | Configurator option | Product attribute where `variation=true` |
| Variant selection | One value per option group on a child product | Variant option value | Variation `attributes[]` item |

The same property group and values can be used for descriptive product properties, variant options, or both. The *assignment* decides its purpose; definitions are never duplicated.

## Canonical storage

```text
property_groups
  └── properties

products ── product_property_assignments ── properties
products ── product_variant_option_groups ── property_groups
child products ── product_variant_option_values ── properties
```

`property_groups` are tenant-owned and include a stable code, display type (`text`, `color`, `image`), position and filterability. `properties` belong to one group and include a stable code, display label, position, optional colour value and optional media reference.

Assignments retain their source (`manual`, `shopware`, `woocommerce`, `olx`, etc.) and source identifiers. This makes import updates traceable without making external IDs part of the canonical model.

## Variant lifecycle

1. Select one or more property groups as variant options on the parent product.
2. Select available properties for each group.
3. Generate combinations.
4. Each combination is a child `products` record with `parent_id` and one selected value per group.
5. Prices, stock, media, availability and identifiers can then differ per child product.

Child products are never a separate variant entity. This matches Shopware’s parent/child behaviour and maps cleanly to WooCommerce variable products and variations.

## Connector mapping

### Shopware

- Shopware property groups map to `property_groups`.
- Shopware property-group options map to `properties`.
- A product’s `properties` association maps to product-property assignments.
- A product’s configurator settings map to variant-option group assignments.
- A child’s option values map to variant selections.

### WooCommerce

- A global attribute taxonomy such as `pa_color` maps to one property group.
- Its attribute terms map to properties.
- A product attribute with `variation: false` becomes a product property.
- A product attribute with `variation: true` becomes a variant option group.
- Each WooCommerce variation `attributes[]` entry becomes a selected value on the child product.
- Product-local WooCommerce attributes are imported as a tenant property group scoped to that product, unless an administrator maps them to an existing global group.
- `meta_data` remains a namespaced extension value, not a property.

### OLX and other marketplace channels

Marketplace category requirements are imported into channel category fields. A field is mapped to a canonical property only when its semantics match. Channel-only requirements remain channel overrides; they do not create global catalogue properties.

## Implementation order

1. Property Groups catalogue page and CRUD.
2. Properties within each group and their display metadata.
3. Product property assignment tab.
4. Replace product-local option groups with canonical group assignments while preserving current products.
5. Generate variants from canonical properties.
6. Connector import/export mappings and category-required-field validation.

