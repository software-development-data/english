# English Quest Pro — Hostinger deployment

This repository holds the deployable English Quest website for `https://english.soloworks.pro`.

## Layout

- `public_html/` — static website, assets, custom 404 page, and `/api/auth.php` endpoint.
- `private/` — PHP backend modules, outside the public document root.
- `database/` — schema and starter curriculum SQL.

## Hostinger setup

1. Point the `english.soloworks.pro` document root to this repository's `public_html/` folder.
2. Keep `private/` as a sibling of `public_html/`; never put it under the public web root.
3. Copy `private/config.example.php` to `private/config.php` on the server and fill in the database, SMTP and optional AI secrets. `private/config.php` is ignored by Git.
4. Back up the target database, then import `database/ENGLISH_QUEST_COMPLETE.sql`, `database/starter.sql`, and `database/v5.sql` in that order.
5. Use PHP 8.1+ with PDO MySQL and HTTPS enabled.
6. Open `/api/auth.php?action=health`; it should return JSON with `ok: true` once the private config and database connection are valid.

The auth API returns JSON diagnostics for missing configuration/database schema. Do not paste secrets into GitHub issues or commits.

## Source archive

The full Hostinger package, including the private `config.php` provided for direct deployment, is kept outside this public repository. Create that server-only file from the template above; never commit credentials.
