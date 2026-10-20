# Fast X Horizon public company and policy imagery

Images are downloaded into `resources/themes/default/public/assets/front-end/img/company-policy/` and mirrored to the active web root at `public/assets/front-end/img/company-policy/`. No public page hotlinks an image at runtime.

The files use Unsplash's image delivery transformation (`fm=webp`, bounded width, quality 82) and are stored as WebP for the active default theme. Unsplash's free-use terms are documented at [Unsplash License](https://unsplash.com/license).

| Page | Local filename | Original source URL | Platform / creator | Use note |
|---|---|---|---|---|
| About Us | `about-hero.webp` | [Unsplash image source](https://images.unsplash.com/photo-1556740749-887f6717d7e4) | Unsplash; creator metadata not exposed by the direct delivery URL | Local, cropped hero image for marketplace operations |
| About Us | `about-intro.webp` | [Unsplash image source](https://images.unsplash.com/photo-1556761175-b413da4baf72) | Unsplash; creator metadata not exposed by the direct delivery URL | Local supporting collaboration image |
| About Us | `about-global.webp` | [Unsplash image source](https://images.unsplash.com/photo-1526778548025-fa2f459cd5c1) | Unsplash; creator metadata not exposed by the direct delivery URL | Local global-commerce supporting image |
| Terms & Conditions | `terms-hero.webp` | [Unsplash image source](https://images.unsplash.com/photo-1450101499163-c8848c66ca85) | Unsplash; creator metadata not exposed by the direct delivery URL | Local business-document hero |
| Privacy Policy | `privacy-hero.webp` | [Unsplash image source](https://images.unsplash.com/photo-1563013544-824ae1b704d3) | Unsplash; creator metadata not exposed by the direct delivery URL | Local secure-digital-commerce hero |
| Refund Policy | `refund-hero.webp` | [Unsplash image source](https://images.unsplash.com/photo-1556742049-0cfed4f6a45d) | Unsplash; creator metadata not exposed by the direct delivery URL | Local customer-payment-resolution hero |
| Return Policy | `return-hero.webp` | [Unsplash image source](https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d) | Unsplash; creator metadata not exposed by the direct delivery URL | Local parcel/fulfilment hero |
| Cancellation Policy | `cancellation-hero.webp` | [Unsplash image source](https://images.unsplash.com/photo-1554224155-6726b3ff858f) | Unsplash; creator metadata not exposed by the direct delivery URL | Local order-processing hero |
| Leadership — Muhammad Younas | `public/assets/front-end/img/leadership/muhammad-younas-profile.png` | User-provided file: `Professional South Asian Corporate Headshot.png` | Supplied for the Muhammad Younas profile; not an online stock image | Approved light-background profile photo supplied for the Leadership CMS |
| Leadership — Muhammad Hamza | `public/assets/front-end/img/leadership/muhammad-hamza-profile.png` | User-provided file: `Polished Outdoor Corporate Headshot (1).png` | Supplied for the Muhammad Hamza profile; not an online stock image | Approved green-background profile photo supplied for the Leadership CMS |

The image files are development/deployment assets, not user-uploaded content. Admin can replace hero and supporting images through the existing Business Pages editor; uploaded replacements are validated as JPG/JPEG/PNG/WebP and converted to WebP by the existing file manager.
