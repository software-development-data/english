# English Quest — source import from soloworks.pro.zip

Imported alongside the existing Vexon archive; these are separate applications.

- `public_html/`: existing static frontend export. Its account features require the PHP backend; uploading HTML alone does not provide a working account system.
- `private/`: PHP backend intended to remain outside the public document root.
- `app/`: older Next.js MVP source; it does not reproduce every feature of the newer exported frontend.
- `source/app/`: partial additional source from the supplied package, not a complete standalone project.
- `assets/` and other asset directories: supplied artwork and catalogs.

## Preparation performed

Removed duplicate backup directories, private configuration files and unreviewed database exports. Replaced identified credential literals in text files. No production credentials are provided. Create private server configuration separately, and rotate credentials previously included in the original archive.

The supplied QA reports describe previous checks, not tests performed for this import. This import has not been rebuilt or tested against a live PHP/MySQL server. Database provisioning and complete source reconciliation remain necessary before production deployment.

## Hosting

The full application requires PHP/MySQL hosting and a private configuration outside the document root. GitHub stores the code; it does not run this PHP backend. GitHub Pages alone cannot run the full application. The Vexon application at the repository root instead requires Node.js and SQLite.
