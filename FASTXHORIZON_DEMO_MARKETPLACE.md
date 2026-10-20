# Fast X Horizon Demo Marketplace

## Demo stores

The local demo catalog now contains exactly six seller stores. Existing demo
seller identities are reused and updated by email, so rerunning the seeder does
not create duplicate sellers or shops.

| Store | Focus | Products |
| --- | --- | --- |
| NovaGear Store | Electronics, automotive tech, and accessories | Electronics/automotive demo listings |
| HomeNest Living | Home, kitchen, grocery, and daily living | Home, grocery, and most essentials |
| UrbanWeave | Men/women fashion and bags | Clothing, bags, and backpacks |
| StepCraft | Shoes and sneakers | Footwear listings |
| AuraScents | Fragrance, beauty, and personal care | Beauty listings |
| PlaySphere | Toys, kids, sports, and lifestyle | Toys and sports listings |

Every store has a local logo, category-specific banner, description, stable
slug, approved demo seller account, and active storefront data.

## Marketplace taxonomy

The seeder creates 13 namespaced top-level categories (including Perfumes &
Fragrances, Shoes & Footwear, and Bags & Accessories), 41 practical
subcategories, and 12 reusable product attributes. Attribute values are stored
in each product's existing `choice_options` structure so the installed
6Valley product/variant UI remains compatible.

The catalog also includes 40 original fictional brands spanning electronics,
fashion, beauty, fragrance, footwear, bags, home, toys, watches, and grocery.
Brand names are deliberately neutral and do not imply affiliation with famous
trademarks.

## Product imagery

The demo seeder assigns a deterministic unique primary path to each of the 100
demo products. Product images are stored under:

- `public/assets/demo-marketplace/products/`
- `storage/app/public/product/thumbnail/fxh-demo-marketplace/products/`
- `storage/app/public/product/fxh-demo-marketplace/products/`

There are 10 product variants per marketplace category plus specific visual
families for shoes, watches, bags, perfume, and kitchen items. Gallery images
remain limited to the existing 20 demo gallery products.

The current audit target is 100 demo products with 100 unique primary image
paths. Products are assigned to their primary category plus relevant secondary
category links for shoes, bags, and fragrance discovery.

## Store assets

Source assets are in `public/assets/demo-marketplace/stores/` and are copied to
the existing 6Valley shop storage paths. No storefront route or product/cart
logic was changed.

## Safe update behavior

Run in local/testing only:

```text
php artisan migrate --force
php artisan db:seed --class=FastXHorizonDemoProductSeeder --force
```

The seeder updates records with the `fxh-demo-` slug/email prefixes, keeps the
catalog at 100 products, reuses existing demo customers, and does not delete
non-demo products, sellers, customers, or orders.
