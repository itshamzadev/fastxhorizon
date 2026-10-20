# Fast X Horizon Demo Image Sources

## Product images

The source photographs are royalty-free Unsplash images downloaded locally and
converted to WebP. The original source IDs and category usage are documented in
`FASTXHORIZON_DEMO_PRODUCT_IMAGE_SOURCES.md`. The marketplace image pass uses
locally generated optimized variants from those downloaded source photographs;
runtime pages never hotlink Unsplash.

## Store logos and banners

The six store logos and banners are original Fast X Horizon demo vector artwork
created for this local catalog. They contain no third-party logos, trademarks,
or commercial brand imagery.

| Store | Logo | Banner | Usage |
| --- | --- | --- | --- |
| NovaGear Store | `stores/novagear.svg` | `stores/novagear-banner.svg` | Electronics/accessories |
| HomeNest Living | `stores/homenest.svg` | `stores/homenest-banner.svg` | Home/kitchen/living |
| UrbanWeave | `stores/urbanweave.svg` | `stores/urbanweave-banner.svg` | Fashion |
| StepCraft | `stores/stepcraft.svg` | `stores/stepcraft-banner.svg` | Footwear |
| AuraScents | `stores/aurascents.svg` | `stores/aurascents-banner.svg` | Beauty/fragrance |
| PlaySphere | `stores/playsphere.svg` | `stores/playsphere-banner.svg` | Toys/kids/lifestyle |

All assets are local, optimized for the existing 6Valley storage convention,
and intended as replaceable demo artwork.

## Category artwork

Top-level demo category icons use the same local optimized product-artwork
pipeline and are copied into `storage/app/public/category/` as
`fxh-demo-category-{key}.webp`. They are category-matched, local assets and do
not hotlink third-party URLs at runtime.
