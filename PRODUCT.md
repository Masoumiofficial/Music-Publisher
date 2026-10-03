# PRODUCT — Sajad Music Publisher

## What it actually is

An **editorial operations plugin** for Persian MP3 blogs: staff fill a form, WordPress creates a post with the meta keys typical of Iranian music themes, and a companion download host fetches MP3/MP4 files.

It is **not** a music store, player, licensing suite, or DistroKid-style publisher.

## Actual features

- Admin panels: single, remix, nohe, music video, album, leech
- Cover upload or URL, 128/320 links, lyrics, credits
- Draft vs publish
- Configurable download-host URLs and category IDs
- Dark/light admin UI (RTL)

## Target users

Operators of Persian music download WordPress sites who already have a theme expecting keys such as `music320`, `musics_type`, `fifu_image_url`, and Yoast meta.

## Limitations (honest)

- Tight coupling to one theme/meta convention
- No public archive, player, or WooCommerce
- Download host is a separate insecure-by-default PHP drop (now hardened, still operator-managed)
- SEO content is formulaic (“دانلود آهنگ …”)
- Weak productization vs generic “post + media library”

## Commercial value

**Niche but real** for RTL-Theme / راست‌چین *if* sold as “پنل ارسال موزیک برای قالب‌های دانلود آهنگ”, not as a generic publisher.

Without a matching theme or documented meta map, **the plugin is too weak to sell as a standalone mass-market product**.

## Positioning

“Staff publishing console for Persian music blogs with off-site download hosting.”

Commercial readiness after this pass: **NEEDS REVIEW** (no live WordPress test).
