# Sajad Music Publisher

WordPress admin plugin for publishing singles, remixes, nohe, music videos, and albums on Persian music sites, with optional off-site download hosting.

**Version:** 1.0.0  
**Requires:** WordPress 6.0+, PHP 7.4+  
**License:** GPL-2.0-or-later  
**Text domain:** `sajad-music-publisher`

## Features

- Dedicated admin screens for each release type
- Cover image upload or URL, 128/320 audio, lyrics and credits
- Draft or publish (publish requires `publish_posts`)
- Configurable download-host endpoints and category IDs
- Shared secret between WordPress and `hostdl` scripts
- RTL admin UI and Persian translations

## Installation

1. Upload `music-publisher.zip` via Plugins → Add New → Upload Plugin.
2. Activate **Sajad Music Publisher**.
3. Open **Music panel → Settings**. Set download host URLs, site domain, category IDs, and a shared secret.
4. Copy the `hostdl/` directory to the download server document root. Copy `config.sample.php` to `config.php` and use the **same** secret.

## Usage

Editors with `edit_posts` open **Music panel**, fill the form, and submit. The plugin creates a standard post and stores theme-oriented meta (`music320`, `musics_type`, Yoast keys, FIFU image URL, etc.).

## Shortcodes / blocks

None.

## Troubleshooting

- If files never appear on the download host, confirm endpoint URLs, secret, and that POST keys match (this build sends both legacy and hostdl names).
- If terms are missing, the theme must register taxonomies `singer`, `songwriter`, `composer`, `regulator`, `mixmaster`.
- Covers land in the Media Library year/month folder via `wp_upload_dir()`.

## Compatibility

Designed for classic posts + typical Iranian music themes. Not tested against a live WordPress install in this release environment.

## Support

Author site: https://sajadmasoumi.com

## License

GPL-2.0-or-later. Bundled getID3 is GPL-compatible. Jalali helpers (jdf) are GNU/LGPL.
