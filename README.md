# YOURLS to PeakURL

Export YOURLS links into PeakURL-compatible import files.

`YOURLS to PeakURL` adds a small admin page inside YOURLS that exports all of your short links in formats PeakURL already accepts in its Bulk Import screen.

Current plugin version: `1.0.0`

## Features

- Export all YOURLS links as `CSV`, `JSON`, or `XML`
- Generate a CSV that is ready for direct upload into PeakURL
- Preserve the YOURLS short keyword as the PeakURL alias
- Preserve the YOURLS title when one exists
- Use YOURLS admin-page registration and nonce protection for downloads

## What gets exported

The plugin reads the standard YOURLS URL table and maps fields like this:

- YOURLS `url` -> PeakURL `url` or `destinationUrl`
- YOURLS `keyword` -> PeakURL `alias`
- YOURLS `title` -> PeakURL `title`

YOURLS core does not store password-protected or expiry-based links in its standard schema, so the plugin includes those PeakURL fields as blank values.

## Install In YOURLS

### Option 1: Copy the repo folder

1. Download or clone this repository.
2. Copy the repository folder into your YOURLS install at:
   `user/plugins/YOURLS-to-PeakURL`
3. Confirm the plugin file exists at:
   `user/plugins/YOURLS-to-PeakURL/plugin.php`
4. Open your YOURLS admin area.
5. Go to `Manage Plugins`.
6. Activate `YOURLS to PeakURL`.

### Option 2: Clone directly on the server

```bash
cd /path/to/yourls/user/plugins
git clone https://github.com/PeakURL/YOURLS-to-PeakURL.git
```

Then activate the plugin from `Manage Plugins` in YOURLS.

## Export From YOURLS

1. Sign in to your YOURLS admin area.
2. Open `Manage Plugins`.
3. Click the plugin page link for `PeakURL Export`.
4. Choose one of the export buttons:
   - `Download CSV for PeakURL`
   - `Download JSON`
   - `Download XML`
5. Save the exported file.

For most migrations, use the CSV export first. It is the simplest path into PeakURL.

## Import Into PeakURL

1. Sign in to your PeakURL dashboard.
2. Open `Dashboard -> Bulk Import`.
3. Open the `File Upload` tab.
4. Upload the file exported from YOURLS.
5. Start the import and review the results.

If you use the CSV export from this plugin, no manual column remapping should be needed.

## PeakURL-Compatible Export Shapes

### CSV

The CSV export uses these headers:

```csv
url,alias,title,password,expires
```

Example:

```csv
url,alias,title,password,expires
https://example.com/article,article-1,Example Article,,
https://example.com/pricing,pricing,Pricing Page,,
```

### JSON

```json
[
  {
    "destinationUrl": "https://example.com",
    "alias": "example",
    "title": "Example",
    "password": "",
    "expiresAt": ""
  }
]
```

### XML

```xml
<urls>
  <url>
    <destinationUrl>https://example.com</destinationUrl>
    <alias>example</alias>
    <title>Example</title>
    <password></password>
    <expiresAt></expiresAt>
  </url>
</urls>
```

## Things To Keep In Mind

- This plugin exports links from the standard YOURLS URL table.
- Password and expiry values are exported as blank fields because YOURLS core does not provide those fields by default.
- If your YOURLS install uses custom plugins that add extra link metadata, this plugin does not export that metadata yet.
- If you want the cleanest migration path, export CSV and import that into PeakURL first.

## Troubleshooting

### The plugin does not appear in YOURLS

Check that the path is exactly:

```txt
user/plugins/YOURLS-to-PeakURL/plugin.php
```

YOURLS expects a `plugin.php` file inside the plugin directory.

### The export page link does not appear

Make sure the plugin is activated in `Manage Plugins`.

### PeakURL import fails

Use the CSV export first. It matches PeakURL's Bulk Import file format directly.

## Development

This plugin is intentionally small and built around official YOURLS plugin APIs:

- plugin header metadata
- direct-call guard with `YOURLS_ABSPATH`
- `yourls_register_plugin_page(...)`
- `load-<plugin-page>` hook for secure downloads
- `yourls_get_db(...)` for database access
