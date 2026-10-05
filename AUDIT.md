# AUDIT — Sajad Music Publisher 1.0.0

Date: 2026-10-03  
Source: repository ZIP `music-publisher.zip` (plugin slug `music-publisher`, text domain `sajad-music-publisher`).

WordPress was **not** available in this environment. Findings are from static review of PHP/JS/CSS and host scripts.

## A. Architecture

The plugin is a **WordPress admin publishing console** for Persian music blogs. It does **not** register public CPTs, shortcodes, or frontend templates. It creates standard `post` objects, writes theme-oriented post meta, optionally sets custom taxonomies if they already exist, and POSTs file URLs to a **separate download host**.

### Main execution flow

1. `music-publisher.php` defines constants and, on `plugins_loaded`, instantiates `SMP_Settings`, `SMP_Admin_Pages`, `SMP_Post_Handler`.
2. Admin menus under “Music panel” (`edit_posts`; settings `manage_options`).
3. Forms POST to `admin-post.php` with actions `smp_submit_*`.
4. Handler sanitizes input, inserts a post, stores meta, downloads cover/audio into uploads, then `wp_remote_post`s the download host.

### Data flow

User form → nonce + capability → `wp_insert_post` + post meta (`music320`, `fifu_image_url`, Yoast keys, etc.) → local temp files in uploads → remote host scripts in `hostdl/`.

### Database

No custom tables. Uses `smp_options`, post meta, terms.

### AJAX / REST

None.

### Assets

Admin CSS/JS enqueued when `page` is an SMP slug.

## B. Functionality inventory

| Feature | Files | Status |
| --- | --- | --- |
| Single / remix / nohe publish | admin-pages, post-handler | Working with theme meta assumptions |
| Music video | same | Working; host field names were mismatched (fixed) |
| Album (15 tracks) | same | Working; host field names were mismatched (fixed) |
| Leech | same | Working as remote copy helper |
| Settings | class-smp-settings | Working |
| Download-host scripts | hostdl/*.php + getID3 | Standalone, historically unauthenticated |
| Dark/light UI | admin.css, admin.js | Working |

Unused form fields: `fatxt`, `entxt`, `txtseo` were never consumed by the handler (still in UI; not removed to preserve layout).

## C. Bug inventory

### Critical

| ID | Problem | Root cause | Fix |
| --- | --- | --- | --- |
| C1 | Host scripts accept unauthenticated POST and `file_get_contents` any URL (SSRF / open leech) | Standalone PHP with no auth | Shared secret + URL allowlist + cURL protocols |
| C2 | Path traversal via artist/track names (`../`) written under uploads / DOCUMENT_ROOT | Unsanitized concatenation | `safe_name` / `smp_host_safe_name` |
| C3 | WP ↔ host POST key mismatch (album `track_*` vs `hs_*`; video `link1080` vs `hs_video1080p`; music `enname` vs `artist_en`) | Divergent scripts | Send both key sets; host reads both |

### High

| ID | Problem | Fix |
| --- | --- | --- |
| H1 | Duplicate settings submenu (`SMP_Settings::add_settings_page` + admin menus) | Settings class no longer registers a menu |
| H2 | Contributors could force `publish` | Require `publish_posts` |
| H3 | Notices via `$_GET['smp_msg']` (length/openness) | User transient flash |
| H4 | Hardcoded `ABSPATH/wp-content/uploads` | `wp_upload_dir()` |
| H5 | `wp_set_object_terms` on taxonomies that may not exist | `taxonomy_exists` guard |

### Medium

| ID | Problem |
| --- | --- |
| M1 | Remix/nohe host endpoints referenced but missing files | Added `curl-remix.php` / `curl-nohe.php` |
| M2 | Duplicate `admin_enqueue` in settings | Removed |
| M3 | Asset hook names assumed Persian menu slug | Enqueue by `page` query |
| M4 | Fake Yoast scores (`99`) | Left as original product behavior; documented as SEO-spam risk |
| M5 | `fatxt`/`entxt` unused | Documented; not silently removed |

### Low

L1: Emoji-heavy UI. L2: Estedad font scoped to `.smp-wrap` (acceptable). L3: Empty `admin/` directory.

## D. Security notes (pre-fix)

- Nonces existed on forms; capability `edit_posts` only.
- Uploads checked extension + `wp_check_filetype` (not magic-byte).
- SSRF filter missed some metadata IPs / DNS rebinding (residual risk).
- getID3 is a large third-party tree (GPL-compatible). Host scripts must not be web-reachable without secret.

## E. i18n / RTL (pre-fix)

Hardcoded Persian in PHP. Text domain declared but unused. Admin CSS already `direction: rtl`. No translation files.

## F. Compatibility

PHP 7.4+ syntax (`??`, `[]`). Targets WP 6.0+. Depends on theme meta keys and optional taxonomies (`singer`, …). Not a standalone public music player.
