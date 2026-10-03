# Changelog

## 1.0.0 — 2026-10-03

Production baseline of the existing 1.0.0 prototype (same version: already labeled 1.0.0).

### Security

- Shared secret for download-host endpoints (`X-SMP-Key`)
- Host downloads via cURL with HTTP/HTTPS only; block private IPs
- Sanitize file-path components (no traversal)
- Flash messages via transients (no user text in query string)
- `publish_posts` required to publish
- Settings URL sanitization; category IDs as integers
- Direct-access guards on WP PHP files
- Uninstall deletes options only

### Fixed

- WP vs host POST parameter mismatch (album, video, artist fields)
- Duplicate settings menu
- Missing remix/nohe host scripts
- Uploads used `ABSPATH/wp-content` instead of `wp_upload_dir()`
- Terms assigned even if taxonomies were absent

### Changed

- Plugin author and design credited to تیم توسعه اتحاد وردپرس (etehadwp.com)

- Load text domain `sajad-music-publisher`
- Persian `fa_IR` translations (`.l10n.php`)
- RTL logical margins on admin wrap
- Plugin headers (Requires at least 6.0, PHP 7.4)

### Added

- `uninstall.php`, `hostdl/bootstrap.php`, `hostdl/config.sample.php`
- Documentation: AUDIT, PRODUCT, ROADMAP, README, README-FA
